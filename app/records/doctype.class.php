<?php

  namespace App\Records;

  class DocType extends Record  {
    
    function __construct($id = null) {
      $this->table_name = 'doctype';
      $this->column_name = 'description';
      $this->unique_column = 'description';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    function processEditPostRedirection($is_new) {
      $redirection = parent::processEditPostRedirection($is_new);
      if($is_new) {
        return "document/new?id_doctype=".$this->id;
      }
      return $redirection;
    }
    
    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'abbr' => [
          'widget' => 'text', 
          'required' => true,
          'schema' => self::varcharSchema(4),
          'autocomplete' => true,
        ],
        'description' => [
          'widget' => 'text',
          'required' => true,
          'schema' => self::varcharSchema(32),
          'autocomplete' => true,
        ],
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
  
