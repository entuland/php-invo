<?php

  namespace App\Records\Traits;
  
  use App\Query\QueryRunner;
  
  trait Unique {
        
    function resetUniqueValues() {
      unset($_SESSION['unique_values'][$this->className()]);
      unset($_SESSION['unique_values_timestamp'][$this->className()]);
    }
    
    function uniqueColumn() {
      if($this->unique_column) {
        return $this->unique_column;
      }
      return $this->table_name;
    }
    
    function uniqueValuesTimestamp($column) {
      $class = $this->className();
      if(!in_array($column, $this->fields())) {
        return false;
      }
      if(isset($_SESSION['unique_values_timestamp'][$class][$column])) {
        return $_SESSION['unique_values_timestamp'][$class][$column];
      }
      return false;
    }
    
    function uniqueValues($column = null) {
      $class = $this->className();
      if(is_null($column)) {
        $column = $this->uniqueColumn();
      }
      if(!in_array($column, $this->fields())) {
        return false;
      }
      if(!isset($_SESSION['unique_values'][$class][$column])) {
        $sql = "
          SELECT
            `id`, TRIM(UPPER(`$column`)) as `$column`
          FROM
            `{$this->table_name}`
          GROUP BY
            TRIM(UPPER(`$column`))
        ";
        $result = QueryRunner::runQuery($sql, [], \PDO::FETCH_KEY_PAIR);
        if(false === $result) {
          return [];
        }
        $_SESSION['unique_values'][$class][$column] = $result;
        $_SESSION['unique_values_timestamp'][$class][$column] = time();
      }
      return $_SESSION['unique_values'][$class][$column];
    }
    
  }