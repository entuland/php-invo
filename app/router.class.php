<?php

  namespace App;
  
  use App\Records\Factory;
  use App\Utils\User;
  use App\Utils\Format;
  use App\Utils\Msg;
    
  class Router {
    private static $latestRoute = '';
    private static $routes = [];
    private static $initialized = false;
    
    public static function redirect($path = '') {
      if($path === '') {
        $path = config('publicbase');
      }
      header('location: ' . $path);
      die();
    }
    
    public static function latestRoute() {
      return self::$latestRoute;
    }
    
    public static function route($path) {
      self::init();
      if(!$path) {
        $path = 'home';
      }
      
      self::$latestRoute = $path;
      
      $parts = explode('/', $path);
      $route = $parts[0];
      
      if(!User::validUser() && $route !== 'user') {
        Router::redirect(config('publicbase') . '/user');
      } 
      
      if(array_key_exists($route, self::$routes)) {
        return self::$routes[$route]();
      } 
      else if(in_array($route, Factory::validClasses())) {
        return self::routeClass($route, $parts);
      }
      else {
        throw new \Exception(t('Path %s not found', $path));
      }
    }
    
    private static function addRoute($route, $callback) {
      if(array_key_exists($route, self::$routes)) {
        return false;
      }
      self::$routes[$route] = $callback;
    }
    
    private static function init() {
      if(!self::$initialized) {
        self::$initialized = true;
        self::addAllRoutes();
      }
    }
    
    private static function addAllRoutes() {
      self::addRoute('home',        'App\Pages\Home::main');
      self::addRoute('fieldcheck',  'App\Router::fieldcheck');
      
      self::addRoute('user',        'App\Utils\User::main');
      self::addRoute('settings',    'App\Utils\Settings::main');
      self::addRoute('log',         'App\Utils\Logger::main');
      
      self::addRoute('ajax',        'App\Pages\Ajax::main');
      self::addRoute('stockfixer',  'App\Pages\StockFixer::main');
      self::addRoute('list',        'App\Pages\ListEdit::main');
      self::addRoute('theme',       'App\Pages\Theme::main');
      self::addRoute('duplicates',  'App\Pages\DuplicatesDeleter::main');
      self::addRoute('backup',      'App\Pages\Backup::main');
      self::addRoute('schema',      'App\Pages\Schema::main');
      self::addRoute('unused',      'App\Pages\UnusedDeleter::main');
      self::addRoute('inventory',   'App\Pages\Inventory::main');
      
      self::addRoute('query-runner',  'App\Query\QueryRunner::main');
      self::addRoute('query-builder-test', 'App\Query\QueryBuilder::test');
      
      self::addRoute('gallery-test', 'App\Utils\Gallery::test');
      self::addRoute('barcode', 'App\Utils\Barcode::main');
      self::addRoute('icons', 'App\Utils\Icons::main');
      
      self::addRoute('phpinfo',     function() {
        setTitle(t('PHP info'));
        ob_start();
        phpinfo();
        return ob_get_clean();
      });
    }
    
    private static function fieldCheck() {
      $keys = [];
      $settings = [];
      foreach(Factory::validClasses() as $class) {
        $inst = Factory::newInstance($class);
        $settings[$class] = $inst->settings();
        foreach($settings[$class] as $field_name => $set) {
          $keys = array_merge($keys, array_keys($set));
        }
      }
      $keys = array_unique($keys);
      $headers = $keys;
      array_unshift($headers, 'class', 'field');
      $matrix = [$headers];
      foreach(Factory::validClasses() as $class) {
        foreach($settings[$class] as $field_name => $set) {
          $row = [$class, $field_name];
          foreach($keys as $key) {
            if(isset($set[$key])) {
              $row[] = $set[$key];
            }
            else {
              $row[] = null;
            }
          }
          $matrix[] = $row;
        }  
      }
      return Format::tablarizeMatrix($matrix);
    }
        
    private static function routeClass($class, $parts) {
      if(count($parts) === 1) {
        return self::routeList($class);
      }
      else if(count($parts) === 2) {
        $id = $parts[1];
        if($id === 'new') {
          setMainClasses('page-new new-' . $class);
          return self::routeEdit($class);
        } 
        else if($id === 'search') {
          setMainClasses('page-search search-' . $class);
          return self::routeSearch($class);
        }
        else if(intval($id)){
          setMainClasses('page-display display-' . $class);
          return self::routeDisplay($class, $id);
        } else {
          return self::routeSubAction($class, [$id]);
        }
      }
      else if(count($parts) >= 3) {
        $id = $parts[1];
        $action = $parts[2];
        if($action === 'edit') {
          setMainClasses('page-edit edit-' . $class);
          return self::routeEdit($class, $id);
        }
        else if ($action === 'delete') {
          setMainClasses('page-delete delete-' . $class);
          return self::routeDelete($class, $id);
        }
        else {
          return self::routeSubAction($class, array_slice($parts, 1));
        }
      }
    }
    
    private static function routeSearch($class) {
      $instance = Factory::newInstance($class);
      return $instance->searchRoute();
    }
        
    private static function routeList($class) {
      $instance = Factory::newInstance($class);
      return $instance->listRoute();
    }
    
    private static function routeDisplay($class, $id) {
      $instance = Factory::newInstance($class, $id);
      return $instance->displayRoute();
    }
    
    private static function routeEdit($class, $id = null) {
      $instance = Factory::newInstance($class, $id);
      return $instance->editRoute();
    }
    
    private static function routeDelete($class, $id) {
      $instance = Factory::newInstance($class, $id);
      return $instance->deleteRoute();
    }
    
    private static function routeSubAction($class, $params = []) {
      if(!count($params)) {
        Msg::error(t('Bad programmer mistake in routing!'));
        return self::routeList($class);
      }
      $id = false;
      if(intval($params[0])) {
        $id = array_shift($params);
      }
      if(!count($params)) {
        Msg::error(t('Bad programmer mistake in routing, part two!'));
        return self::routeDisplay($class, $id);
      }
      $action = array_shift($params);
      $instance = Factory::newInstance($class, $id);
      $subroute = $action . 'route';
      if(method_exists($instance, $subroute)) {
        return $instance->$subroute($params);
      }
      Msg::warning(t('Unsupported subroute %s', $action));
    }
    
  }
