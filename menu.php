<?php
  use App\Utils\Format;
  use App\Utils\Icons;
  use App\Records\Factory;
  use App\Records\Document;
  
  $menu_icons_size = 2;

?>
<div id="menu">
  <a class="button" href="<?= config('publicbase') ?>/?cur_doc_id=<?= Document::getCurrent()->id ?>" title="<?= t('Load/unload') ?>"><?= Icons::i('home', $menu_icons_size) ?></a>
  <?php 
    
  /* ===========================================================================
    MAIN BUTTONS
  =========================================================================== */
    $links = [
      'item' => Icons::i('tags', $menu_icons_size),
      'document' => Icons::i('book', $menu_icons_size),
    ];
    array_walk($links, function($icon, $link) {
      $text = t($link.'s');
      if($link === 'document') {
        $link = 'document/filter/id_company/!' . config('stockcompanyid');
      }
      ?>
        <a class="button" href="<?= config('publicbase') ?>/<?= $link ?>" title="<?= $text ?>"><?= $icon ?></a> 
      <?php
    });
    
  /* ===========================================================================
    SEARCH MENU
  =========================================================================== */    
    
    $search_links = Factory::validClasses();
    unset($search_links['change']);
    array_walk($search_links, function(&$item) {
      $base = config('publicbase');
      $text = t($item . 's');
      $item = <<<HTML
        <a class="button" href="$base/$item/search">$text</a> 
HTML;
    });
    $search_header = Icons::i('search', $menu_icons_size, t('Search'));
    echo Format::dropdown($search_header, $search_links);
    
  /* ===========================================================================
    NEW MENU
  =========================================================================== */    
    
    $new_links = Factory::validClasses();
    unset($search_links['change']);
    array_walk($new_links, function(&$item) {
      $base = config('publicbase');
      $text = t($item);
      $item = <<<HTML
        <a class="button" href="$base/$item/new">$text</a> 
HTML;
    });
    $new_header = Icons::i('plus', $menu_icons_size, t('New'));
    echo Format::dropdown($new_header, $new_links);
    
  /* ===========================================================================
    TABLES LINKS
  =========================================================================== */    

    $tables_links = Factory::validClasses();
    array_walk($tables_links, function(&$item) {
      $base = config('publicbase');
      $text = t($item . 's');
      $item = <<<HTML
        <a class="button" href="$base/$item">$text</a> 
HTML;
    });
    $tables_header = Icons::i('th-list', $menu_icons_size, t('Tables'));
    echo Format::dropdown($tables_header, $tables_links);    
    
  /* ===========================================================================
    OTHER LINKS
  =========================================================================== */    

    $other_links = [
      'user',
      'theme',
      'log',
      'inventory',
      'backup',
      // 'duplicates',
      // 'unused',
      // 'stockfixer',
      'icons',
      'settings',
    ];
    array_walk($other_links, function(&$item) {
      $base = config('publicbase');
      $text = t($item);
      $item = <<<HTML
        <a class="button" href="$base/$item">$text</a> 
HTML;
    });
    $other_header = Icons::i('cog', $menu_icons_size, t('Other'));
    echo Format::dropdown($other_header, $other_links);    
    
  ?>
  <a class="button" href="<?= config('publicbase') ?>/person"><?= Icons::i('user', $menu_icons_size, t('Persons')) ?></a>
  <a class="button" href="<?= config('publicbase') ?>/payment"><?= Icons::i('money-bill', $menu_icons_size, t('Payments')) ?></a>
  <a class="button" href="<?= config('publicbase') ?>/request/filter/closed/never/status/print"><?= Icons::i('clipboard', $menu_icons_size, t('Requests')) ?></a>
  <div id="navigation-container">
    <div>
    <a class="button" id="navigation-back"><?= Icons::i('arrow-left', $menu_icons_size) ?></a>       
    <a class="button" id="navigation-forward"><?= Icons::i('arrow-right', $menu_icons_size) ?></a>
    </div>
    <div id="navigation-select-container">
      <select id="navigation-select"></select>
    </div>
  </div>
</div>

