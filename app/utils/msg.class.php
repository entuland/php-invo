<?php

  namespace App\Utils;
  
  class Msg {
    static function msg(string $text, string $level = 'normal') {
      $_SESSION['messages'][$level][] = $text;
    }
    
    static function raw(string $text) {
      self::msg($text, 'raw');
    }
    
    static function normal(string $text) {
      self::msg($text);
    }
    
    static function debug(string $text) {
      self::msg($text, 'debug');
    }
    
    static function error(string $text) {
      self::msg($text, 'error');
    }
    
    static function warning(string $text) {
      self::msg($text, 'warning');
    }
    
    static function getRawMessages() {
      if(array_key_exists('messages', $_SESSION)) {
        return $_SESSION['messages'];
      }
      return [];
    }
    
    static function deleteMessages() {
      unset($_SESSION['messages']);
    }
    
    static function flush($return = false) {
      $output = '';
      $messages = self::getRawMessages();
      foreach($messages as $level => $sub) {
        if($level === 'raw') {
          $output .= implode(Format::wrap('<pre class="message warning raw">', $sub, '</pre>'));
        } else {
          $sub = array_keys(array_count_values($sub));
          if(count($sub) > 1) {
            $sub = '<ul>' . implode(Format::wrap('<li>', $sub, '</li>')) . '</ul>';          
          }
          else {
            $sub = $sub[0];
          }
          $output .= <<<HTML
  <div class="message $level">$sub</div>
HTML;
        }
      }
      self::deleteMessages();
      if($return) {
        return $output;
      }
      echo $output;
    }
  }
