<?php

  namespace App\Records;

  class Size extends Record  {

    function __construct($id = null) {
      $this->table_name = 'size';
      $this->values['id'] = $id;
      $this->reload();
    } 
  }
  
