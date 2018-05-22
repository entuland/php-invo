<?php

  namespace App\Records;

  use App\Utils\Logger;
  use App\Utils\Msg;
  use App\Utils\Settings;
  use App\Utils\Field;

  abstract class Record {

    use Traits\DAO;
    use Traits\Delete;
    use Traits\Edit;
    use Traits\StaticHelpers;
    use Traits\Query;
    use Traits\Find;
    use Traits\Render;
    use Traits\Search;
    use Traits\Filter;
    use Traits\Unique;
    use Traits\Validate;

    const ADD_LINK = true;
    const OMIT_LINKS = false;
    const SKIP_FULL_CHECK = false;
    const LIST_CONTEXT = 1;
    const PRINT_CONTEXT = 2;
    const DISPLAY_CONTEXT = 4;
    const EDIT_CONTEXT = 8;
    const SEARCH_CONTEXT = 16;
    const FAX_CONTEXT = 32;
    const MAIL_CONTEXT = 64;
    const FILTER_CONTEXT = 128;

    protected $context = Record::LIST_CONTEXT;

    function listContext() {
      return $this->context & Record::LIST_CONTEXT;
    }

    function editContext() {
      return $this->context & Record::EDIT_CONTEXT;
    }

    function printContext() {
      return $this->context & Record::PRINT_CONTEXT;
    }

    function mailContext() {
      return $this->context & Record::MAIL_CONTEXT;
    }

    function faxContext() {
      return $this->context & Record::FAX_CONTEXT;
    }

    function mailFaxContext() {
      return $this->mailContext() || $this->faxContext();
    }

    function printMailFaxContext() {
      return $this->printContext() || $this->mailContext() || $this->faxContext();
    }

    function displayContext() {
      return $this->context & Record::DISPLAY_CONTEXT;
    }

    function searchContext() {
      return $this->context & Record::SEARCH_CONTEXT;
    }

    function filterContext() {
      return $this->context & Record::FILTER_CONTEXT;
    }

    function setContext($context) {
      $this->context = $context;
    }

    function addContext($context) {
      $this->context |= $context;
    }

    function removeContext($context) {
      $this->context &= ~$context;
    }

    function isNew() {
      return !$this->id;
    }

    function getSchema() {
      $record_schema = [];
      $settings = $this->defaultSettings();
      foreach($settings as $field_name => $settings) {
        $field_schema = $settings['schema'] ?? 'missing';
        if($field_schema === 'missing') {
          $class = $this->className();
          Msg::error("missing schema for $class $field_name");
        }
        $record_schema[$field_name] = $field_schema;
      }
      return $record_schema;
    }

    function selfLog($tag) {
      Logger::log(
        $this->resolvedFieldPairs(), $this->className(), $tag, $this->id
      );
    }

    function className() {
      return str_replace('app\\records\\', '', mb_strtolower(get_class($this)));
    }

    function tableName() {
      return $this->table_name;
    }

    function prepareOneLinerDisplay($fields = []) {
      static $global_settings = false;
      if(!$global_settings) {
        $global_settings = new Settings();
      }
      $new_fields = [];
      $class = $this->className();
      foreach($fields as $fieldname => $fieldcontent) {
        if($global_settings->skipOnLinerDisplay($class, $fieldname)) {
          continue;
        }
        $new_fields[$fieldname] = $fieldcontent;
      }
      return $new_fields;
    }

    function identifier($add_link = false) {
      return t($this->className()) . ' #' . $this->id . ' ' . $this->oneLinerDisplay($add_link);
    }

    function oneLinerDisplay($add_link = false) {
      $prepared_fields = [];
      foreach($this->resolvedFieldPairs() as $field => $values) {
        $prepared_fields[$field] = $values['displayed'];
      }
      $fields = $this->prepareOneLinerDisplay($prepared_fields);
      $output = implode(' ', $fields);
      if($add_link) {
        return $this->wrapInEditURL($output);
      }
      return $output;
    }

    function wrapInEditURL($text) {
      $edit_url = $this->editURL();
      $safe_text = safeMarkup($text);
      $base = config('publicbase');
      return <<<HTML
<a class="button" href="$base/$edit_url">$safe_text</a>
HTML;
    }

    function displayURL() {
      return '/' . $this->className() . '/' . $this->id;
    }

    function editURL() {
      return $this->displayURL() . '/edit';
    }

    function deleteURL() {
      return $this->displayURL() . '/delete';
    }

    function fields() {
      return array_keys($this->settings());
    }

    static function tinyintSchema() {
      return [
        'type' => 'tinyint(1)',
        'null' => 'YES',
        'key' => '',
        'default' => NULL,
        'extra' => ''
      ];
    }

    static function intSchema($index = false) {
      return [
        'type' => 'int(11)',
        'null' => 'YES',
        'key' => $index ? 'MUL' : '',
        'default' => NULL,
        'extra' => ''
      ];
    }

    static function referenceSchema() {
      return self::intSchema(true);
    }

    static function varcharSchema($size) {
      $intsize = intval($size) > 0 ? intval($size) : 64;
      return [
        'type' => 'varchar(' . $intsize . ')',
        'null' => 'YES',
        'key' => '',
        'default' => NULL,
        'extra' => '',
        'size' => $intsize,
      ];
    }

    static function dateSchema() {
      return [
        'type' => 'datetime',
        'null' => 'YES',
        'key' => '',
        'default' => NULL,
        'extra' => ''
      ];
    }

    static function datetimeSchema() {
      return [
        'type' => 'datetime',
        'null' => 'YES',
        'key' => '',
        'default' => NULL,
        'extra' => ''
      ];
    }

    function doReplacements($text, $raw_prefix) {
      $prefix = mb_strtolower($raw_prefix);
      $search = [];
      $replace = [];
      foreach($this->settings() as $fieldname => $settings) {
        $field = new Field($settings, $this->$fieldname);
        $value = '';
        if($fieldname === 'price') {
          $value = $this->$fieldname;
        }
        else if($field->displayValue(Field::OMIT_LINK, Field::OMIT_PREFIX)) {
          $value = $field->displayValue(Field::OMIT_LINK, Field::ADD_PREFIX);
        }
        if(trim($value)) {
          $value = '<span class="class-'
            . $this->className()
            . ' field field-' . $fieldname
            . '">' . $value . '</span>';
        }
        $search[] = '[' . $prefix . '.' . mb_strtolower(t($fieldname)) . ']';
        $replace[] = $value;
      }
      return str_replace($search, $replace, $text);
    }

    function settings() {
      return $this->filterSettings($this->defaultSettings());
    }

    function defaultSettings() {
      return [
        'id' => [
          'widget' => 'text',
          'readonly' => true,
          'schema' => [
            'type' => 'int(11)',
            'null' => 'NO',
            'key' => 'PRI',
            'default' => NULL,
            'extra' => 'auto_increment'
          ],
        ],
        $this->table_name => [
          'widget' => 'text',
          'required' => true,
          'schema' => self::varcharSchema(32),
          'autocomplete' => true,
        ],
      ];
    }

  }
  