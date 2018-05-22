<?php

  namespace App\Query;
  
  use App\Utils\Database;
  use App\Utils\Format;
  use App\Utils\Msg;

  class QueryRunner {
    private static $db = false;
    private static $test_cache = [];
    private static $run_cache = [];
    
    static function resetCaches() {
      self::$test_cache = self::$run_cache = [];
    }
    
    static function init() {
      if(!self::$db) {
        self::$db = Database::getConnection();
      }
    }
    
    static function main() {
      self::init();
      Msg::warning(
        t('Careful, with this page you can destroy the database!')
        . ' ' . t('Make sure you perform a backup first')
        . ' <a class="button" href="'.config('publicbase').'/backup">' . t('backup') . '</a>'
      );
      setTitle(t('SQL runner'));
      return self::checkPost();
    }
    
    private static function checkPost() {
      $sql = '';
      $output = '';
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $action = getPOST('action');
        if($action === 'test' || $action == 'run') {
          $sql = getPOST('sql');
          $test_result = self::testQuery($sql);
          $output .= $test_result;
          if($test_result && $action === 'run') {
            $output .= self::confirmForm($sql);
          }
        }
        $query_id = getPOST('query_id');
        if($action === 'cancel') {
          $sql = $_SESSION['queries'][$query_id];
          Msg::msg(t('Run cancelled, no query was executed'));
        }
        if($action === 'confirm') {
          $sql = $_SESSION['queries'][$query_id];
          $rows = self::runQuery($sql);
          if(count($rows)) {
            $headers = array_keys($rows[0]);
            array_unshift($rows, $headers);
            $output = Format::tablarizeMatrix($rows);
          }
        }
      }
      return self::form($sql) . $output;
    }
    
    private static function errorInfo($info) {
      Msg::error(t('SQL state') . ': ' . $info[0]);
      Msg::error(t('Error code') . ': ' . $info[1]);
      Msg::error(t('Error message') . ': ' . $info[2]);
    }
    
    private static function showExplain($explain) {
      Ob_start();
      echo '<ul class="message warning">';
      foreach($explain as $key => $value) {
        echo '<li>' . $key . ': ' . $value . '</li>';
      }
      echo '</ul>';
      return Ob_get_clean();
    }
    
    static function testQuery($sql, $bindings = []) {
      $key = serialize([
        $sql,
        $bindings,
      ]);
      if(!array_key_exists($key, self::$test_cache)) {
        self::$test_cache[$key] = self::doTestQuery($sql, $bindings);
      }
      foreach(self::$test_cache[$key]['messages'] as $level => $messages) {
        foreach($messages as $text) {
          if($level === 'errorinfo') {
            self::errorInfo($text);
          } else {
            Msg::msg($text, $level);
          }
        }
      }
      return self::$test_cache[$key]['result'];
    }
    
    private static function doTestQuery($sql, $bindings = []) {
      self::init();
      $response = [
        'messages' => [],
        'result' => false,
      ];
      if(strpos($sql, ';') !== false) {
        $response['messages']['error'][] = t('Cannot execute multiple statements in a single query command');
        return $response;
      }
      $trimmed_sql = trim($sql);
      if(preg_match('#^SELECT|INSERT|UPDATE|DELETE|REPLACE#i', $trimmed_sql)) {
        $trimmed_sql = 'explain ' . $trimmed_sql;
      } else {
        $response['messages']['warning'][] = t('This kind of query cannot be tested');
        $response['result'] = true;
        return $response;
      }
      $stmt = self::$db->prepare($trimmed_sql); 
      if(!$stmt) {
        $response['messages']['errorinfo'][] = self::$db->errorInfo();
        return $response;
      }
      $success = $stmt->execute($bindings);
      if(!$success) {
        $response['messages']['errorinfo'][] = $stmt->errorInfo();
        return $response;
      }
      $result = $stmt->fetchAll($fetch_mode = \PDO::FETCH_ASSOC);
      $output = '';
      forEach($result as $explain) {
        $output .= self::showExplain($explain);
      }
      $response['result'] = Format::collapsible($output, t('Query details'));
      return $response;
    }
    
    static function runCachedQuery($sql, $bindings = [], $fetch_mode = \PDO::FETCH_ASSOC) {
      $key = serialize([
        $sql,
        $bindings,
        $fetch_mode,
      ]);
      if(!array_key_exists($key, self::$run_cache)) {
        self::$run_cache[$key] = self::doRunQuery($sql, $bindings, $fetch_mode);
      }
      self::displayMessages(self::$run_cache[$key]['messages']);
      if(self::$run_cache[$key]['success']) {
        return self::$run_cache[$key]['result'];
      }
      return false;
    }
    
    private static function displayMessages($messages) {
      foreach($messages as $level => $msgs) {
        foreach($msgs as $text) {
          if($level === 'errorinfo') {
            self::errorInfo($text);
          } else {
            Msg::msg($text, $level);
          }
        }
      }
    }
    
    static function runQuery($sql, $bindings = [], $fetch_mode = \PDO::FETCH_ASSOC) {
      $result = self::doRunQuery($sql, $bindings, $fetch_mode);
      self::displayMessages($result['messages']);
      if($result['success']) {
        return $result['result'];
      }
      return false;
    }
    
    static function lastInsertID() {
      if(self::$db) {
        return self::$db->lastInsertID();
      }
      return null;
    }
    
    private static function doRunQuery($sql, $bindings = [], $fetch_mode = \PDO::FETCH_ASSOC) {
      self::init();
      $response = [
        'success' => false,
        'messages' => [],
        'result' => [],
      ];
      \Debug::maybe(\Debug::QUERY, function() use($sql, $bindings) {
        Format::dump([
          'sql' => $sql,
          'binding' => $bindings,
        ]);
      });
      $stmt = self::$db->prepare($sql); 
      if(!$stmt) {
        $response['messages']['errorinfo'][] = self::$db->errorInfo();
        return $response;
      }
      $success = $stmt->execute($bindings);
      if(!$success) {
        $response['messages']['errorinfo'][] = $stmt->errorInfo();
        return $response;
      }
      $result = $stmt->fetchAll($fetch_mode);
      if(is_array($result) && count($result)) {
        $response['result'] = $result;
      }
      $response['success'] = true;
      return $response;
    }
    
    static function runQueryColumn($sql, $bindings = []) {
      return self::runQuery($sql, $bindings, \PDO::FETCH_COLUMN);
    }
    
    static function runCachedQueryColumn($sql, $bindings = []) {
      return self::runCachedQuery($sql, $bindings, \PDO::FETCH_COLUMN);
    }
    
    private static function confirmForm($sql) {
      $query_id = \UUID::v4();
      $_SESSION['queries'][$query_id] = $sql;
      Ob_start();
?>
<strong><?= t('Query about to be run') ?></strong>
<div class="sql"><?= safeMarkup($sql) ?></div>
<form method="POST" class="testquery">
  <input 
    type="hidden"
    name="query_id"
    value="<?= $query_id ?>">
  <button 
    class="button" 
    type="submit" 
    name="action" 
    value="confirm"
  ><?= t('Confirm, run it') ?></button>
  <button 
    class="button" 
    type="submit" 
    name="action" 
    value="cancel"
  ><?= t('Cancel') ?></button>
</form>
<?php
      return Ob_get_clean();
    }
    
    private static function form($sql) {
      Ob_start();
?>
<form method="POST" class="testquery">
  <textarea name="sql"><?= safeMarkup($sql) ?></textarea>
  <button 
    class="button" 
    type="submit" 
    name="action" 
    value="test"
  ><?= t('Test') ?></button>
  <button 
    class="button" 
    type="submit" 
    name="action" 
    value="run"
  ><?= t('Run') ?></button>
</form>
<?php
      return Ob_get_clean();
    }
    
  }


