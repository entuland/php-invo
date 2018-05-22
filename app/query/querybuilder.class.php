<?php

  namespace App\Query;
  
  use App\Query\QueryRunner;
  use App\Utils\Format;
  use App\Utils\Msg;
  
  class QueryBuilder {
    private $type = 'SELECT';
    private $main_table = '';
    private $main_alias = '';
    private $columns = [];
    private $joins = [];
    private $orders = [];
    private $wheres = [];
    private $manual_wheres = [];
    private $operator = 'OR';
    private $limit = '';
    private $fetch_mode = \PDO::FETCH_COLUMN;
    private $aliases = [];
    
    static function test() {
      setTitle(t('query builder test'));
      $builder = new QueryBuilder('SELECT', 'item');
      
      for($i = 0; $i < 300; ++$i) {
        $alias = $builder->getTableAlias($i);
        Msg::warning("Alias for '$i' is '$alias'");
      }
      Format::dump($builder->aliases);
      
      $builder 
        ->addAllFieldsOf('item')
        ->addJoin('make', 'id', 'id_make')
        ->addField('make', 'make')
        ->addJoin('color', 'id', 'id_color')
        ->addField('color', 'color')
        ->addJoin('um', 'id', 'id_um')
        ->addField('um', 'um')
        ->addJoin('size', 'id', 'id_size')
        ->addField('size', 'size')
        ->setOperator('AND')
        ->addWhere('make', 'make', '=', ':make')
        ->addWhere('color', 'color', 'LIKE', ':color')
        ->addOrderBy('item', 'id', 'DESC')
        ->addOrderBy('color', 'color', 'DESC')
        ->addOrderBy('make', 'make', 'ASC')
        ->setLimit(1000000000, 0)
      ;
      
      $builder->dumpQuery();
      
      $bindings = [
        'color' => 'a%',
        'make' => 'mizuno',
      ];
      
      Format::dump($builder->countResults($bindings));
      
      $results = $builder->run($bindings);
      
      return var_export($results, true);
      
    }
    
    static function backtick($text) {
      return "`$text`";
    }
    
    function getTableAlias($table) {
      static $letters = false;
      static $count = 0;
      if(array_key_exists($table, $this->aliases)) {
        return $this->aliases[$table];
      }
      if(!$letters) {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $count = strlen($letters);
      }
      $index = 0;
      $position = 0;
      $alias = $letters{$index};
      while(in_array($alias, $this->aliases)) {
        ++$index;
        if($index >= $count) {
          $alias{$position} = $letters{0};
          $alias .= ' ';
          ++$position;
          $index = 0;
        }
        $alias{$position} = $letters{$index};
      }
      $this->aliases[$table] = $alias;
      return $alias;
    }
    
    function __construct($type, $main_table) {
      $this->type = $type;
      $this->main_table = self::backtick($main_table);
      $this->main_alias = $this->getTableAlias($main_table);
    }
    
    function addField($table_name, $table_column) {
      $this->columns[] = $this->getTableAlias($table_name) . '.' . self::backtick($table_column);
      return $this;
    }

    function addAllFieldsOf($table_name) {
      $this->columns[] = $this->getTableAlias($table_name) . '.*';
      return $this;
    }
    
    function addJoin(
      $joined_table, 
      $joined_table_column, 
      $main_table_column_to_equal) {
      // make sure we don't add multiple joins on the same table
      $this->joins[$joined_table] = (object)[
        'table' => self::backtick($joined_table),
        'on' => self::backtick($joined_table_column),
        'equals' => self::backtick($main_table_column_to_equal),
        'alias' => $this->getTableAlias($joined_table),
      ];
      return $this;
    }
    
    function addOrderBy($table_name, $table_column, $direction = 'ASC') {
      $this->orders[] = (object)[
        'alias' => $this->getTableAlias($table_name),
        'column' => self::backtick($table_column),
        'direction' => strtoupper($direction) !== 'ASC' ? 'DESC' : 'ASC',
      ];
      return $this;
    }
    
    function addWhere($table_name, $table_column, $operator, $compares) {
      $this->wheres[] = (object)[
        'alias' => $this->getTableAlias($table_name),  
        'column' => self::backtick($table_column),  
        'operator' => $operator,  
        'compares' => $compares,  
      ];
      return $this;
    }

    function addManualWhere($where_clause) {
      $this->manual_wheres[] = $where_clause;
      return $this;
    }
    
    function setOperator($operator) {
      $this->operator = $operator; 
      return $this;
    }
    
    function setLimit($count, $offset = 0) {
      if(!intval($count)) {
        return $this;
      }
      if($offset) {
        $this->limit = intval($offset) . ', ' . intval($count);
      } else {
        $this->limit = intval($count);
      }
      return $this;
    }
    
    private function compileForValues() {
      $lines = array_merge(
        $this->compileIntro()
        , $this->compileFields()
        , $this->compileFrom()
        , $this->compileJoins()
        , $this->compileWheres()
        , $this->compileOrder()
        , $this->compileLimit()
      );
      return implode(PHP_EOL, $lines);
    }
    
    private function compileForCount() {
      $lines = array_merge(
        $this->compileIntro()
        , $this->compileCount()
        , $this->compileFrom()
        , $this->compileJoins()
        , $this->compileWheres()
      );
      return implode(PHP_EOL, $lines);
    }

    function countResults($bindings = []) {
      $sql = $this->compileForCount();
      $test = QueryRunner::testQuery($sql, $bindings);
      if($test) {
        return QueryRunner::runCachedQuery($sql, $bindings, \PDO::FETCH_COLUMN)[0];
      }
    }
    
    function run($bindings = []) {
      $sql = $this->compileForValues();
      $test = QueryRunner::testQuery($sql, $bindings);
      if($test) {
        return QueryRunner::runCachedQuery($sql, $bindings, $this->fetch_mode);
      }
      return [];
    }
    
    private function compileIntro() {
      return [$this->type];
    }
    
    private function compileFields() {
      if(!$this->columns) {
        return ['    ' . $this->main_alias . '.`id`'];
      }
      $lines[] = "    " . implode(",\n    ", $this->columns);
      return $lines;
    }
    
    private function compileCount() {
      return ['COUNT(*)'];
    }
    
    private function compileFrom() {
      $lines = [];
      $lines[] = 'FROM';
      $lines[] = "    {$this->main_table} AS {$this->main_alias}";
      return $lines;
    }
    
    private function compileJoins() {
      $lines = [];
      foreach($this->joins as $join) {
        $lines[] = "JOIN";
        $lines[] = "    {$join->table} AS {$join->alias}";
        $lines[] = "ON";
        $lines[] = "    {$join->alias}.{$join->on} = {$this->main_alias}.{$join->equals}";
      }
      return $lines;
    }
    
    private function compileWheres() {
      if(!count($this->wheres) && !count($this->manual_wheres)) {
        return [];
      }
      $wheres = $this->manual_wheres;
      foreach($this->wheres as $where) {
        $wheres[] = "{$where->alias}.{$where->column} {$where->operator} {$where->compares}";
      }
      return [
        "WHERE\n    " 
        . implode("\n" . $this->operator . "\n    ", $wheres)
      ];
    }
    
    private function compileOrder() {
      if(!count($this->orders)) {
        return ["ORDER BY\n    ".$this->main_alias.".`id` DESC"];
      }
      $orders = [];
      foreach($this->orders as $order) {
        $orders[] = "    {$order->alias}.{$order->column} {$order->direction}";
      }
      return ["ORDER BY\n    " . implode(",\n", $orders)];
    }
    
    private function compileLimit() {
      if(!$this->limit) {
        return [];
      }
      return ["LIMIT {$this->limit}"];
    }
    
    function dumpQuery($bindings = []) {
      Msg::msg('<pre>' . $this->compileForValues() . '</pre>');
      if($bindings) {
        Msg::msg('<pre>' . var_export($bindings, true) . '</pre>');
      }
    }
    
  }
