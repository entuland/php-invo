<?php

  namespace App\Utils;
  
  class Database {
    private static $conn = NULL;

    private static function init() {
        try {
          self::$conn = new \PDO(
            config('dbhandler') . ':dbname=' . config('dbname') . ';host=' . config('dbhost') . ';charset=' . config('dbcharset'),
            config('dbuser'), 
            config('dbpass'), 
            array(
              \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . config('dbcharset') . ' COLLATE ' . config('dbcollate'),
            )
          );
        } 
        catch (\PDOException $e) {
          if (preg_match("/\b1045\b/", $e->getMessage())) {
            echo "SQLSTATE[HY000] [1045] Access denied for user 'name removed' @ '" . config('dbhost') . "' (using password: YES)";
          }
          else {
            echo $e->getMessage();  
          }
        }
    }

    public static function getConnection() {
      if (!self::$conn) { self::init(); }
      return self::$conn;
    }

  }