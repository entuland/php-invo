<?php

  namespace App\Records;
  
  use App\Utils\Msg;
  use App\Utils\Icons;
  use App\Query\QueryRunner;
  
  class Document extends Record {
    private static $cur_doc_id = 0;
    private static $daily_doc_id = 0;
    private static $initialized = false;
    
    private static function init($skipInit = false) {
      if(!self::$initialized && !$skipInit) {
        self::$initialized = true;
        Document::initializeCurrent();
      }
    }
    
    function __construct($id = null, $skipInit = false) {
      self::init($skipInit);
      $this->table_name = 'document';
      $this->unique_column = 'id';
      $this->column_name = 'id';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    static function alternateMode() {
      $current = self::getCurrent();
      $daily = self::getDaily();
      return $current->id !== $daily->id;
    }
    
    static function getDaily() {
      if(!self::$daily_doc_id) {
        $doc = Factory::newDocument();
        $now = date(Factory::DATE_FORMAT);
        $doctype = Factory::newDocType();
        $company = Factory::newCompany();
        $doctype_id = $doctype->getForcedID(config('dailydocumentname'));
        $company_id = $company->getForcedID(config('stockcompanyname'));
        $search_params = [
          'id_doctype' => $doctype_id, 
          'id_company' => $company_id,
          'date' => $now . '%',
          'matching' => 'AND',
        ];
        $ids = $doc->find($search_params)['ids'];
        if($ids) {
          self::$daily_doc_id = $ids[0];
        } else {
          $doc->id_doctype = $doctype_id; 
          $doc->id_company = $company_id; 
          $doc->date = $now;
          $doc->selfLog('auto-pre-create');
          if($doc->save()) {
            self::$daily_doc_id = $doc->id;
          }
          else {
            throw new \Exception(t('Error during generation of daily document'));
          }
        }
      }
      return Factory::newDocument(self::$daily_doc_id);
    }
    
    static function getDailyInventory() {
      $doc = Factory::newDocument();
      $now = date(Factory::DATE_FORMAT);
      $doctype = Factory::newDocType();
      $company = Factory::newCompany();
      $doctype_id = $doctype->getForcedID(config('dailydocumentname'));
      $company_id = $company->getForcedID(config('stockcompanyname'));
      $params = [
        'id_doctype' => $doctype_id, 
        'id_company' => $company_id,
        'date' => $now . '%',
        'matching' => 'AND',
      ];
      $ids = $doc->find($params)['ids'];
      if($ids) {
        return Factory::newDocument($ids[0]); 
      }
      $doc->id_doctype = $doctype_id; 
      $doc->id_company = $company_id; 
      $doc->date = $now;
      $doc->selfLog('auto-pre-create');
      if($doc->save()) {
        return $doc;
      }
      else {
        throw new \Exception(t('Error during generation of daily inventory document'));
      }
    }
    
    static function initializeCurrent() {
      $session_id = $_SESSION['cur_doc_id'] ?? 0;
      $session_doc = Factory::newDocument($session_id);      
      $override_id = filter_input(INPUT_GET, 'cur_doc_id', FILTER_VALIDATE_INT);      
      $override_doc = Factory::newDocument($override_id);
      $daily_doc = self::getDaily();
      switch(true) {
        case $override_doc->id:
          $cur_doc_id = $override_doc->id;
          break;
        case $session_doc->id:
          $cur_doc_id = $session_doc->id;
          break;
        default:
          $cur_doc_id = $daily_doc->id;
      }      
      $_SESSION['cur_doc_id'] = self::$cur_doc_id = $cur_doc_id;
    }
    
    static function setCurrent($id_document) {
      $id = intval($id_document);
      $document = new self($id);
      if($id && $document->id === $id) {
        $_SESSION['cur_doc_id'] = self::$cur_doc_id = $id;
      }
    }

    static function getCurrent() {
      try {
        self::initializeCurrent();
        return Factory::newDocument(self::$cur_doc_id);
      } catch(\Exception $ex) {
        return Factory::newDocument();
      }
    }
    
    static function recalculateRows($id) {
      $sql = <<<SQL
        UPDATE
          `document` 
        SET 
          `rows` = (
            SELECT 
              COUNT(`id_document`) 
            FROM 
              `change` 
            WHERE 
              `id_document` = :id 
            GROUP BY 
              `id_document`
          )
        WHERE 
          `id` = :id 
        LIMIT 1
SQL;
      QueryRunner::runQuery($sql, ['id' => $id]);
    }
        
    static function recalculateAllRows() {
      $sql = <<<SQL
        UPDATE
          `document` 
        SET 
          `rows` = (
            SELECT 
              COUNT(`id_document`) 
            FROM 
              `change` 
            WHERE 
              `id_document` = `document`.`id` 
            GROUP BY 
              `id_document`
          )
SQL;
      QueryRunner::runQuery($sql);
    }
    
    function recalcRoute() {
      self::recalculateAllRows();
      Msg::msg(t('All document rows recalculated'));
    }
    
    function prepareEditForm($form) {
      $overrides = ['id_company', 'id_doctype'];
      
      foreach($overrides as $field_name) {
        $override = filter_input(INPUT_GET, $field_name, FILTER_VALIDATE_INT);
        if(strlen($override)) {
          $this->$field_name = $override;
        }
      }
      
      $prepared_form = parent::prepareEditForm($form);
      if($this->id) {
        $current_text = t('Set as current');
        $base = config('publicbase');
        $prepared_form->buttons .=<<<HTML
     <a class="button activatedocument" href="$base/?cur_doc_id={$this->id}">$current_text</a>
HTML;
      }
      
      return $prepared_form;
    }
    
    function processEditPostRedirection($is_new) {
      $redirection = parent::processEditPostRedirection($is_new);
      if($is_new) {
        $redirection = '?cur_doc_id=' . $this->id;
      }
      return $redirection;
    }
    
    function getCellsAdditionalButtons($global_settings) {
      $buttons = parent::getCellsAdditionalButtons($global_settings);
      if($global_settings->showInlineActivateDocumentButtons) {
        $icon = Icons::i('book');
        $title = t('Set as current');
        $base = config('publicbase');
        $buttons .=<<<HTML
     <a class="button square-button activatedocument" title="$title" href="$base/?cur_doc_id={$this->id}">$icon</a>
HTML;
      }      
      return $buttons;
    }
    
    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'id_doctype' => [
          'widget' => 'text',
          'reference' => 'doctype', 
          'required' => true,
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'id_company' => [
          'widget' => 'text',
          'reference' => 'company', 
          'required' => true,
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'number' => [
          'widget' => 'text',
          'schema' => self::varcharSchema(32),
        ],
        'notes' => [
          'widget' => 'textarea',
          'schema' => self::varcharSchema(256),
        ],
        'date' => [
          'widget' => 'date', 
          'default' => null,
          'required' => true,
          'schema' => self::dateSchema(),
        ],
        'total' => [
          'widget' => 'number',
          'min' => 0,
          'prefix' => '€',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'rows' =>  [
          'widget' => 'number',
          'readonly' => true,
          'schema' => self::intSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
  
