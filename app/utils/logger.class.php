<?php

  namespace App\Utils;
  
  use App\Utils\Settings;
  use \App\Utils\Format;
  
  // todo implement cron facilities
  // todo implement forking to command line scripting
  
  class Logger {
    const LOG_FOLDER = 'files/logs';

    static function main() {
      setTitle(t('Log'));
      self::deleteOldLogs();
      return Logger::htmlReport();
    }

    private static function deleteOldLogs() {
      $settings = new Settings();
      $daylimit = $settings->maxLogAgeInDays;
      $subfolders = getSubfolders(self::LOG_FOLDER);
      if(intval($daylimit) <= 0) {
        Msg::error(t('Invalid age limit for log deletion: %s', $daylimit));
        return;
      }
      $oldest_allowed = strtotime('-' . $daylimit . ' days');
      $deletable = [];
      foreach($subfolders as $subfolder) {
        $folder = str_replace(self::LOG_FOLDER . '/', '', $subfolder);
        $timestamp = strtotime($folder);
        if($timestamp < $oldest_allowed) {
          $deletable[] = $subfolder;
        }
      }
      foreach($deletable as $del) {
        rrmdir($del);
        Msg::warning(t('Folder %s deleted for exceeding max age', $del));
      }
    }
    
    private static function sanityCheck() {
      ensureFolder(self::LOG_FOLDER);
    }
        
    public static function log($object, $class = 'none', $action = 'none', $id = 'none') {
      self::sanityCheck();
      list($milli, $seconds) = explode(' ', microtime());
      $date = date('Y-m-d', $seconds);
      $time = date('H-i-s', $seconds) . substr($milli, 1);
      $folder = self::LOG_FOLDER . '/' . $date;
      $folder = normalizePath($folder);
      ensureFolder($folder);
      $id_string = $class . '_' . $action . '_#' . $id;
      $filename = $folder . '/h' . $time . '_' . $id_string;
      $json = json_encode($object, JSON_PRETTY_PRINT);
      if(false === file_put_contents($filename . '.json', $json)) {
        Format::dump($object, $id_string);
        throw new \Exception(t('Unable to store log file, see data above'));
      }
    }

    private static function logRow($filename) {
      $log = self::parseFilename($filename);
      $log['class'] = t($log['class']);
      $log['action'] = t($log['action']);
      $log['logfile'] = '<a class="button" href="?log=' . urlencode($log['logfile']) . '">' . t('Display') . '</a>';
      return "<tr>" . implode(Format::wrap('<td>', $log, '</td>')) . '</tr>';
    }

    private static function parseFilename($filename) {
      $norm_filename = normalizePath($filename);
      $cut_filename = str_replace(self::LOG_FOLDER . '/', '', $norm_filename);
      $parts = explode('/', $cut_filename);
      $date = $parts[0];
      $name = $parts[1];
      $name_parts = explode('_', $name);
      $time = str_replace(['h', '-'], ['', ':'], $name_parts[0]);
      $class = $name_parts[1];
      $action = $name_parts[2];
      $id = str_replace(['.json', '#'], '', $name_parts[3]);
      
      $log = [
        'date' => "$date $time",
        'class' => $class,
        'action' => $action,
        'id' => $id,
        'logfile' => $filename,
      ];
      return $log;
    }
    
    private static function getList() {
      $files = glob_recursive(self::LOG_FOLDER . '/*.json');
      if(!count($files)) {
        Msg::msg(t('Log folder is empty'));
        return;
      }
      $files = array_reverse($files);
      Ob_start();
?>
<table>
  <thead>
    <tr>
      <th><?= t('Date') ?></th>
      <th><?= t('Type') ?></th>
      <th><?= t('Action') ?></th>
      <th><?= t('ID') ?></th>
      <th><?= t('Link') ?></th>
    </tr>
  </thead>
  <tbody>
  <?php
      foreach($files as $file) {
        echo self::logRow($file);
      }
  ?>
  </tbody>
</table>
<?php
      return Ob_get_clean();
    }
    
    private static function echoPairRow($value, $key) {
      $key = t($key);
      if(is_object($value)) {
        $keysAsHeaders = true;
        $value = (array)$value;
        if(!implode($value)) {
          return;
        }
        Ob_start();
        self::echoTable((array)$value, '', $keysAsHeaders);
        $value = Ob_get_clean();
      }
      else {
        $value = safeMarkup($value);
      }
      echo <<<HTML
    <tr>
      <th>$key</th>
      <td>$value</td>
    </tr>
HTML;
    }

    private static function echoTable($array, $caption = '', $keysAsHeaders = false) {
    if($caption) {
      $caption = "<caption>$caption</caption>";
    }
    $headers = [
      'key',
      'value',
    ];
    if($keysAsHeaders) {
      $headers = array_keys($array);
    }
    $headers = array_map('t', $headers);
    $headers = implode(Format::wrap('<th>', $headers, '</th>'));
?>
<table>
    <?= $caption ?>
  <thead>
    <tr>
      <?= $headers ?>
    </tr>
  </thead>    
  <tbody>
  <?php
      if($keysAsHeaders) {
        $array = array_map(function($el) {
          return safeMarkup($el);
        }, $array);
        echo '<tr>' . implode(Format::wrap('<td style="width: 50%">', $array, '</td>')) . '</tr>';
      }
      else {
        array_walk($array, 'self::echoPairRow');
      }
  ?>
  </tbody>
</table>
<?php
    }
    
    private static function getDetails($filename) {
      $contents = file_get_contents($filename);
      $fields = (array)json_decode($contents);
      $log = self::parseFilename($filename);
      Ob_start();
      
      $class = $log['class'];
      
      $log['class'] = t($class);
      $log['action'] = t($log['action']);
            
      self::echoTable($log);
      self::echoTable($fields, t('Fields'));
            
      return Ob_get_clean();
    }
    
    public static function htmlReport() {
      self::sanityCheck();
      $filename = filter_input(INPUT_GET, 'log', FILTER_SANITIZE_URL);
      if($filename && file_exists($filename)) {
        return self::getDetails($filename);
      }
      return self::getList();
    }
    
  }

