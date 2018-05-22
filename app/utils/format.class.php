<?php

  namespace App\Utils;
  
  use App\Records\Record;
  
  class Format {
    
    static function bytes($size) {
      $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
      $power = $size > 0 ? floor(log($size, 1024)) : 0;
      return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
    
    static function datalist($options, $id) {
      $output = '<datalist id="' . $id . '">';
      $clean_options = array_map('safeMarkup', $options);
      $output .= implode(self::wrap('<option value="', $clean_options, '">'));
      $output .= '</datalist>';
      return $output;
    }
    
    static function hasNumericIndex($array) {
      foreach(array_keys($array) as $key) {
        if(intval($key)) {
          return true;
        }
      }
      return false;
    }
    
    static function select($options, $default, $field_name, $default_mode = 'value', $attributes = []) {
        Ob_start();
        $attr = [];
        foreach($attributes as $key => $value) {
          if(is_null($value)) {
            $attr[] = $key;
          }
          else {
            $attr[] = $key . '="' . $value . '"';
          }
        }
        $attr_markup = implode(' ', $attr);
        echo <<<HTML
          <select name='$field_name' $attr_markup>
HTML;
        
        if(is_null($default)) {
          $default = '';
        }
        $default = ''. $default;
        foreach($options as $value => $text) {
          $value = '' . $value;
          $text = '' . $text;
          $selected = '';
          if(($default_mode === 'value' && $text === $default)
              || ($default_mode === 'key' && $value === $default) 
            ) {
            $selected = 'selected';
          }
          $value = safeMarkup($value);
          $text = safeMarkup($text);
          echo <<<HTML
            <option value="$value" $selected>$text</option>
HTML;
        }
        echo <<<HTML
          </select>
HTML;
        return Ob_get_clean();
    }
    
    static function wrap($prefix, $array, $postfix) {
      return array_map(function($element) use ($prefix, $postfix) {
        if(is_array($element)) {
          $element = serialize($element);
        }
        return $prefix . $element . $postfix;
      }, $array);
    }

    static function export($obj, $label = null) {
      $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
      self::callDebugFunction($obj, $label, 'var_export', $backtrace);
    }
    
    static function dump($obj, $label = null) {
      $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
      self::callDebugFunction($obj, $label, 'var_dump', $backtrace);
    }
    
    static function callDebugFunction($obj, $label, $debugFunction, $backtrace = false) {
      Ob_start();
      if(is_string($label)) {
        echo '<br><kbd>' . $label . '</kbd><br>';
      }
      if(!$backtrace) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
      }
      $debugFunction($obj);
      $file = $backtrace[0]['file'];
      $basename = basename($file);
      $function = $backtrace[1]['function'];
      $line = $backtrace[0]['line'];
      echo '<hr><em>';
      echo "$basename:$line:$function() [$file]";
      echo '</em>';
      $output = Ob_get_clean();
      Msg::debug($output);
    }
    
    static function tablarizeMatrix($matrix, $has_headers = true, $classes = []) {
      $head = '';
      if($has_headers) {
        $headers = array_shift($matrix);
        array_shift($classes);
        $header_row = self::wrap('<th>', $headers, '</th>');
        $head = '<thead><tr>' . implode($header_row) . '</tr></thead>';
      }
      $rows = array();
      foreach($matrix as $index => $cells) {
        $row = self::wrap('<td>', $cells, '</td>');
        $class = $classes[$index] ?? '';
        $rows[] = '<tr class="'.$class.'">' . implode($row) . '</tr>';
      }
      return '<table>' . $head . '<tbody>' . implode($rows) . '</tbody></table>';
    }
    
    static function money($amount) {
      return sprintf('&euro;&nbsp;%.2f', $amount); 
    }
    
    static function applyRounding($amount, $rounding) {
      if($rounding) {
        $amount *= 100;
        $rounding *= 100;
        $rounding = abs($rounding);
        $excess = $amount % $rounding;
        if($excess) {
          if($excess > $rounding/2) {
            $amount += $rounding - $excess;
          } else {
            $amount -= $excess;
          }
        }
        $amount = round($amount) / 100;
      }
      return number_format($amount, 2);
    }
    
    static function collapsible($markup, $title, $id = null, $open = false, $affect_pager = false) {
      if(!$id) {
        $id = \UUID::v4();
      }
      Ob_start();
      ?>
<div class="collapsible" 
     id="<?= $id ?>" 
     data-title="<?= escapeHtmlAttribute($title) ?>"
     data-affect-pager="<?= $affect_pager ? '1' : '0' ?>"
>
  <div class="collapsible-content <?= $open ? '' : 'collapsed' ?>">
    <?= $markup ?>
  </div>
</div>
      <?php
      return Ob_get_clean();
    }
    
    static function dropdown($title, $options, $id = false, $first_href = false) {
      Ob_start();
      $tag = 'button';
      if($first_href) {
        $tag = 'a';
      }
      ?>
      <div class="drop" id="<?= $id ?>">
        <<?= $tag ?> 
          class="drop-first-button button"
          <?= $first_href ? 'href="' . config('publicbase') . '/' . $first_href . '"' : '' ?>
        >
            <?= $title ?>
        </<?= $tag ?>>
        <div class="drop-items">
          <?= implode(self::wrap('<div class="drop-item">', $options, '</div>')) ?>
        </div>
      </div>
      <?php
      return Ob_get_clean();
    }
    
    static function renderFormObject($form, $context = Record::EDIT_CONTEXT) {
      Ob_start();
?>
<?= $form->before_form ?>
<form method="POST" 
      class="<?= $form->class ?>"
      data-unique-class="<?= $form->unique_class ?>"
      data-unique-field="<?= $form->unique_field ?>"
      action="<?= $form->action ?>"
      novalidate>
  <input type="hidden" name="submission_id" value="<?= \UUID::v4()?>">
  <?= $form->before_table ?>
  <table class="<?= $form->table_class ?>">
    <caption><?= $form->caption ?></caption>  
    <tbody>
      <?php
        if($form->fields) {
          foreach($form->fields as $field) {
            echo $field->formatAsFormRow($context);
          }
        }
      ?>
    </tbody>
  </table>
  <?= $form->after_table ?>
  <div class="form-buttons">
    <?= $form->buttons ?>
  </div>
</form>
<?= $form->after_form ?>
<?php
      $output = Ob_get_clean();
      if($form->show_toggle) {
        return Format::collapsible($output, $form->toggle_text);
      }
      return $output;
    }
    
  }