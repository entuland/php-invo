<?php

  namespace App\Pages;
  
  use App\Utils\Format;
  use App\Utils\Msg;
  use App\Query\QueryRunner;
  use App\Records\Factory;
  
  class Schema {
    private $current_schema = null;
    private $missing_tables = [];
    private $missing_fields = [];
    private $altered_fields = [];
    private $drop_fields = [];
    private $test_mode = false;
    private static $instance = null;
    
    static function main() {
      return self::getInstance()->overview(); 
    }
    
    static function fieldExists($table_name, $field_name) {
      static $cache = [];
      if(!isset($cache[$table_name][$field_name])) {
        $current_schema = self::getInstance()->getCurrentSchema();
        $cache[$table_name][$field_name] = isset($current_schema[$table_name][$field_name]);
      }
      return $cache[$table_name][$field_name];
    }
    
    static function tableExists($table_name) {
      static $cache = [];
      if(!isset($cache[$table_name])) {
        $current_schema = self::getInstance()->getCurrentSchema();
        $cache[$table_name] = isset($current_schema[$table_name]);
      }
      return $cache[$table_name];
    }
    
    static function getInstance() {
      if(!self::$instance) {
        self::$instance = new self();
      }
      return self::$instance;
    }
        
    function overview() {
      setTitle(t('Database Schema'));
      $this->sanityCheck();
      if($this->pendingQueueTasks()) {
        return $this->confirmQueueProcessing();
      }
    }
    
    private function runQuery($sql, $bindings = [], $fetch_mode = \PDO::FETCH_ASSOC) {
      if($this->test_mode) {
        return QueryRunner::testQuery($sql, $bindings);
      }
      else {
        return QueryRunner::runQuery($sql, $bindings, $fetch_mode);
      }
    }
    
    private function testQuery($sql, $bindings = []) {
      return QueryRunner::testQuery($sql, $bindings);
    }
    
    private function sanityCheck() {
      $default_schema = $this->getDefaultSchema();
      $current_schema = $this->getCurrentSchema();
      $comparison = $this->compareDatabaseSchemas($current_schema, $default_schema);
      // Format::dump($comparison);
    }
    
    private function compareDatabaseSchemas($current_db, $default_db) {
      $comparison = [];
      foreach($default_db as $default_table_name => $default_table) {
        $table_comparison = 'missing';
        if(!array_key_exists($default_table_name, $current_db)) {
          $this->queueTableCreate($default_table_name);
        } else {
          $current_table = $current_db[$default_table_name];
          $table_comparison = 
            $this->compareTableSchemas(
              $current_table, 
              $default_table, 
              $default_table_name
            );
        }
        $comparison[$default_table_name] = $table_comparison;
      }
      return $comparison;
    }
    
    private function compareTableSchemas($current_table, 
                                          $default_table, 
                                          $default_table_name
                                        ) {
      $comparison = [];
      foreach($default_table as $default_field_name => $default_field) {
        $field_comparison = 'missing';
        if(!array_key_exists($default_field_name, $current_table)) {
          $this->queueFieldCreate($default_table_name, $default_field_name);
        } else {
          $current_field = $current_table[$default_field_name];
          $field_comparison = $this->compareFieldSchemas(
            $current_field, 
            $default_field,
            $default_field_name,
            $default_table_name
          );
          if(strpos($default_field['type'], 'varchar') === 0) {
            $ideal = $this->computeMaxVarcharLength($default_table_name, $default_field_name);
//            Msg::warning(t(
//              'Max length of field `%s`.`%s` is %d',
//              $default_table_name,
//              $default_field_name,
//              $ideal
//            ));
          }
        }
        $comparison[$default_field_name] = $field_comparison;
      }
      foreach($current_table as $current_field_name => $current_field) {
        if(!array_key_exists($current_field_name, $default_table)) {
          $this->queueFieldDrop($default_table_name, $current_field_name);
          $comparison[$current_field_name] = 'drop';
        }
      }
      return $comparison;
    }
    
    private function compareFieldSchemas($current_field, 
                                          $default_field,
                                          $default_field_name,
                                          $default_table_name
                                        ) {
      static $properties = ['type', 'null', 'key', 'default', 'extra'];
      $pass = true;
      foreach($properties as $property) {
        if(!array_key_exists($property, $default_field)) {
          Msg::error(t(
            'Missing property %s in the default schema of `%s`.`%s`',
            $property,
            t($default_table_name),
            t($default_field_name)
          ));
          $pass = false;
        }
        if(!array_key_exists($property, $current_field)) {
          Msg::error(t(
            'Missing property %s in the current schema of `%s`.`%s`',
            $property,
            t($default_table_name),
            t($default_field_name)
          ));
          $pass = false;
        }
        if($pass) {
          $default_value = $default_field[$property];
          $current_value = $current_field[$property];
          if($current_value !== $default_value) {
            $pass = false;
          }
        }
      }
      if(!$pass) {
        $this->queueFieldAlter(
          $default_table_name,
          $default_field_name
        );
        return 'mismatch';
      }
      return 'ok';
    }
    
    private function computeMaxVarcharLength($table_name, $field_name) {
      $sql = "SELECT MAX(CHARACTER_LENGTH(`$field_name`)) FROM `$table_name`";
      return $this->runQuery($sql, [], \PDO::FETCH_COLUMN)[0];
    }
    
    private function getTables() {
      return $this->runQuery('show tables', [], \PDO::FETCH_COLUMN);
    }

    function getCurrentSchema() {
      if(!$this->current_schema) {
        $tables = $this->getTables();
        $this->current_schema = [];
        foreach($tables as $table) {
          $this->current_schema[$table] = $this->getTableSchema($table); 
        }
      }
      return $this->current_schema;
    }
    
    private function getDefaultSchema() {
      return Factory::getDefaultSchema();
    }
    
    private function getTableSchema($table) {
      $fields = [];
      foreach($this->runQuery("describe `$table`") as $fi) {
        $keys = array_keys($fi);
        foreach($keys as &$key) {
          $key = mb_strtolower($key);
        }
        $fi = array_combine($keys, $fi);
        $field_name = $fi['field'];
        unset($fi['field']);
        $fields[$field_name] = $fi;
      }
      return $fields;
    }
    
    private function queueTableCreate($table_name) {
      $this->missing_tables[] = $table_name;
    }
    
    private function queueFieldCreate($table_name, $field_name) {
      $this->missing_fields[$table_name][] = $field_name;
    }
    
    private function queueFieldAlter($table_name, $field_name) {
      $this->altered_fields[$table_name][] = $field_name;
    }
    
    private function queueFieldDrop($table_name, $field_name) {
      $this->drop_fields[$table_name][] = $field_name;
    }
    
    private function pendingQueueTasks() {
      return $this->missing_tables || $this->missing_fields || $this->altered_fields;
    }
    
    private function explainQueue() {
      $default_schema = $this->getDefaultSchema();
      $current_schema = $this->getCurrentSchema();
      foreach($this->missing_tables as $table_name) {
        Msg::raw(t('Pending creation of table %s', $table_name));
        // Msg::raw(t('Table schema') . ': ' . var_export54($default_schema[$table_name]));
      }
      foreach($this->missing_fields as $table_name => $fields) {
        foreach($fields as $field_name) {
          Msg::raw(t('Pending creation of field %s', $table_name . '.' . $field_name));
          // Msg::raw(t('Field schema') . ': ' . var_export54($default_schema[$table_name][$field_name]));
        }
      }
      foreach($this->altered_fields as $table_name => $fields) {
        foreach($fields as $field_name) {
          $this->checkFieldAlteration($table_name, $field_name);
          Msg::raw(t('Pending conversion of field %s', $table_name . '.' . $field_name));
          Msg::raw(t('Current field schema') . ': ' . var_export54($current_schema[$table_name][$field_name]));
          Msg::raw(t('Desired field schema') . ': ' . var_export54($default_schema[$table_name][$field_name]));
        }
      }
      foreach($this->drop_fields as $table_name => $fields) {
        foreach($fields as $field_name) {
          Msg::raw(t('Pending drop of field %s', $table_name . '.' . $field_name));
        }
      }
    }
    
    private function checkFieldAlteration($table_name, $field_name) {
      $default_schema = $this->getDefaultSchema();
      $default_field = $default_schema[$table_name][$field_name];
      if(strpos($default_field['type'], 'varchar') === 0) {
        $maxlen = $this->computeMaxVarcharLength($table_name, $field_name);
        $desired = $default_field['size'];
        if($maxlen > $desired) {
          Msg::error(t(
            'Data into `%s`.`%s` occupies at least %d character, you will truncate it'
            . ' if you reduce the size to %d',
            $table_name,
            $field_name,
            $maxlen,
            $desired
          ));
        }
      }
    }

    private function confirmQueueProcessing() {
      if(getPOST('action') === 'process-queue') {
        return $this->processQueue();
      }
      if(getPOST('action') === 'test-queue') {
        $this->test_mode = true;
        return $this->processQueue();
      }
      $this->explainQueue();
      ob_start();
?>
<form method="post">
  <button name="action" value="process-queue">
    <?= t('Execute pending database fixes') ?>
  </button>
  <button name="action" value="test-queue">
    <?= t('Test pending database fixes') ?>
  </button>
</form>
<?php
      return ob_get_clean();
    }
    
    private function processQueue() {
      $output = '';
      $output .= $this->processQueueMissingTables();
      $output .= $this->processQueueMissingFields();
      $output .= $this->processQueueAlteredFields();
      $output .= $this->processQueueDropFields();
      $output .= "<br>" . t("If you see a row of empty square brackets here above ('[ ]') the database should be ready, proceed to the homepage.");
      $output .= "<br>" . t("Errors about unavailable record types can be ignored in this page.");
      return $output;
    }
    
    private function processQueueAlteredFields() {
      $output = '';
      foreach($this->altered_fields as $table_name => $fields) {
        $output .= var_export54($this->alterFields($table_name, $fields));
      }
      return $output;
    }
    
    private function processQueueMissingFields() {
      $output = '';
      foreach($this->missing_fields as $table_name => $fields) {
        $output .= var_export54($this->createFields($table_name, $fields));
      }
      return $output;
    }
    
    private function processQueueDropFields() {
      $output = '';
      foreach($this->drop_fields as $table_name => $fields) {
        $output .= var_export54($this->dropFields($table_name, $fields));
      }
      return $output;
    }
    
    private function processQueueMissingTables() {
      $output = '';
      foreach($this->missing_tables as $table_name) {
        $output .= var_export54($this->createTable($table_name));
      }
      return $output;
    }
    
    private function alterFields($table_name, $fields) {
      $statements = [];
      foreach($fields as $field_name) {
        $statements[] = '`' . $field_name . '` ' . $this->compileFieldStatement($table_name, $field_name);
      }
      $compiled = implode(', ', Format::wrap('CHANGE ', $statements, ''));
      $sql = 'ALTER TABLE `' . $table_name . '` ' . $compiled;
      // Msg::warning($sql);
      return $this->runQuery($sql);
    }
    
    private function createFields($table_name, $fields) {
      $statements = [];
      foreach($fields as $field_name) {
        $statements[] = $this->compileFieldStatement($table_name, $field_name);
        $index = $this->compileIndexStatement($table_name, $field_name);
        if($index) {
          $statements[] = $index;
        }
      }
      $compiled = implode(', ', Format::wrap('ADD ', $statements, ''));
      $sql = 'ALTER TABLE `' . $table_name . '` ' . $compiled;
      // Msg::warning($sql);
      return $this->runQuery($sql);
    }
    
    private function dropFields($table_name, $fields) {
      $compiled = implode(', ', Format::wrap('DROP `', $fields, '`'));
      $sql = 'ALTER TABLE `' . $table_name . '` ' . $compiled;
      // Msg::warning($sql);
      return $this->runQuery($sql);
    }
    
    private function createTable($table_name) {
      $statements = array_merge(
        [], 
        $this->compileFieldStatements($table_name), 
        $this->compileIndexStatements($table_name)
      );
      $sql = "CREATE TABLE `$table_name` ("
            . implode(', ', $statements)
            . ") ENGINE=INNODB DEFAULT CHARSET=UTF8MB4 COLLATE=UTF8MB4_UNICODE_520_CI";
      // Msg::warning($sql);
      return $this->runQuery($sql);
    }
    
    private function compileFieldStatements($table_name) {
      $field_names = array_keys($this->getDefaultSchema()[$table_name]);
      $statements = [];
      foreach($field_names as $field_name) {
        $statements[] = $this->compileFieldStatement($table_name, $field_name);
      }
      return $statements;
    }
    
    private function compileFieldStatement($table_name, $field_name) {
      $field = (object)$this->getDefaultSchema()[$table_name][$field_name];
      $statement = [];
      $statement[] = '`' . $field_name . '`';
      $statement[] = $field->type;
      if(strpos($field->type, 'varchar') === 0) {
        $statement[] = 'COLLATE UTF8MB4_UNICODE_520_CI';
      }
      if($field->null === 'NO') {
        $statement[] = 'NOT NULL';
      }
      if($field->extra) {
        $statement[] = $field->extra;
      }
      return implode(' ' , $statement);
    }
    
    private function compileIndexStatements($table_name) {
      $field_names = array_keys($this->getDefaultSchema()[$table_name]);
      $statements = [];
      foreach($field_names as $field_name) {
        $statement = $this->compileIndexStatement($table_name, $field_name);
        if($statement) {
          $statements[] = $statement;
        }
      }
      return $statements;
    }

    private function compileIndexStatement($table_name, $field_name) {
      $field = (object)$this->getDefaultSchema()[$table_name][$field_name];
      switch($field->key) {
        case 'PRI':
          return 'PRIMARY KEY (`' . $field_name . '`)';
        case 'MUL':
          return 'KEY (`' . $field_name . '`)';
      }
      return '';
    }
  }

