<?php

  namespace App\Utils;

  class Pager {
    static $pages = 0;
    static $rows = 0;
    static $start = 0;
    static $count = 25;
    private static $first_link = '';
    private static $cur_link = '';
    private static $last_link = '';
    
    static function init() {
      $settings = new Settings();
      self::$count = $settings->defaultPagerCount;
      
      $pager_start = filter_input(INPUT_GET, 'start', FILTER_VALIDATE_INT);
      $pager_count = filter_input(INPUT_GET, 'count', FILTER_VALIDATE_INT);

      if(is_int($pager_start)) {
        self::$start = $pager_start;
      }

      if(is_int($pager_count) && $pager_count > 0) {
        self::$count = $pager_count;
      }
    }

    static private function prepareLinks($rows) {
      $pages = ceil($rows / self::$count);
      self::$pages = $pages;
      self::$rows = $rows;
      
//      if($pages < 2) {
//        self::$first_link = 
//          self::$cur_link =
//          self::$last_link = '';
//        return;
//      }
      
      $current = floor(self::$start / self::$count) + 1;
      
      $first_link = self::renderPageLink(1, t('First'));
      $cur_link = self::renderPageLink($current, t('Page %s', $current), false);
      $last_link = self::renderPageLink($pages, t('Last') . " ($pages)");
      
      self::$first_link = '';
      if($current != 1) {
        self::$first_link = $first_link;
      }
      
      self::$cur_link = $cur_link;
      
      self::$last_link = '';

      if($current != $pages) {
        self::$last_link = $last_link;
      }
      
    }
    
    static private function renderPrevButton() {
      $start = self::$start - self::$count;
      $count = self::$count;
      if($start < 0) {
        $start = 0;
      }
      $text = t('prev');
      return <<<HTML
      <a class="button pager-button pager-prev-button"
        href="?start=$start&count=$count">$text</a>
HTML;
    }
    
    static private function renderNextButton() {
      $start = self::$start + self::$count;
      $count = self::$count;
      if($start < 0) {
        $start = 0;
      }
      $text = t('next');
      return <<<HTML
      <a class="button pager-button pager-prev-button"
        href="?start=$start&count=$count">$text</a>
HTML;
    }
        
    static private function renderPageLink($page, $text, $active = true) {
      $count = self::$count;
      $start = ($page-1) * $count;
      if($active) {
        return <<<HTML
        <a class="button pager-link"
          href="?start=$start&count=$count">$text</a>
HTML;
      }
      $pages = self::$pages;
      return <<<HTML
        <div class="curpage" 
          data-page="$page" 
          data-start="$start" 
          data-count="$count"
          data-pages="$pages"
        >$text</div>
HTML;
    }
    
    static private function renderCompleteControls($rows) {      
      self::prepareLinks($rows);
      $controls = '';
      if(self::$first_link) {
        $controls .= self::$first_link . self::renderPrevButton();
      }
      $controls .= self::$cur_link;
      if(self::$last_link) {
        $controls .= self::renderNextButton() . self::$last_link;
      }
      return self::wrapControls($controls, 'complete');      
    }
        
    static private function renderSimpleControls() {
      $controls = self::renderPrevButton() . self::renderNextButton();
      return self::wrapControls($controls, 'simple');
    }

    static private function wrapControls($controls, $class) {
      return <<<HTML
        <div class='pager pager-$class'>
          $controls
        </div>
HTML;
    }
    
    static function renderControls($rows_count = null) {
      if(is_int($rows_count) || intval($rows_count, 10)) {
        return self::renderCompleteControls($rows_count);
      }
      return self::renderSimpleControls();
    }
  }
  
  Pager::init();
  
