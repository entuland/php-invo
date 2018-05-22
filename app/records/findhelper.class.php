<?php
  declare(strict_types = 1);
  
  namespace App\Records;
  
  use App\Records\Factory;
  use App\Utils\Dynamic;
  use App\Records\Record;
  use App\Query\QueryBuilder;
  
  class FindHelper {
    private $params = [];
    private $start = null;
    private $count = null;
    private $mode = 'OR';
    private $table = '';
    private $builder = false;
    private $instances = [];
    private $orderby = false;
    private $bindings = [];
    private $record = false;

    function __construct(array $params = [], 
                         int $start = null, 
                         int $count = null, 
                         string $mode = 'OR',
                         Record $record = null) {
      if(!$record || !Factory::validClass($record->tableName())) {
        throw new \Exception(t('Invalid table name %s passed to FindHelper', $record->tableName()));
      }
      $this->table = $record->tableName();
      $this->record = $record;
      
      if(isset($params['matching'])) {
        $mode = strtoupper($params['matching']);
        unset($params['matching']);
      }
      else {
        $mode = 'OR';
      }
      if($mode !== 'OR') {
        $mode = 'AND';
      }
      $this->params = $params;
      $this->start = $start;
      $this->count = $count;
      $this->mode = $mode;
    }
    
    function result(): array {
      $this->prepareBuilder();
      if(\Debug::track(\Debug::FIND)) {
        $this->builder->dumpQuery($this->bindings);
      }
      return [
        'ids' => $this->builder->run($this->bindings),
        'count' => $this->builder->countResults($this->bindings),
      ];
    }
    
    private function prepareBuilder() {
      $this->builder = new QueryBuilder('SELECT', $this->table);
      $this->builder->setLimit($this->count, $this->start);
      $this->builder->setOperator($this->mode);
      $this->addOrderBy();
    }

    private function addOrderBy() {
      $orderby = getGET('orderby');
      if($orderby) {
        $orderby = mb_strtolower($orderby);
        if(substr($orderby, 0, 3) === 'id_') {
          $orderby = substr($orderby, 3);
        }
      }
      $direction = getGET('direction');
      if(in_array($orderby, $this->record->fields())) {
        $this->builder->addOrderBy($this->table, $orderby, $direction);
      }
      $this->orderby = $orderby;
      $this->direction = $direction;
      $this->addMainJoins();
    }
    
    private function addMainJoins() {      
      $main_table_column_to_equal = $this->record->reference($this->orderby);
      if($main_table_column_to_equal) {
        $joined_table = $this->orderby;
        $joined_table_column = 'id';
        $this->builder->addJoin(
          $joined_table, 
          $joined_table_column, 
          $main_table_column_to_equal 
        );
        if(!Factory::validClass($joined_table)) {
          throw new \Exception(t('Invalid record type %s during query building', $joined_table));
        }
        if(!isset($this->instances[$joined_table])) {
          $this->instances[$joined_table] = Factory::newInstance($joined_table);
        }
        $table_column = $this->instances[$joined_table]->uniqueColumn();
        $this->builder->addOrderBy($joined_table, $table_column, $this->direction);
      }
      $this->prepareBindings();
    }
    
    private function prepareBindings() {
      $this->bindings = [];
      foreach($this->params as $column => $param) {
        if(is_array($param)) {
          $this->bindings[$column] = $param['value'];
        }
        else {
          $this->bindings[$column] = $param;
        }
      }
      $this->processParams();
    }
    
    private function processParams() {
      foreach($this->params as $column => $param) {
        if(!is_array($param)) {
          $param = ['value' => $param];
        }
        $dynamic = new Dynamic($param);
        $dynamic->column = $column;
        $this->processParam($dynamic); 
      }
    }
    
    private function processParam(Dynamic $param) {
      if(!$param->operator) {
        $param->operator = '=';
        if(is_string($param->value) && strpos($param->value, '%') !== false) {
          $param->operator = 'LIKE';
        } 
      }
      if($this->record->searchContext() && substr($param->column, 0, 3) === 'id_') {
        $this->processReferenceParam($param);
      } else {
        $this->processNormalParam($param);
      }
    }

    private function processReferenceParam(Dynamic $param) {
      $joined_table = substr($param->column, 3);
      if(!Factory::validClass($joined_table)) {
        throw new \Exception(t('Invalid record type %s during query building', $joined_table));
      }
      if(!isset($this->instances[$joined_table])) {
        $this->instances[$joined_table] = Factory::newInstance($joined_table);
      }
      $joined_table_column = 'id';
      $main_table_column_to_equal = $param->column;
      $this->builder->addJoin(
        $joined_table, 
        $joined_table_column, 
        $main_table_column_to_equal
      );
      $table_name = $joined_table;
      $table_column = $this->instances[$joined_table]->uniqueColumn();
      $operator = 'LIKE';
      $this->builder->addWhere($table_name, $table_column, $operator, ':' . $param->column);
      if(strpos($this->bindings[$param->column], '%') === false) {
        $this->bindings[$param->column] = "%{$this->bindings[$param->column]}%";
      }
    }
    
    private function processNormalParam(Dynamic $param) {
      if(!$this->record->validColumn($param->column)) {
        throw new \Exception(t('Column %s not found during query building', $param->column));
      }
      $table_and_column = $this->builder->getTableAlias($this->table)
                          . '.'
                          . QueryBuilder::backtick($param->column);
      if($param->wrapper) {
        $table_and_column = sprintf($param->wrapper, $table_and_column);
      }
      $this->builder->addManualWhere("$table_and_column {$param->operator} :{$param->column}"); 
    }
    
  }

