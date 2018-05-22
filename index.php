<?php
  declare(strict_types=1);
  
  use App\Router;
  use App\Utils\Msg;
  use App\Pages\Theme;
  use App\Utils\Settings;
  use App\Utils\User;
  use App\Utils\Format;
  
  require 'bootstrap.php';
  
  setTitle(config('sitename'));
  setMainClasses('home');
  
  $microtime_start = microtime(true);

  $main_content = "";
  
  $settings = new Settings();

  if($settings->debugPost) {
    Debug::enable(Debug::POST);
  }
  if($settings->debugFind) {
    Debug::enable(Debug::FIND);
  }
  if($settings->debugQuery) {
    Debug::enable(Debug::QUERY);
  }
  
  
  Debug::maybe(Debug::POST, function() {
    if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
      Format::dump($_POST, '$_POST');
    } 
    else {
      Msg::warning(t('No $_POST data to dump'));  
    }
  });
  
  try {
    $main_content .= compactString(Router::route(request_path()));  
  } catch (\Exception $ex) {
    setTitle(t('error'));
    Msg::error($ex->getMessage());
  }
  
  $theme_css = Theme::currentThemeCss();
  if(User::validUser()) {
    $js_settings = $settings->javascriptSettings();
    $menu = requireCompact('menu.php');
    $javascript = requireCompact('javascript.php');
    appendMainClasses('logged-in');
    $valid_user = true;
  }
  else {
    $menu = '';
    $javascript = '';
    $js_settings = '';
    setMainClasses('not-logged-in');
    $valid_user = false;
  }
    
  $return = true;
  $messages = Msg::flush($return);
  require 'templates/main.tpl.php';
  