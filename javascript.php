<?php
  $scripts = [
    // libs
    'lib/gettext/Gettext',
    'lib/color',
    'lib/sprintf',
    
    // helpers
    'app',
    'functions',
    'ajax',
    'movable',
    
    // autorun - main
    'msg',
    'cover',
    'dialog',
    'deleter',
    'collapsible',
    'tables',
    'rapid-edit',
    
    // autorun - secondary
    'rapid-change',
    'theme',
    'navigation',
    'forms',
    'keyboard',
    'autocomplete',
    'list-edit',
    'list-select',
    'search',
    'autoprice',
    'pager',
    'xdebug',
    'gallery',
    'category',
    'print',
    'tabbed',
    'person',
    'tooltip',
    
  ];
  
  define('JS_CACHE_FILE', 'files/js/cache.js');
  
  $settings = new \App\Utils\Settings();
  
  if($settings->disableResourcesCaching) {
    \App\Utils\Msg::warning(t('Resources cache is disabled, enable it from the settings page'));
    foreach($scripts as $script) {
?>
    <script src="<?= config('publicbase') ?>/js/<?= $script ?>.js"></script>
<?php
    }
    if(file_exists(JS_CACHE_FILE)) {
      unlink(JS_CACHE_FILE);
    }
  } 
  else {
    $recreate = $settings->recreateCacheContinuously;
    if(!file_exists(JS_CACHE_FILE) || $recreate) {
      if($recreate) {
        \App\Utils\Msg::warning(t('Resources cache recreated at every page request, disable it from the settings page'));
      }
      createJsCache($scripts);
    }
?>
<script src="<?= config('publicbase') . '/' . JS_CACHE_FILE . cacheBuster() ?>"></script>
<?php
  }
  
  function createJsCache($scripts) {
    $path = dirname(JS_CACHE_FILE);
    if(!is_dir($path)) {
      mkdir($path);
    }
    $cache_content = '';
    foreach($scripts as $script) {
      $cache_content .= file_get_contents('js/' . $script.'.js') . PHP_EOL;
    }
    file_put_contents(JS_CACHE_FILE, $cache_content);
  }
    
