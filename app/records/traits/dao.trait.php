<?php

  namespace App\Records\Traits;
  
  use App\Records\Factory;
  use App\Utils\Msg;
  use App\Utils\Field;
  use App\Pages\Schema;
  use App\Query\QueryRunner;
  
  trait DAO {
    protected $table_name = '';
    protected $column_name = '';
    protected $unique_column = '';
    protected $children = null;
    protected $children_count = null;
    public $disable_readonly_checks = false;
    
    protected $values = array();
        
    function __get($key) {
      if(array_key_exists($key, $this->values)) {
        return $this->values[$key];
      }
      else {
        throw new \Exception('Property [' . $key . '] not found in class [' . get_class($this) . ']');
      }
    }
    
    function __set($key, $value) {
      if(in_array($key, $this->fields())) {
        $settings = $this->settings()[$key];
        $field = new Field($settings);
        if($field->readonly && !$this->disable_readonly_checks) {
          throw new \Exception('Property [' . $key . '] is readonly in [' . get_class($this) . ']');          
        }
        else {
          $this->values[$key] = $value;          
        }
      }
      else {
        throw new \Exception('Property [' . $key . '] not found in class [' . get_class($this) . ']');
      }
    }

    function getForcedID($raw_value) {
      $value = trim($raw_value);
      if(!$value) {
        return 0;
      }
      $error_msg = t(
        'Unable to get an ID for value %s in %s',
        $value,
        t($this->className())
      );
      $column = $this->unique_column;
      if(!$column) {
        $column = $this->table_name;
      }
      $params = [
        $column => $value
      ];
      $result = $this->find($params)['ids'];
      if(count($result) > 1) {
        return 0;
      }
      else if(!count($result)) {
        $new = Factory::newInstance($this->className());
        $new->values[$column] = $value;
        $this->selfLog('auto-pre-create');
        if($new->save()) {
          $class = $new->className();
          $gender = Factory::classGender($class);
          $intros = [
            'male' => ct('male', 'Created new'),
            'female' => ct('female', 'Created new'),
          ];
          $intro = $intros[$gender];
          $msg = t('%s %s for value %s, ID %d', 
            $intro,
            t($new->className()), 
            safeMarkup($new->oneLinerDisplay()),
            $new->id
          );
          Msg::warning($msg);
          return $new->id;
        }
        throw new \Exception($error_msg . ' - ' . t('Error on save'));
      }
      return $result[0];
    }
    
    function filterSettings($settings) {
      $filtered = [];
      $table_name = $this->className();
      foreach($settings as $field_name => $set) {
        if(Schema::fieldExists($table_name, $field_name)) {
          $filtered[$field_name] = $set;
        }
      }
      return $filtered;
    }
    
    function delete() {
      if($this->id) {
        $sql = "
          DELETE FROM
            `{$this->table_name}`
          WHERE
            `id` = ?
          LIMIT 1";
        if(false === QueryRunner::runQuery($sql, [$this->id])) {
          return false;
        }
      }
      $this->postUpdate();
      return true;
    }
        
    function prepareSaveClauses($fields) {
      $clauses_array = [];
      foreach($fields as $field) {
        $clauses_array[] = "`$field` = :$field";
      }
      return implode(",\n            ", $clauses_array);
    }
    
    function prepareSaveValues($values) {
      $settings = $this->settings();
      foreach($values as $field_name => &$value) {
        $set = $settings[$field_name];
        if(array_key_exists('storagemultiplier', $set)) {
          $value *= $set['storagemultiplier'];
        }
      }
      return $values;
    }
    
    function prepareLoadValues($values) {
      $settings = $this->settings();
      foreach($values as $field_name => &$value) {
        $set = $settings[$field_name];
        if(array_key_exists('storagemultiplier', $set)) {
          $value /= $set['storagemultiplier'];
        }
      }
      return $values;
    }
    
    function save() {
      $fields = $this->fields();
      if($fields) {
        $flipped_fields = array_flip($fields);

        // field ID is readded separately
        unset($flipped_fields['id']);

        $fields = array_flip($flipped_fields);

        $clauses = $this->prepareSaveClauses($fields);

        $values = $this->values;
        if($this->id) {
          $sql = "
            UPDATE
              `{$this->table_name}`
            SET
              $clauses
            WHERE
              `id` = :id
            LIMIT 1;";
        }
        else {
          unset($values['id']);
          $sql = "
            INSERT INTO
              `{$this->table_name}`
            SET
              $clauses";
        }

        $prepared_values = $this->prepareSaveValues($values);
        if(false === QueryRunner::runQuery($sql, $prepared_values)) {
          return false;
        }
        if(!$this->id) {
          $this->values['id'] = QueryRunner::lastInsertID();
        }
        $this->postUpdate();
        return true;
      }
      return false;
    }
    
    function postUpdate() {
      QueryRunner::resetCaches();
      $this->resetUniqueValues();      
    }
    
    function reload() {
      $fields = implode('`, `', $this->fields());
      if(!$fields) {
        Msg::error(t('Records of type %s are currently not available, run database updates', $this->className()));
        return;
      }
      if($this->id) {
        $sql = "SELECT
                    `$fields`
                  FROM
                    `{$this->table_name}`
                  WHERE
                    `id` = ?
                  LIMIT 1;";
        $result = QueryRunner::runQuery($sql, [$this->id]);                    
        if(!is_array($result) || count($result) < 1) {
          $this->loadDefaults();
          return;
        }
        $prepared_values = $this->prepareLoadValues($result[0]);
        foreach($prepared_values as $key => $value) {
          $this->values[$key] = $value;
        }
        $this->values['id'] = intval($this->id);
      }
      else {
        $this->loadDefaults();
      }
    }
    
    function loadDefaults() {
      foreach($this->settings() as $key => $setting) {
        if(isset($setting['default'])) {
          $this->values[$key] = $setting['default'];
        }
        else {
          $this->values[$key] = null;
        }
      }
    }
    
  }