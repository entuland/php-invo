<?php

  namespace App\Utils;
  
  class Icons {
    const NAMES_FILE = 'res/fa/less/icons.less';
    
    static function main() {
      setTitle(t('Icons'));
      return self::table();
    }
    
    private static function table() {
      $names = self::names();
      $output = '';
      foreach($names as $name) {
        $icon = self::i($name, 4);
        $output .= <<<HTML
<span style="display: inline-block; border: 1px solid black; background: white; padding: 2px; margin: 2px; text-align: center;">$icon<br>fa-$name</span>
HTML;
      }
      return $output;
    }
    
    static function i($name, $size = 1, $title = '') {
      if($title) {
        $title = 'title="' . $title . '"';
      }
      return "<i {$title}class='fa fa-{$size}x fa-$name'></i>";
    }
    
    private static function names() {
      $content = file_get_contents(self::NAMES_FILE);
      $matches = [];
      preg_match_all('#\}-(.+?)\:#', $content, $matches);
      return $matches[1];
    }
    
  }
