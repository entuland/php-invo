<?php

  namespace App\Records;

  use App\Utils\Msg;
  use App\Utils\Field;
  use App\Utils\Settings;
  use App\Query\QueryRunner;
  use App\Utils\Barcode;
  use App\Utils\Icons;
  use App\Utils\Format;

  class Item extends Record {

    function __construct($id = null) {
      $this->table_name = 'item';
      $this->column_name = 'id';
      $this->unique_column = 'barcode';
      $this->values['id'] = $id;
      $this->reload();
    }

    function recalcRoute() {
      self::recalculateAllStocks();
      Msg::msg(t('All items\' stocks recalculated'));
    }

    static function recalculateStock($id) {
      $sql = <<<SQL
        UPDATE 
          `item` 
        SET 
          `stock` = (
            SELECT 
              SUM(`change`) 
            FROM `change`
              WHERE `id_item` = :id 
            GROUP BY `id_item`
          )
        WHERE
          `id` = :id
        LIMIT 1
SQL;
      QueryRunner::runQuery($sql, ['id' => $id]);
    }

    static function recalculateAllStocks() {
      $sql = <<<SQL
        UPDATE 
          `item` 
        SET 
          `stock` = (
            SELECT 
              SUM(`change`) 
            FROM `change`
              WHERE `id_item` = `item`.`id`
            GROUP BY `id_item`
          )
SQL;
      QueryRunner::runQuery($sql);
    }

    static function forceStock($id, $new_stock, $price, $verified = 1,
                               $log_prefix = '') {
      if(is_string($log_prefix)) {
        $log_prefix .= '-';
      }
      else {
        $log_prefix = '';
      }
      $item = Factory::newItem($id);
      if(!$item || $id !== $item->id) {
        Msg::error(t('Invalid item ID %s', $id));
        return false;
      }
      if($new_stock === false || $new_stock < 0) {
        Msg::error(t('Cannot set negative stock for %s',
                     $item->oneLinerDisplay()));
        return false;
      }
      $current_stock = $item->stock;
      $new_change = $new_stock - $current_stock;
      if(!$new_change) {
        Msg::warning(t(
            'Stock for %s is already set to %d', $item->oneLinerDisplay(),
            $current_stock
        ));
        return false;
      }
      $inventory = Document::getDailyInventory();
      $change = Factory::newChange();
      $values = [
        'change' => $new_change,
        'price' => $price,
        'id_document' => $inventory->id,
        'id_item' => $item->id,
      ];
      $new_change_response = $change->newFromValuesAndSave($values, $log_prefix);
      if($new_change_response) {
        $item->reload();
        $item->verified = intval($verified);
        $item->selfLog($log_prefix . 'pre-save');
        if($item->save()) {
          Msg::msg(t(
              'Stock for item %s successfully updated', $item->oneLinerDisplay()
          ));
          return $item;
        }
        else {
          Msg::error(t(
              'Unable to save item %s', $item->oneLinerDisplay()
          ));
        }
      }
      else {
        Msg::error(t(
            'Unable update and verify stock of %s', $item->oneLinerDisplay()
        ));
      }
      return false;
    }

    function copyFromLatest() {
      $this->copyFrom($this->getLatest());
    }

    function copyFrom($instance) {
      $class = $this->className();
      if(!$instance || $instance->className() !== $class || !in_array($class,
                                                                      ['item'])) {
        return;
      }
      $settings = $this->settings();
      foreach($settings as $field_name => $set) {
        $field = new Field($set);
        if($field_name !== 'barcode' && $field->widget !== 'date' && $field->widget !== 'datetime-local' && !$field->readonly) {
          $this->values[$field_name] = $instance->values[$field_name];
        }
      }
    }

    function autoPriceSettings() {
      return [
        'load' => [
          'widget' => 'number',
          'min' => 0,
        ],
        'purchase' => [
          'widget' => 'number',
          'min' => 0,
          'prefix' => '€',
        ],
        'multiplier' => [
          'widget' => 'number',
          'default' => null,
        ],
        'id_document' => [
          'widget' => 'text',
          'readonly' => true,
          'default' => Document::getCurrent()->id,
        ],
      ];
    }

    function addAutoPriceFields($form) {
      $autoprice_settings = $this->autoPriceSettings();
      $new_fields = [];
      foreach($autoprice_settings as $field_name => $settings) {
        $value = null;
        if(array_key_exists('default', $settings)) {
          $value = $settings['default'];
        }
        $new_fields[$field_name] = new Field($settings, $value,
                                             $this->className(), $field_name);
      }
      $fields = (array) $form->fields;
      $index = array_search('price', array_keys($fields));
      array_insert($fields, $new_fields, $index);
      $form->fields = (object) $fields;
      return $form;
    }

    function editRoute() {
      $output = $this->renderPrintLabelButton();
      return $output . parent::editRoute();
    }

    function renderPrintLabelButton() {
      if(!$this->id) {
        return '';
      }
      $text = t('Print item labels');
      return <<<HTML
<a class="button nocover" href="javascript:print.multipleLabels({$this->id})">$text</a>
HTML;
    }

    function prepareEditForm($form) {
      if($this->isNew() && $this->editContext()) {
        $document = Document::getCurrent();
        $docmsg = $document->oneLinerDisplay(Record::ADD_LINK);
        if(Document::alternateMode()) {
          appendMainClasses('alternate');
        }
        Msg::warning(t('Loading into %s', $docmsg));
        $clone_item = Factory::newItem(
            filter_input(INPUT_GET, 'clone', FILTER_VALIDATE_INT)
        );
        if($clone_item && $clone_item->id) {
          $this->copyFrom($clone_item);
          Msg::warning(t(
              'Cloning requested item %s',
              $clone_item->oneLinerDisplay(Record::ADD_LINK)
          ));
        }
        else {
          $latest_item = $this->getLatest();
          Msg::warning(t(
              'Creating new item from latest one %s',
              $latest_item->oneLinerDisplay(Record::ADD_LINK)
          ));
          $this->copyFrom($latest_item);
        }
      }

      if($this->listContext()) {
        return parent::prepareEditForm($form);
      }

      if(!$this->isNew()) {
        $base = config('publicbase');
        $prepared_form = parent::prepareEditForm($form);
        $add_text = t('Load/unload');
        $prepared_form->buttons .= <<<HTML
    <a class="button unload" href="$base/?barcode={$this->barcode}">$add_text</a>
HTML;
        $clone_text = t('Clone as new');
        $prepared_form->buttons .= <<<HTML
    <a class="button clone" href="$base/item/new?clone={$this->id}">$clone_text</a>
HTML;
        $request_text = t('Request');
        $prepared_form->buttons .= <<<HTML
        <a class="button request" 
           href="$base/request/new?item_id={$this->id}"
        >$request_text</a>
HTML;
      }
      else {
        $temp_form = parent::prepareEditForm($form);
        $temp_form->fields->barcode->value = filter_input(INPUT_GET, 'barcode');
        $temp_form->fields->verified->value = 1;
        $prepared_form = $this->addAutoPriceFields($temp_form);
        unset($prepared_form->fields->stock);
      }

      return $prepared_form;
    }

    function processEditPostRedirection($is_new) {
      $redirection = parent::processEditPostRedirection($is_new);
      Category::quickSave($this->category);
      if($is_new) {
        $purchase = getPOST('purchase');
        $load = getPOST('load');
        $id_document = getPOST('id_document');
        if($load) {
          $change = Factory::newChange();
          $values = [
            'change' => $load,
            'price' => $purchase,
            'id_document' => $id_document,
            'id_item' => $this->id,
          ];
          $new_change_response = $change->newFromValuesAndSave($values,
                                                               'firstload');
          if($new_change_response) {
            Msg::msg(t(
                'First item load of %s %s successfully created in current document',
                $load, nt('unit', 'units', $load)
            ));
            Document::setCurrent($id_document);
          }
        }
        return '';
      }
      return $redirection;
    }

    function getCellsAdditionalButtons($global_settings) {
      $additional = parent::getCellsAdditionalButtons($global_settings);
      $base = config('publicbase');
      if($global_settings->showInlineCloneButtons) {
        $icon = Icons::i('copy');
        $title = t('Clone');
        $additional .= <<<HTML
        <a class="button clone square-button" 
           title="$title"
           href="$base/item/new?clone={$this->id}"
        >$icon</a>
HTML;
      }

      if($global_settings->showInlineUnloadButtons) {
        $icon = Icons::i('expand');
        $title = t('Load/unload');
        $additional .= <<<HTML
        <a class="button unload square-button" 
           title="$title"
           href="$base/?barcode={$this->barcode}"
        >$icon</a>
HTML;
      }

      if($global_settings->showInlineRequestButtons) {
        $icon = Icons::i('clipboard');
        $title = t('Request');
        $additional .= <<<HTML
        <a class="button request square-button" 
           title="$title"
           href="$base/request/new?item_id={$this->id}"
        >$icon</a>
HTML;
      }

      $additional .= Category::specialsDropdown('item', $this->id);

      return $additional;
    }

    function getNextCustomItemNumber() {
      $sql = <<<SQL
        SELECT
          `barcode`
        FROM
          `item`
        WHERE
          `barcode` LIKE ?
SQL;
      $settings = new Settings();
      $item_prefix = $settings->custom_item_prefix;
      $barcodes = QueryRunner::runQueryColumn($sql, [$item_prefix . '%']);
      $max = 0;
      foreach($barcodes as $barcode) {
        $number = str_replace($item_prefix, '', $barcode);
        $max = max($max, intval($number, 10));
      }
      return $this->formatCustomItemNumber($max + 1);
    }

    function formatCustomItemNumber($number) {
      $settings = new Settings();
      $prefix = $settings->custom_item_prefix;
      return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    function renderDisplay() {
      $settings = new Settings();
      $category_discounts = Category::getDiscounts($this->category);
      $default_discounts = $settings->getDefaultDiscounts();
      $cats = explode('|', $this->category);
      $fixed = Category::getFixedPrices();
      $fixed_prices = $fixed;
      foreach($fixed as $cat => $price) {
        unused($price);
        if(!in_array($cat, $cats)) {
          unset($fixed_prices[$cat]);
        }
      }
      $discounts = [
        'fixedprices' => $fixed_prices,
        'category' => $category_discounts,
        'default' => $default_discounts,
      ];
      $json = safeMarkup(json_encode($discounts));
      $output = <<<HTML
  <span id="discount-data" data-discounts="$json"></span>
HTML;
      $output .= parent::renderDisplay();
      return $output;
    }

    function specialListButtons() {
      ob_start();
      ?>
      <div class="button-group">
        <span class="button-group-label"><?= t('Selected') ?>:</span> 
        <a class="button label-button label-selected" href="javascript:print.selected('labels')"><?= t('Print labels') ?></a>
      </div>
      <div class="button-group">
        <span class="button-group-label"><?= t('All') ?>:</span> 
        <a class="button label-button label-all" href="javascript:print.all('labels')"><?= t('Print labels') ?></a>
      </div>
      <?php
      return ob_get_clean();
    }

    function renderPrintLabels($ids) {
      $output = '';
      foreach($ids as $id) {
        $output .= $this->renderPrintLabel($id);
      }
      return $output;
    }

    function discountedPrice() {
      $rounding = (new Settings())->defaultDiscountRounding;
      $fixed_prices = Category::getFixedPrices();
      $discounts = Category::getDiscounts($this->category);
      $cats = explode('|', $this->category);
      foreach($cats as $cat) {
        if(isset($fixed_prices[$cat])) {
          return number_format($fixed_prices[$cat], 2);
        }
      }
      foreach($discounts as $discount) {
        $percent = $discount['percent'];
        $amount = $this->price / 100 * (100 - $percent);
        if($rounding) {
          return Format::applyRounding($amount, $rounding);
        }
        return number_format($amount, 2);
      }
      return $this->price;
    }

    function renderPrintLabel($id) {
      $settings = new Settings();
      $label_template = $settings->labelTemplate();
      $item = Factory::newItem($id);
      $discounted = $item->discountedPrice();
      if($discounted !== $item->price) {
        $item->price = '<em class="strike">' 
          . Format::money($item->price) . '</em><br>' . Format::money($discounted);  
      } else {
        $item->price = Format::money($item->price);
      }
      $label_with_item = $item->doReplacements($label_template,
                                               t($this->className()));
      $label_with_markdown = Factory::parsedown($label_with_item);
      $barcoder = new Barcode($item->barcode);
      $datauri = $barcoder->imageSrc();
      $barcode = <<<HTML
      <figure class="barcode">
        <img src="$datauri">
      </figure>
HTML;

      $card_with_barcode = str_replace('[barcode]', $barcode,
                                       $label_with_markdown);

      return <<<HTML
<div class="label">
  <div class="label-inner">
      $card_with_barcode
  </div>
</div>
HTML;
    }

    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'barcode' => [
          'widget' => 'text',
          'required' => true,
          'schema' => self::varcharSchema(128),
          'autocomplete' => true,
          'autolimit' => 5,
        ],
        'id_size' => [
          'widget' => 'text',
          'reference' => 'size',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'price' => [
          'widget' => 'number',
          'prefix' => '€',
          'min' => 0,
          'required' => true,
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'category' => [
          'widget' => 'text',
          'schema' => self::varcharSchema(128),
          'autocomplete' => true,
        ],
        'id_make' => [
          'widget' => 'text',
          'reference' => 'make',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'description' => [
          'widget' => 'text',
          'required' => true,
          'schema' => self::varcharSchema(64),
          'autocomplete' => true,
          'autolimit' => 3,
        ],
        'article' => [
          'widget' => 'text',
          'schema' => self::varcharSchema(64),
          'autocomplete' => true,
        ],
        'producer_code' => [
          'widget' => 'text',
          'schema' => self::varcharSchema(64),
          'autocomplete' => true,
        ],
        'id_um' => [
          'widget' => 'text',
          'reference' => 'um',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'id_color' => [
          'widget' => 'text',
          'reference' => 'color',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
        ],
        'stock' => [
          'widget' => 'number',
          'readonly' => true,
          'keep_visible' => true,
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'verified' => [
          'widget' => 'select',
          'options' => [t('no'), t('yes')],
          'default_mode' => 'key',
          'add_select_option' => true,
          'schema' => self::tinyintSchema(),
        ],
        'created' => [
          'widget' => 'datetime-local',
          'default' => date(Factory::DATETIME_FORMAT),
          'schema' => self::datetimeSchema(),
        ],
        'modified' => [
          'widget' => 'datetime-local',
          'default' => date(Factory::DATETIME_FORMAT),
          'schema' => self::datetimeSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }

  }
  