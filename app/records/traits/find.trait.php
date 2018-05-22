<?php

  declare(strict_types = 1);
  
  namespace App\Records\Traits;
  
  use App\Records\FindHelper;
  
  trait Find {
    
    function find(array $params = [], int $start = null, int $count = null, string $mode = 'OR'): array {
      $helper = new FindHelper($params, $start, $count, $mode, $this);
      return $helper->result();
    }
      
  }
