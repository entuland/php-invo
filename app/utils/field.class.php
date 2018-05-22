<?php

  namespace App\Utils;
  
  use App\Records\Factory;
  use App\Records\Record;
  
  class Field {
    const ADD_LINK = true;
    const OMIT_LINK = false;
    const ADD_PREFIX = true;
    const OMIT_PREFIX = false;
    
    private $properties;
    private $_storage_value = null;
    private $_recalculate_storage_value = true;
    
    static private $defaults = [
      'class' => '',
      'prefix' => '',
      'postfix' => '',
      'widget' => 'text',
      'name' => null,
      'value' => null,
      'default' => null,
      'default_mode' => null,
      'reference' => null,
      'step' => null,
      'min' => null,
      'max' => null,
      'maxlength' => null,
      'readonly' => false,
      'autocomplete' => false,
      'autolimit' => 1,
      'required' => false,
      'disabled' => false,
      'keep_visible' => false,
      'add_select_option' => false,
      'storagemultiplier' => false,
      'options' => [],
      'datalist' => [],
      'schema' => [],
    ];
    
    function __construct($settings = [], $value = null, $class = '', $name = '') {
      $this->properties = $settings;
      $this->name = $name;
      $this->class = $class;
      $this->value = $value;
    }
    
    function __get($prop_name) {
      $prop = $this->properties;
      if(array_key_exists($prop_name, self::$defaults)) {
        if(isset($prop[$prop_name])) {
          return $prop[$prop_name];
        }
        return self::$defaults[$prop_name];
      }
      Msg::error(t('Field property %s not found', $prop_name));
      return null;
    }
    
    function __set($prop_name, $prop_value) {
      if(array_key_exists($prop_name, self::$defaults)) {
        if($prop_name === 'value') {
          if($this->widget === 'number') {
            $prop_value = str_replace(',', '.', $prop_value);
          }
          $this->_recalculate_storage_value = true;
        }
        $this->properties[$prop_name] = $prop_value;
      }
      else {
        Msg::error(t('Field property %s not found', $prop_name));
      }
    }
    
    function valid() {
      if($this->required && !$this->value) {
        return false;
      }
      if(!is_null($this->min) && floatval($this->value) < $this->min) {
        return false;
      }
      return true;
    }
    
    function storageValue() {
      if($this->_recalculate_storage_value) {
        $value = $this->value;
        if($this->widget === 'datetime-local') {
          $value{10} = ' ';
        }
        if($this->widget === 'date') {
          $value = substr($value, 0, 10);
        }
        $this->_storage_value = $value;
        $this->_recalculate_storage_value = false;
      }
      return $this->_storage_value;
    }
    
    function displayValue($add_link = true, $add_prefix = false, $context = Record::DISPLAY_CONTEXT) {
      unused($context);
      $skip_encoding = false;
      $result = $this->value;
      if($this->widget === 'datetime-local') {
        if(intval(preg_replace('#\D#', '', $this->value))) {
          $result = Factory::formatDateTimeDisplay($this->value);
        } else {
          $result = '';
        }
      } 
      else if($this->widget === 'date') {
        if(intval(preg_replace('#\D#', '', $this->value))) {
          $result = Factory::formatDateDisplay($this->value);
        } else {
          $result = '';
        }
      } 
      else if($this->widget === 'select' && $this->options) {
        if(isset($this->options[$this->value])) {
          $result = $this->options[$this->value];
        }
      } 
      else if($this->reference) {
        $id = preg_replace("#\D#", '', $this->value);
        $instance = Factory::newInstance($this->reference, $id);
        if($instance->id) {
          $result = $instance->oneLinerDisplay($add_link);
          $skip_encoding = true;
        } else {
          $result = '';
        }
      }
            
      if($this->value && $this->name === 'category' && $add_link) {
        $category = \App\Records\Category::byName($this->value);
        $result = $category->wrapInEditUrl($result);
        $skip_encoding = true;
      }
      
      if(!$skip_encoding) {
        $result = safeMarkup($result);
      }
            
      if($result && $add_prefix) {
        $result = $this->prefix . $result . $this->postfix;
      }
      
      if(!$result) {
        $result = '';
      }

      return $result;
    }
    
    function formatAsTableRow() {
      Ob_start();
?>
  <tr class="field-row field-<?= $this->name ?>">
    <th class="title"><?= t($this->name) ?></th>
    <td class="value"><?= $this->prefix ?><?= $this->displayValue() ?><?= $this->postfix ?></td>
  </tr>
<?php
      return Ob_get_clean();    
     
    }

    private static function numericSearchTooltip() {
      return <<<HTML
  <span class="input-prefix tooltip operators-tooltip">?</span>
HTML;
    }
    
    private function prepareNumberWidget($search_context = false) {
      if($search_context) {
        $this->widget = 'text';
        $this->postfix .= self::numericSearchTooltip();
      }
      else {
        if(is_null($this->step)) {
          $this->step = '0.01';
        }
      }
    }
    
    private function prepareSelectWidget($search_context = false) {
      if($search_context && !$this->required) {
        $this->widget = 'text';
      }
    }
    
    private function prepareTextWidget($search_context = false) {
      if($this->reference && !$this->readonly && !$search_context) {
        $id = $this->value;
        if(!$id) {
          $id = null;
        }
        $instance = Factory::newInstance($this->reference, $id);
        if($instance->id) {
          $this->value = $instance->{$instance->uniqueColumn()};
        } else {
          $this->value = '';
        }
        if(isset($instance->settings()[$instance->uniqueColumn()]['schema']['size'])) {
          $this->maxlength = $instance->settings()[$instance->uniqueColumn()]['schema']['size'];
        }
      }
      $this->value = safeMarkup($this->value);
      if($this->autocomplete) {
        $settings = new Settings();
        $class = $this->class;
        $field = $this->name;
        if($this->reference) {
          $instance = Factory::newInstance($this->reference);
          $class = $instance->className();
          $field = $instance->uniqueColumn();
        }
        if($this->name === 'category') {
          $class = 'category';
        }
        $settings->registerAutocomplete($class, $field);
        $this->autocomplete = (object)[
          'class' => $class,
          'field' => $field,
        ];
      }
    }
    
    private function prepareTextareaWidget($search_context = false) {
      if($search_context) {
        $this->widget = 'text';
      }
      $this->value = safeMarkup($this->value);
    }
    
    private function prepareDateTimeLocalWidget($search_context = false) {
      if($this->name === 'modified' && !$search_context) {
        $this->value = $this->default;
      }
      if($search_context) {
        $this->widget = 'text';
      }
      else {
        if(strlen($this->value)) {
          $pass = $this->value;
          $pass{10} = 'T';
          $this->value = $pass;
        }
        $this->step = 1;
      }
      if(!intval(preg_replace('#\D#', '', $this->value))) {
        $this->value = '';
      }
    }
    
    private function prepareDateWidget($search_context = false) {
      if($search_context) {
        $this->widget = 'text';
      }
      $this->value = substr($this->value, 0, 10);
      if(!intval(preg_replace('#\D#', '', $this->value))) {
        $this->value = '';
      }
    }
    
    private function prepareWidget($search_context = false) {
      $method = 'prepare' . str_replace('-', '', $this->widget) . 'widget';
      if(method_exists($this, $method)) {
        return $this->$method($search_context);
      }
      Msg::error(t('Widget type %s not implemented', $this->widget));
    }
    
    private function debug() {
      if(filter_input(INPUT_GET, 'debug')) {
        $temp_options = $this->options;
        $temp_datalist = $this->datalist;
        $this->options = 'truncated: ' . serialize(array_slice($this->options, 0, 10));
        $this->datalist = 'truncated: ' . serialize(array_slice($this->datalist, 0, 10));
        Format::dump($this);
        $this->options = $temp_options;
        $this->datalist = $temp_datalist;
      }
    }
    
    private function selectMarkup($search_context = false) {
      $default_mode = $this->default_mode ? $this->default_mode : 'value';
      $options = $this->options;
      $missing_option = !array_key_exists('', $options);
      $allowed_or_asked = !$this->required || $this->add_select_option;
      if($missing_option && $allowed_or_asked) {
        $options = ['' => t('-- select --')] + $options;
      }
      $attributes = [];
      if($this->disabled) {
        $attributes['disabled'] = true;
      }
      if($this->required && !$search_context) {
        $attributes['required'] = true;
      }
      return Format::select(
        $options, 
        $this->value, 
        $this->name, 
        $default_mode,
        $attributes
      );
    }
    
    private function otherMarkup($search_context = false) {
      $required_attr = $this->required && !$search_context ? 'required' : '';
      $readonly_attr = $this->readonly && !$search_context ? 'readonly' : '';
      $step_attr = is_null($this->step) ?  '' : 'step="' . $this->step . '"';
      $min_attr = is_null($this->min) ? '' : 'min="' . $this->min . '"';
      $max_attr = is_null($this->max) ? '' : 'max="' . $this->max . '"';
      $disabled_attr = $this->disabled ? 'disabled' : '';
      if($this->widget === 'number' && !floatval($this->value)) {
        $this->value = '';
      }
      $list_attr = '';
      $list = '';
      if($this->options) {
        $list = Format::datalist($this->options, $this->name . '-datalist');
        $list_attr = "list='{$this->name}-datalist'";
      }
      $maxlength_attr = '';
      if($this->maxlength) {
        $maxlength_attr = ' maxlength="' . $this->maxlength . '"';
      }
      if(isset($this->schema['size'])) {
        $maxlength_attr = ' maxlength="' . $this->schema['size'] . '"';
      }
      
      $autocomplete = '';
      if($this->autocomplete) {
        $autocomplete = 
          'data-autocomplete="' 
          . $autocomplete
          . htmlspecialchars(json_encode($this->autocomplete)) . '"';
      }
      
      return <<<HTML
<input 
  type="{$this->widget}"
  name="{$this->name}"
  value="{$this->value}"
  $autocomplete
  $list_attr
  $disabled_attr
  $readonly_attr
  $required_attr
  $step_attr
  $min_attr
  $max_attr
  $maxlength_attr
>$list
HTML;
    }
    
    private function textareaMarkup($search_context = false) {
      $required_attr = $this->required && !$search_context ? 'required' : '';
      $readonly_attr = $this->readonly && !$search_context ? 'readonly' : '';
      $disabled_attr = $this->disabled ? 'disabled' : '';
      $maxlength_attr = '';
      if($this->maxlength) {
        $maxlength_attr = ' maxlength="' . $this->maxlength . '"';
      }
      if(isset($this->schema['size'])) {
        $maxlength_attr = ' maxlength="' . $this->schema['size'] . '"';
      }
      return <<<HTML
<textarea 
  name="{$this->name}"
  autocomplete="off"
  $disabled_attr
  $readonly_attr
  $required_attr
  $maxlength_attr
>{$this->value}</textarea>
HTML;
    }
    
    private function markup($search_context = false) {
      $this->debug();
      if($this->widget === 'select') {
        return $this->selectMarkup($search_context);
      }
      if($this->widget === 'textarea') {
        return $this->textareaMarkup($search_context);
      }
      return $this->otherMarkup($search_context);
    }
    
    function formatAsFormRow($context = Record::EDIT_CONTEXT) {
      $post_value = getPOST($this->name);
      if(!is_null($post_value)) {
        $this->value = $post_value;
      }
      if($this->readonly && !$this->keep_visible) {
        $this->widget = 'hidden';
      }
      if($this->widget === 'hidden') {
        return $this->markup();
      }
      $list_context = $context === Record::LIST_CONTEXT;
      $search_context = $context === Record::SEARCH_CONTEXT;
      $this->prepareWidget($search_context);
      $title_markup = t($this->name) . ' ' . $this->prefix . $this->postfix;
      if($list_context) {
        $title_markup = <<<HTML
          <label>
            <input
              class="list-toggle"
              type="checkbox" 
              name="list-enabled[{$this->name}]"
              data-controlled-field="{$this->name}"
            >
            $title_markup
          </label>
HTML;
      }
      Ob_start();
?>
  <tr class="field-<?= $this->name ?>">
    <th class="title"><?= $title_markup ?></th>
    <td class="value"><?= $this->markup($search_context) ?></td>
  </tr>
<?php
      return Ob_get_clean();
    }
    
  }
