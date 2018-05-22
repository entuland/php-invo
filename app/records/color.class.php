<?php

  namespace App\Records;

  class Color extends Record  {
    
    function __construct($id = null) {
      $this->table_name = 'color';
      $this->values['id'] = $id;
      $this->reload();
    } 
  }
  
