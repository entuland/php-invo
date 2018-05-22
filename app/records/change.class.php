<?php

  namespace App\Records;
  use App\Utils\Msg;

  class Change extends Record  {
    
    function __construct($id = null) {
      $this->table_name = 'change';
      $this->column_name = 'id';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    function uniqueColumn() {
      return false;
    }
    
    function newFromValuesAndSave($values, $log_prefix = false) {
      $change = Factory::newChange();
      $new_values = $change->validateNewValues($values, Record::SKIP_FULL_CHECK);
      if(!$new_values) {
        return false;
      }
      if($log_prefix) {
        $log_prefix .= '-';
      }
      $change->selfLog($log_prefix . 'pre-create');
      foreach($new_values as $field_name => $value) {
        $change->disable_readonly_checks = true;
        $change->{$field_name} = $value;
      }
      return $change->save();
    }
    
    function validateNewValues($values, $full_check = true) {
      $new_values = parent::validateNewValues($values, $full_check);
      if(!$new_values) {
        return false;
      }
      $id_item = $this->id_item;
      if(!$id_item) {
        if(!array_key_exists('id_item', $new_values) || !$new_values['id_item']) {
          Msg::error(t('Missing item ID during change edit'));
          return false;
        }
        else {
          $id_item = $new_values['id_item'];
        }
      }
      $item = Factory::newItem($id_item);
      if(!$item->id) {
        Msg::error(t('Invalid item ID %s during change edit', $id_item));
        return false;
      }
      if(!$this->change) {
        $this->change = 0;
      }
      if(array_key_exists('change', $new_values)) {
        $resulting_stock = $new_values['change'] + $item->stock - $this->change;
        if($resulting_stock < 0) {
          Msg::error(t('Cannot set negative stock for %s',
            $item->oneLinerDisplay(Record::ADD_LINK)));
          return false;
        }
      }
      return $new_values;
    }
    
    function prepareEditForm($form) {
      
      $overrides = ['id_document', 'id_item'];
      
      foreach($overrides as $field_name) {
        $override = filter_input(INPUT_GET, $field_name, FILTER_VALIDATE_INT);
        if(strlen($override)) {
          $this->$field_name = $override;
        }
      }
      
      $prepared_form = parent::prepareEditForm($form);
      
      $item_id = $prepared_form->fields->id_item->value;
      $item = Factory::newItem($item_id);
      $form->before_form .= $item->renderDisplay();

      $document_id = $prepared_form->fields->id_document->value;
      $document = Factory::newDocument($document_id);
      $form->before_form .= $document->renderDisplay();

      return $prepared_form;
    }

    function prepareTableHeaders($fields, $skip_fields) {
      $new_headers = [];
      foreach($fields as $field) {
        if($field === 'price') {
          $new_headers[] = 'selling';
          $new_headers[] = 'purchase';
        } 
        else {
          $new_headers[] = $field;
        }
      }
      return parent::prepareTableHeaders($new_headers, $skip_fields);
    }
    
    function prepareCells($fields, $skip_fields, $add_links = true) {
      unused($add_links);
      $cells = parent::prepareCells($fields, $skip_fields);
      $new_cells = [];
      $change = floatval($cells['change']['content']);
      foreach($cells as $index => $cell) {
        $content = $cell['content'];
        if($index === 'price') {
          if($change <= 0) {
            $new_cells[] = [
              'content' => $content,
              'class' => 'field-price selling',
            ];
            $new_cells[] = [
              'content' => ' ',
              'class' => 'field-price purchase',
            ];
          } 
          else {
            $new_cells[] = [
              'content' => ' ',
              'class' => 'field-price selling',
            ];
            $new_cells[] = [
              'content' => $content,
              'class' => 'field-price purchase',
            ];
          }
        } 
        else {
          $new_cells[$index] = $cell;
        }
      }
      if($change === 0.0) {
        $new_cells['change']['content'] = t('Trial');
      }

      return $new_cells;
    }
    
    function specialListButtons() {
      ob_start();
?>
    <button type="submit" name="button-action" value="confirm-move-changes"><?= t('Move into current document') ?></button>
<div class="button-group">
  <span class="button-group-label"><?= t('Selected')?>:</span> 
  <a class="button label-button label-selected" href="javascript:print.selected('labels')"><?= t('Print labels') ?></a>
</div>
<div class="button-group">
  <span class="button-group-label"><?= t('All') ?>:</span> 
  <a class="button label-button label-all" href="javascript:print.all('labels')"><?= t('Print labels') ?></a>
</div>
<?php
      return ob_get_clean();
    }
    
    function postUpdate() {
      Document::recalculateRows($this->id_document);
      Item::recalculateStock($this->id_item);
      Person::recalculateBalance($this->id_person);
      parent::postUpdate();
    }
    
    function getIntroButtons() {
      return '';
    }
    
    function safeDelete($log_prefix = '') {
      $children_classes = $this->getChildren();
      foreach($children_classes as $class => $ids) {
        if($class === 'payment') {
          foreach($ids as $id) {
            $payment = new Payment($id);
            if($payment->id_change == $this->id) {
              $payment->disable_readonly_checks = true;
              $payment->id_change = null;
              $payment->notes .= ' [x' . $this->id . ']';
              $payment->selfLog('change-reference-deleted');
              $payment->save();
              Msg::warning(t('Payment %s converted to credit', $payment->oneLinerDisplay()));
            }
          }
        }
      }
      return parent::safeDelete($log_prefix);
    }
    
    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'id_document' => [
          'widget' => 'text',
          'reference' => 'document',
          'keep_visible' => true,
          'readonly' => true,
          'required' => true,
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
        ],
        'id_item' => [
          'widget' => 'text',
          'reference' => 'item',
          'keep_visible' => true,
          'readonly' => true,
          'required' => true,
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
        ],
        'id_person' => [
          'widget' => 'text',
          'reference' => 'person',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'change' => [
          'widget' => 'number',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'price' => [
          'widget' => 'number',
          'required' => true,
          'min' => 0,
          'prefix' => '€',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'date' => [
          'widget' => 'datetime-local', 
          'default' => date(Factory::DATETIME_FORMAT),
          'schema' => self::datetimeSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
  