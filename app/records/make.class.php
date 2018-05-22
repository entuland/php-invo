<?php

  namespace App\Records;

  class Make extends Record  {

    function __construct($id = null) {
      $this->table_name = 'make';
      $this->values['id'] = $id;
      $this->reload();
    } 
  }
  
