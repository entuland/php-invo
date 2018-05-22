<?php

  namespace App\Records;

  class Company extends Record {
    
    function __construct($id = null) {
      $this->table_name = 'company';
      $this->column_name = 'name';
      $this->unique_column = 'name';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    function processEditPostRedirection($is_new) {
      $redirection = parent::processEditPostRedirection($is_new);
      if($is_new) {
        return "document/new?id_company=".$this->id;
      }
      return $redirection;
    }
    
    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'name' => [
          'widget' => 'textarea', 
          'required' => true,
          'schema' => self::varcharSchema(256),
        ],
        'fiscal' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(32),
        ],
        'street' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(64),
        ],
        'province' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(32),
        ],
        'zipcode' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(16),
        ],
        'place' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(64),
        ],
        'country' => [
          'widget' => 'text', 
          'schema' => self::varcharSchema(64),
        ],
        'phone' => [
          'widget' => 'textarea', 
          'schema' => self::varcharSchema(256),
        ],
        'fax' => [
          'widget' => 'textarea', 
          'schema' => self::varcharSchema(256),
        ],
        'email' => [
          'widget' => 'textarea', 
          'schema' => self::varcharSchema(256),
        ],
        'notes' => [
          'widget' => 'textarea', 
          'schema' => self::varcharSchema(256),
        ],
        'multiplier' => [
          'widget' => 'number',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
  
