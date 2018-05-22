<?php
  
  function config($key = null) {
    static $conf = null;
    if(is_null($conf)) {
      $conf = [
        'dbname' => 'inventory',
        'dbuser' => 'inventory',
        'dbpass' => file_get_contents('files/private/.ht_pass.dat'),
        'dbhandler'  => 'mysql',
        'dbhost' => 'localhost',
        'dbcharset' => 'utf8mb4',
        'dbcollate' => 'utf8mb4_unicode_520_ci',
        'sitename' => 'Inventory',
        'publicbase' => '/inventory',
        'locale' => '', // 'Italian_Italy.1252',
        'localecode' => 'en', // 'it_IT',
        'stockcompanyname' => 'stock',
        'stockcompanyid' => 1,
        'dailydocumentname' => 'daily',
        'fontfile' => realpath(__DIR__ . '/../res/fonts/opensans/OpenSans-Light.ttf')
      ];
    }
    if(is_string($key)) {
      if(array_key_exists($key, $conf)) {
        return $conf[$key];
      } else {
        throw new \Exception(t("Invalid key %s passed to config() function", $key));
      }
    }
    return $conf;
  }
  
