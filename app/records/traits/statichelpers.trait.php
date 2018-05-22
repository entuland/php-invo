<?php
  namespace App\Records\Traits;
  
  use App\Utils\Msg;
  
  trait StaticHelpers {
        
    static function operatorsTooltip() {
      return '<div id="operators-tooltip" style="display: none">'
        . t('Accepted operators:')
        . '<ul>'
        . '<li><em>= n</em>: ' . t('equal to "n"') . '</li>' 
        . '<li><em>&lt; n</em>: ' . t('less than "n"') . '</li>' 
        . '<li><em>&gt; n</em>: ' . t('greater than "n"') . '</li>' 
        . '<li><em>&lt;= n</em>: ' . t('less or equal to "n"') . '</li>' 
        . '<li><em>&gt;= n</em>: ' . t('greater or equal to "n"') . '</li>' 
        . '<li><em>&lt;&gt; n</em>: ' . t('different from "n"') . '</li>' 
        . '<li><em>% n</em>: ' . t('not a multiple of "n"') . '</li>' 
        . '<li><em>* n</em>: ' . t('multiple of "n"') . '</li>'
        . '</ul></div>';
    }
    
    static function listIntro($recordInstance) {
      Ob_start();
?>
<form method="POST" action="<?= config('publicbase') ?>/list" target='_blank' id="list">
  <input type="hidden" name="list-class" value="<?= $recordInstance->className() ?>">
  <input type="hidden" name="list-action" value="prepare">
<?php
      return Ob_get_clean();
    }
    
    static function listButtons($recordInstance) {
      Ob_start();
?>
<div class="list-buttons">
  <div class="button-group">
    <span class="button-group-label"><?= t('Select')?>:</span> 
    <button class="button list-select-all" onclick="return false;"><?= t('All') ?></button>
    <button class="button list-select-none" onclick="return false;"><?= t('None') ?></button>
    <button class="button list-select-invert" onclick="return false;"><?= t('Invert') ?></button>
    <button type="submit" name="button-action" value="prepare"><?= t('Edit selected') ?></button>
  </div>
<?= $recordInstance->specialListButtons() ?>
  </div>
<?php
      return Ob_get_clean();
    }
    
    function specialListButtons() { return ''; }
    
    static function listOutro() {
      return '</form>';
    }
        
    static function searchButtons($add_resets) {
      $buttons = '';
      $t_search = t('Search');
      $buttons .= <<<HTML
        <button class="button search"
          type="submit" 
          name="action"
          value="search"
  >$t_search</button>
HTML;
      if($add_resets) {
        $t_reset = t('Reset');
        $t_clear = t('Clear');
        $buttons .= <<<HTML
  <button class="button reset"
          type="reset"
  >$t_reset</button>
  <a class="button clear" href="javascript:">$t_clear</a>
HTML;
      }
      
      return $buttons;
    }
    
    static function matchingSettings() {
      return [
        'widget' => 'select',
        'options' => [
          'AND' => t('All the fields'),
          'OR' => t('Any field'),
        ],
        'default' => 'AND',
        'default_mode' => 'key',
        'required' => true,
      ];
    }
    
    static function prepareDateSearchParam($value) {
      $nevers = [
        mb_strtolower(t('never')),
        mb_strtolower(t('no')),
        'never',
        'no',
      ];
      $result = [
        'value' => false,
      ]; 
      if(in_array(trim(mb_strtolower($value)), $nevers)) {
        $result['original'] = t('no');
        $result['operator'] = '=';
        $result['value'] = '0000-00-00 00:00:00';
      } else {
        $year = '';
        $month = '';
        $day = '';
        $time = '';
        $matches = [];
        if(preg_match("#(\d{4})\D(\d{1,2})\D(\d{1,2})(.*)#", $value, $matches)) {
          $year = $matches[1];
          $month = $matches[2];
          $day = $matches[3];
          $time = $matches[4];
        }
        else if(preg_match("#(\d{1,2})\D(\d{1,2})\D(\d{4})(.*)#", $value, $matches)) {
          $day = $matches[1];
          $month = $matches[2];
          $year = $matches[3];
          $time = $matches[4];
        }
        else if(preg_match("#(\d{1,2})\D(\d{4})(.*)#", $value, $matches)) {
          $month = $matches[1];
          $year = $matches[2];
          $time = $matches[3];
        }
        else if(preg_match("#(\d{4})(.*)#", $value, $matches)) {
          $year = $matches[1];
          $time = $matches[2];
        }
        else if(preg_match("#(\d{4})\D(\d{1,2})(.*)#", $value, $matches)) {
          $year = $matches[1];
          $month = $matches[2];
          $time = $matches[3];
        }
        else {
          Msg::error(t('Invalid date %s', $value));
          return $result;
        }
        $date = '';
        if($year) {
          $date .= $year;
        }
        if($month) {
          $date .= '-' . str_pad($month, 2, '0');
        }
        if($day) {
          $date .= '-' . str_pad($day, 2, '0');
        }
        $time_part = preg_replace('#\D#u', ':', trim($time));
        if($year && $month && $day && $time_part) {
          $time_part = ' ' . $time_part;
        }
        $result['operator'] = 'LIKE';
        $result['value'] = $date . $time . '%';
      }
      return $result;
    }
    
    static function prepareNumberSearchParam($field) {
      $value = $field->value;
      $test_operator = preg_replace('#[^<>=%*]#', '', $value);
      $valid_operators = [
        '<',
        '<=',
        '>',
        '>=',
        '<>',
      ];
      $operator = '=';
      if(in_array($test_operator, $valid_operators)) {
        $operator = $test_operator;
      }
      $numeric_value = preg_replace('#[^\d\.]#', '', $value);
      if($field->storagemultiplier) {
        $numeric_value *= $field->storagemultiplier;
      }
      $result = [
        'value' => $numeric_value,
        'operator' => $operator,
      ];
      if(in_array($test_operator, ['%', '*'])) {
        $result['wrapper'] = "MOD(%s,$numeric_value)";
        $result['value'] = 0;
        $result['operator'] = $test_operator === '%' ? '<>' : '=';
      }
      return $result;
    }
    
    static function prepareReferenceSearchParam($field) {
      $value = $field->value;
      $result = [
        'value' => $value,
        'operator' => '=',
      ];
      if(strlen($value) && $value{0} === '!') {
        $result = [
          'value' => substr($value, 1),
          'operator' => '<>',
        ];
      }
      return $result;
    }
    
    
  }