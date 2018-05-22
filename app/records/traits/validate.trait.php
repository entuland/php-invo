<?php

  namespace App\Records\Traits;
  
  use App\Records\Record;
  use App\Utils\Msg;
  use App\Utils\Field;
  
  trait Validate {
        
    function validateNewValues($values, $full_check = true) {
      $new_values = [];
      $errors = [];
      foreach($this->settings() as $field_name => $settings) {
        if($field_name === 'id') {
          continue;
        }
        if(!array_key_exists($field_name, $values) && !$full_check) {
          continue;
        }
        $value = $values[$field_name] ?? null;
        $field = new Field($settings, $value, $this->className(), $field_name);
        if(!$field->valid()) {
          $errors[$field->name] = true;
        }
        else {
          $new_value = $field->storageValue();
          if(is_string($new_value)) {
            $new_values[$field->name] = mb_strtoupper($new_value);
          } else {
            $new_values[$field->name] = $new_value;            
          }
        }
      }
      if(count($errors)) {
        $failures = implode(', ', array_map('t', array_keys($errors)));
        Msg::error(t('The following fields are required or invalid: %s', $failures));
        return false;
      }
      if(!$this->anythingNew($new_values)) {
        Msg::error(t('Nothing to change in %s',
          $this->identifier(Record::ADD_LINK)));
        return false;
      }
      return $new_values;
    } 
    
    function validateUniqueColumn($new_values) {
      $uniqueColumn = $this->uniqueColumn();
      if(array_key_exists($uniqueColumn, $new_values)) {
        $value = trim(mb_strtoupper($new_values[$uniqueColumn]));
        $strict_match = true;
        $uniqueID = array_search($value, $this->uniqueValues(), $strict_match);
        if($value 
              && $uniqueID !== false
              && $this->id !== intval($uniqueID)
            ) {
          $class = $this->className();
          $translated_class = t($class);
          $base = config('publicbase');
          $link = <<<HTML
            <strong><a class="button" href="$base/$class/$uniqueID">$translated_class $value</a></strong>
HTML;
          Msg::error(
            t('Duplicated value found')
            . $link
          );
          return false;
        }
      }
      return true;
    }
    
    function validateEditId($id) {
      if($this->id != $id) {
        Msg::error(t('Error during edit of %s, passed ID %d does not match', 
          $this->identifier(),
          $id
        ));
        return false;
      }
      return true;
    }
    
  }
