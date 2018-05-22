<?php
  mb_internal_encoding("UTF-8");
  
  session_start();

  require_once('app/config.php');

  putenv("LC_MESSAGES=" . config('localecode'));
  putenv("LC_ALL=" . config('localecode'));
  putenv("LANG=" . config('locale'));
  setlocale(LC_ALL, config('localecode'), config('locale'));
  
  $domain = 'app';
  bindtextdomain($domain, './locale');
  bind_textdomain_codeset($domain, 'UTF-8');
  textdomain($domain);
  
  spl_autoload_extensions('.class.php,.trait.php');
  spl_autoload_register();
    
  require_once('lib/uuid.class.php');
  require_once('lib/parsedown/Parsedown.php');
  require_once('app/code128-constants.php');
  require_once('app/ascii-constants.php');
  require_once('app/common.php');
  