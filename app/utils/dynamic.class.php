<?php

  namespace App\Utils;
  
  class Dynamic {
    private $values = [];
    
    function __construct($obj = null) {
      if(is_null($obj)) {
        return;
      }
      if(is_array($obj)) {
        $this->values = $obj;
      }
      else if(is_object($obj)) {
        foreach($obj as $key => $value) {
          $this->$key = $value;
        }
      }
    }
    
    function __get($key) {
      if(array_key_exists($key, $this->values)) {
        return $this->values[$key];
      }
      return null;
    }
    
    function __set($key, $value) {
      $this->values[$key] = $value;
    }
    
  }
