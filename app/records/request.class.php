<?php

  namespace App\Records;
  
  use App\Utils\Settings;
  use App\Utils\Icons;
    
  class Request extends Record {
    
    function __construct($id = null) {
      $this->table_name = 'request';
      $this->unique_column = 'id';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    function listRoute() {
      \App\Router::redirect(config('publicbase') . '/request/filter');
    }
    
    function prepareEditForm($form) {
      $person_id = filter_input(INPUT_GET, 'person_id');
      if($person_id) {
        $this->id_person = $person_id;
      }

      $item_id = filter_input(INPUT_GET, 'item_id');
      if($item_id) {
        $item = Factory::newItem($item_id);
        $this->request = $item->oneLinerDisplay();
      }
      return parent::prepareEditForm($form);
    }
    
    function processEditPostRedirection($is_new) {
      parent::processEditPostRedirection($is_new);
      return 'request/filter/closed/never';
    }
        
    function prepareTableHeaders($fields, $skip_fields) {
      $settings = new Settings();
      if($this->printMailFaxContext() && $settings->hidePrintRequestsHeaders) {
        return [];
      }
      
      $headers = parent::prepareTableHeaders($fields, $skip_fields);
      
      if($this->printMailFaxContext()) {
        unset($headers['closed']);
        unset($headers['id_person']);
      }
      
      if($this->listContext() && $settings->enableRequestGallery) {
        $headers['images'] = [
          'text' => 'images',
          'sortable' => false,
          'translatable' => true,
        ];
      }
      
      return $headers;
    }
    
    function prepareCells($fields, $skip_fields, $add_links = true) {
      $cells = parent::prepareCells($fields, $skip_fields, $add_links);
      $settings = new Settings();
      if($this->printMailFaxContext()) {
        $closed = '';
        if(isset($cells['closed']['content'])) {
          $closed = trim($cells['closed']['content']);
        }
        unset($cells['closed']);          
        if($closed) {
          $closed = t('closed') . ': ' . $closed;
        }
        $person = '';
        if(isset($cells['id_person']['content'])) {
          $person = trim($cells['id_person']['content']);
        }
        unset($cells['id_person']);          
        if($closed && $person) {
          $closed .= '<br>';
        }
        if(!isset($cells['notes'])) {
          $cells['notes'] = [
            'content' => '',
            'class' => 'field-notes',
          ];
        }
        $cells['notes']['content'] .= $closed . $person;
      }
      
      if($this->listContext() && $settings->enableRequestGallery) {
        $cells['images'] = [
          'content' => count($this->getImageItems()),
          'class' => 'field-images',
        ];
      }

      return $cells;
    }
    
    function renderIdsForPrint($ids_or_instances) {
      $settings = new Settings();
      $instances = [];
      foreach($ids_or_instances as $id_or_instance) {
        if($id_or_instance instanceof Record) {
          $instances[] = $id_or_instance;
        }
        else {
          $instances[] = Factory::newInstance($this->className(), $id_or_instance);
        }
      }
      $images_output = '';
      foreach($instances as $inst) {
        $inst->setContext($this->context);
        if($inst->printImagesEnabled()) {
          $gal = $inst->getGallery();
          $rendered_images = $gal->renderImages($inst->oneLinerDisplay());
          $images_output .= $rendered_images; 
        }
      }
      $records_output = '';
      if($settings->groupRequestsByProvider) {
        $skip_fields = $this->getPrintSkipFields();

        $groups = [];

        foreach($instances as $inst) {
          $company_instance = Factory::newCompany($inst->id_company);
          $company = $company_instance->oneLinerDisplay() . ' ' . $inst->provider_section;
          if(!trim($company)) {
            $company = '';
          }
          $groups[$company][] = $inst;
        }
        
        ksort($groups);
        
        $skip_fields[] = 'id_company';
        $skip_fields[] = 'provider_section';
        foreach($groups as $caption => $instances) {
          if($this->mailFaxContext()) {
            $caption = '';
          }
          $records_output .= $this->renderIds($instances, $skip_fields, Record::OMIT_LINKS, $caption);
        }
      } else {
        $records_output = parent::renderIdsForPrint($instances);
      }
      return $records_output . '<div class="images-section">' . $images_output . '</div>';
    }
    
    function filterButtons() {
      ob_start();
?>
<a class="button" href="<?= config('publicbase') ?>/person"><?= t('Persons') ?></a>
<a class="button" href="<?= config('publicbase') ?>/request/new"><?= t('New request') ?></a>
<a class="button" href="<?= config('publicbase') ?>/request/search"><?= t('Search requests') ?></a>
<?= $this->getFilterButton('closed/never', t('Open requests')) ?>
<?= $this->getFilterButton('', t('All requests')) ?>
<hr>
<?php
      echo $this->getCompanyFilterButtons();
      return ob_get_clean();
    }
    
    function getCompanyFilterButtons() {
      $company_ids = <<< SQL
        SELECT 
          `id_company`
        FROM
          `request`
        WHERE
          `closed` = '0000-00-00 00:00:00'
        GROUP BY
          `id_company`
SQL;
      
      $statuses_for_company = <<<SQL
        SELECT 
          `status`
        FROM
          `request`
        WHERE
          `closed` = '0000-00-00 00:00:00'
        AND
          `id_company` = ?
        GROUP BY
          `status`
SQL;
      $ids = \App\Query\QueryRunner::runQueryColumn($company_ids);
      $buttons = [];
      
      $buttons[] = $this->getFilterButton('closed/never/status/print', t('print'));      
      
      foreach($ids as $id) {
        if(!intval($id)) {
          continue;
        }
        $statuses = \App\Query\QueryRunner::runQueryColumn($statuses_for_company, [$id]);
        $company = Factory::newCompany($id);
        foreach($statuses as $status) {
          $status = mb_strtolower($status);
          if($status === 'print') {
            continue;
          }
          $filter_path = 'closed/never/id_company/' . $id . '/status/' . $status;
          $buttons[] = $this->getFilterButton($filter_path, $company->name . ' ' . t($status));
        }
      }
      return implode($buttons);
    }
    
    function specialListButtons() {
      ob_start();
?>
<div class="button-group">
  <span class="button-group-label"><?= t('Selected')?>:</span> 
  <a class="button print-button print-selected" href="javascript:print.selected()"><?= t('Print') ?></a>
  <a class="button mail-button mail-selected" href="javascript:print.selected('mail')"><?= t('Mail') ?></a>
  <a class="button fax-button fax-selected" href="javascript:print.selected('fax')"><?= t('Fax') ?></a>
</div>
<div class="button-group">
  <span class="button-group-label"><?= t('All') ?>:</span> 
  <a class="button print-button print-all" href="javascript:print.all()"><?= Icons::i('print', 2, t('Print')) ?></a>
  <a class="button mail-button mail-all" href="javascript:print.all('mail')"><?= Icons::i('envelope', 2, t('Mail')) ?></a>
  <a class="button fax-button fax-all" href="javascript:print.all('fax')"><?= Icons::i('fax', 2, t('Fax')) ?></a>
</div>
<?php
      return ob_get_clean();
    }
    
    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        
        'opened' => [
          'widget' => 'date',
          'required' => true,
          'default' => date(Factory::DATE_FORMAT),
          'schema' => self::dateSchema(),
        ],
        
        'status' => [
          'widget' => 'select', 
          'options' => [
            'PRINT' => t('print'),
            'SENDEMAIL' => t('sendemail'),
            'EMAILSENT' => t('emailsent'),
            'SENDFAX' => t('sendfax'),
            'FAXSENT' => t('faxsent'),
          ],
          'default' => 'PRINT',
          'default_mode' => 'key',
          'required' => true,
          'schema' => self::varcharSchema(16),
        ],
        
        'id_company' => [
          'widget' => 'text',
          'reference' => 'company', 
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        
        'provider_section' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(32),
        ],
        
        'id_person' => [
          'widget' => 'text', 
          'reference' => 'person', 
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        
        'request' => [
          'widget' => 'textarea',
          'required' => true,
          'schema' => self::varcharSchema(256),
        ],
        
        'notes' => [
          'widget' => 'textarea',
          'schema' => self::varcharSchema(256),
        ],
        
        'closed' => [
          'widget' => 'date', 
          'schema' => self::dateSchema(),
        ],
        
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
  