<?php

  namespace App\Records;

  class UM extends Record  {
  
    function __construct($id = null) {
      $this->table_name = 'um';
      $this->values['id'] = $id;
      $this->reload();
    } 
  }
  
