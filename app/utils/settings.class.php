<?php
  declare(strict_types = 1);
  
  namespace App\Utils;
  
  use App\Utils\Msg;
  use App\Records\Factory;
  use App\Records\Document;
  use App\Pages\Theme;
  
  class Settings {
    const SETTINGS_FILE = 'files/private/settings.json';
    const JSON_ASSOC = true;
    const FORCE_REBUILD = true;
    
    const SHOW_IN_LINER_DISPLAY_PREFIX = 'np_show_in_liner_';
    const SHOW_IN_SINGLE_DISPLAY_PREFIX = 'np_show_in_single_';
    const SHOW_IN_LIST_DISPLAY_PREFIX = 'np_show_in_list_';
    const SHOW_IN_PRINT_DISPLAY_PREFIX = 'np_show_in_print_';
    const SHOW_IN_MAILFAX_DISPLAY_PREFIX = 'np_show_in_mailfax_';
    
    const CONTROL_LINER_DISPLAY_PREFIX = 'np_control_liner_';
    const CONTROL_SINGLE_DISPLAY_PREFIX = 'np_control_single_';
    const CONTROL_LIST_DISPLAY_PREFIX = 'np_control_list_';
    const CONTROL_PRINT_DISPLAY_PREFIX = 'np_control_print_';
    const CONTROL_MAILFAX_DISPLAY_PREFIX = 'np_control_mailfax_';
    
    static $settings = [];
    static $autocomplete = [];
    static $additional = [];
    
    static function main() {
      setTitle(t('Settings manager'));
      $settings = new Settings();
      return $settings->manager();
    }

    private function init(bool $force_rebuild = false) {
      if(!self::$settings || $force_rebuild) {
        $new_settings = $this->defaultSettings();
        if(file_exists(self::SETTINGS_FILE)) {
          $json = file_get_contents(self::SETTINGS_FILE);
          if($json !== false) {
            $settings = json_decode($json, self::JSON_ASSOC);
            if(!is_null($settings)) {
              $new_settings = $this->mergedSettings($settings);
            }
          }
        }
        self::$settings = $new_settings;
      }
    }
    
    function __construct() {
      $this->init();
    }
        
    function javascriptSettings(): string {
      $this->prepareAppData();
      Ob_start();
?>
<script>
  var app = app || {};  
  app.settings = app.settings || {};
  app.data = app.data || {};
<?php
    foreach(self::$settings as $setting_name => $setting_options) {
      if(strpos($setting_name, 'np_') === 0) {
        continue;
      }
?>
  app.settings.<?= $setting_name ?> = <?= json_encode($setting_options['value']) ?>;
<?php
    }
    
    foreach(self::$additional as $key => $content) { 
?>
  app.data.<?= $key ?> = <?= json_encode($content) ?>;
<?php
    }
?>
  onLoad(function() {
    var checkerChanged = function(ch) {
      var tr = parentMatchingQuery(ch, 'tr');
      if(tr) {
        tr.classList.toggle('selected', ch.checked);
        var subtable = tr.querySelector('table');
        if(subtable) {
          subtable.style.display = ch.checked ? 'table' : 'none';
        }
      }
      if(forms && typeof forms.checkDirty === 'function') {
        forms.checkDirty(ch);
      }
    };
    var checkers = document.querySelectorAll('#settings input[type=checkbox]');
    forEach(checkers, function(ch) {
      ch.addEventListener('change', function(){ checkerChanged(ch);});
      checkerChanged(ch);
    });
  });
</script>
<?php
      return Ob_get_clean();
    }
    
    function addJSdata(string $key, $obj) {
      self::$additional[$key] = $obj;
    }
    
    function registerAutocomplete(string $class, string $field) {
      self::$autocomplete[$class][$field] = true;
    }
    
    function prepareAppData() {
      $this->addJSdata('publicBase', config('publicbase'));
      try {
        $document = Document::getCurrent();
        $company = Factory::newCompany($document->id_company);
        $this->addJSdata('document', $document->resolvedFieldPairs());
        $this->addJSdata('company', $company->resolvedFieldPairs());            
      } catch (\Exception $ex) {
        // Msg::error($ex->)
      }
      $this->addJSdata('theme', [
        'name' => Theme::currentThemeName(),
        'markers' => Theme::loadCurrentTheme(),
      ]);
      $additional = [];
      foreach(self::$autocomplete as $class => $fields) {
        $fields = array_keys($fields);
        $instance = Factory::newInstance($class);
        $settings = $instance->settings();
        foreach($fields as $field) {
          $values = $instance->uniqueValues($field);
          $additional[$class][$field]['values'] = $values;
          $autolimit = $settings[$field]['autolimit'] ?? 1;
          $additional[$class][$field]['autolimit'] = $autolimit;
          $additional[$class][$field]['count'] = count($values);
        }
      }
      $this->addJSdata('unique', $additional);
      $category = Factory::newCategory();
      $ids = $category->findFromFilterPath('multiplier/>0')['ids'];
      $category_multipliers = [];
      foreach($ids as $id) {
        $cat = Factory::newCategory($id);
        $category_multipliers[$cat->category] = $cat->multiplier;
      }
      $this->addJSdata('categoryMultipliers', $category_multipliers);
    }
        
    function manager(): string {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $this->processPost();
      }
      $this->init(self::FORCE_REBUILD);
      return $this->renderEditForm();
    }
    
    private function processPost() {
      $default = $this->defaultSettings();
      $new_values = [];
      $errors = 0;
      foreach($default as $setting_name => $setting_option) {
        $posted_value = getPOST($setting_name);
        if(is_null($posted_value)) {
          continue;
        }
        if($setting_option['widget'] === 'textarea') {
          $new_value = trim($posted_value);
        } else {
          $new_value = preg_replace('#\s+#', '', $posted_value);
        }
        if(array_key_exists('cleaner', $setting_option)) {
          $new_value = $setting_option['cleaner']($new_value);
        }
        if(array_key_exists('validator', $setting_option)) {
          if(!$setting_option['validator']($new_value)) {
            ++$errors;
            continue;
          }
        }
        if($setting_option['widget'] === 'checkbox') {
          $new_value = !!$new_value;
        }
        $new_values[$setting_name] = $new_value;
      }
      if($errors) {
        return;
      }
      $settings = $this->mergedSettings($new_values);
      $this->setStoredSettings($settings);
      Msg::msg(t('Settings stored successfully'));
    }
    
    private function renderEditRow(string $setting_name, Dynamic $set): array {
      $hidden = false;

      $method = 'control' . str_replace('-', '', $set->context);
      // \App\Utils\Format::dump($setting_name, $method);
      if(method_exists($this, $method)) {
        $hidden = !$this->$method($set->class);
      }
      
      $widget = $this->renderEditWidget($setting_name, $set);
      
      if($set->widget === 'textarea') {
        $content = $set->description . '<br>' . $widget;
      } else {
        $content = $widget . ' ' . $set->description;
      }
      

      if($set->widget === 'checkbox') {
        $content = '<label>' . $content . '</label>';
      }
      
      if($set->subsections) {
        foreach($set->subsections as $subsection => $subsettings) {
          $content .= $this->renderEditFormSection($subsection, $subsettings, $set->level + 1);
        }
      }
      
      return [
       'content' => "<tr><td>$content</td></tr>", 
       'hidden' => $hidden 
      ];
    }
    
    private function renderEditWidget(string $setting_name, Dynamic $set): string {
      switch($set->widget) {
        case 'number':
          return <<<HTML
  <input 
    type="number"
    name="$setting_name" 
    title="$setting_name" 
    step="{$set->step}"
    min="{$set->min}"
    max="{$set->max}"
    value="{$set->value}"
    required
  >
HTML;
        case 'checkbox':
          $checked = '';
          if($set->value) {
            $checked = 'checked';
          }
          return <<<HTML
  <input type="hidden" name="$setting_name" value="0">
  <input 
    type="checkbox"
    name="$setting_name" 
    title="$setting_name" 
    value="1"
    $checked
  >
HTML;
        case 'text':
          return <<<HTML
  <input 
    type="text"
    name="$setting_name" 
    title="$setting_name" 
    value="{$set->value}"
  >
HTML;
        case 'textarea':
          $value = htmlspecialchars(''.$set->value);
          return <<<HTML
  <textarea
    name="$setting_name" 
    title="$setting_name"
    style="text-transform: none"
  >$value</textarea>
HTML;
      }
      return '';
    }
        
    private function renderEditFormSection(string $section, array $settings, int $level = 0): string {
      ob_start();
      $hidden_count = 0;
      $rows = [];
      foreach($settings as $setting_name => $setting_options) {
        $setting_options->level = $level;
        $row = $this->renderEditRow($setting_name, $setting_options);
        if($row['hidden']) {
          ++$hidden_count;
        }
        $rows[] = $row['content'];
      }
      
      $style = '';
      if($hidden_count === count($settings)) {
        $style = 'display: none';
      }

?>
  <table 
    style="<?= $style ?>"
    data-level="<?= $level ?>"
  >
    <caption><?= $section ?></caption>
    <tbody>
<?= implode($rows) ?>
    </tbody>
  </table>
<?php
      return ob_get_clean();
    }
    
    private function renderEditForm(): string {
      $tree = $this->treedSettings();
      $tables_output = '';
      foreach($tree as $section => $settings) {
        $tables_output .= $this->renderEditFormSection($section, $settings);
      }

      ob_start();
?>
<form 
  id="settings" 
  method="POST" 
  id="settings"
  data-tabbed
  data-tab-containers='table[data-level="0"]'
  data-tab-titles="caption"
  data-tab-level="0"
>
<?= $tables_output ?>
  <button id="settings-save-button" class="button save"
          type="submit"
  ><?= t('Save settings') ?></button>
</form>
<?php
      return Ob_get_clean();
    }
    
    function __get(string $setting_name) {
      if(array_key_exists($setting_name, self::$settings)) {
        return self::$settings[$setting_name]['value'];
      }
      return false;
    }
    
    private function setStoredSettings(array $settings) {
      $storable_settings = [];
      foreach($settings as $setting_name => $setting_options) {
        $storable_settings[$setting_name] = $setting_options['value'];
      }
      $json = json_encode($storable_settings, JSON_PRETTY_PRINT);
      file_put_contents(self::SETTINGS_FILE, $json);
    }
    
    private function mergedSettings(array $settings): array {
      $default = $this->defaultSettings();
      foreach($settings as $setting_name => $setting_value) {
        if(array_key_exists($setting_name, $default)) {
          $default[$setting_name]['value'] = $setting_value;
        }
      }
      return $default;
    }
    
    private static function validateMultipliers(string $multipliers): bool {
      if(preg_match('#^(\d+([\.,]?\d+)?;?)+$#', $multipliers)) {
        return true;
      }
      $error = 
        t('Invalid multipliers') . ' <kbd>' . $multipliers . '</kbd><br>'
        . t('Each multiplier can contain only digits with an optional comma or a period.') . '<br>'
        . t('Multiple multipliers must be separated by semicolons.') . '<br>'
        . t('Examples') . ':<ul>'
        . '<li><kbd>2,2</kbd> (' . t('one multiplier') . ')</li>'
        . '<li><kbd>1.5;2</kbd> (' . t('two multipliers') . ')</li></ul>';
      Msg::error($error);
      return false;
    }
    
    private static function validateDiscounts(string $discounts): bool {
      if(preg_match('#^(\d+;?)+$#', $discounts)) {
        return true;
      }
      $error = 
        t('Invalid discounts') . ' <kbd>' . $discounts . '</kbd><br>'
        . t('Each discount can contain only digits.') . '<br>'
        . t('Discounts are expressed in percent quantities (percent sign implied).') . '<br>'
        . t('Multiple discounts must be separated by semicolons.') . '<br>'
        . t('Examples') . ':<ul>'
        . '<li><kbd>10</kbd> (' . t('one discount') . ')</li>'
        . '<li><kbd>15;20</kbd> (' . t('two discounts') . ')</li></ul>';
      Msg::error($error);
      return false;
    }
    
    function getMultiplierOptions(): array {
      $mults = explode(';', $this->autoPriceMultipliers);
      array_walk($mults, function(&$m) {
        $m = str_replace(',', '.', $m);
      });
      return array_combine($mults, $mults);
    }
    
    function getDefaultMultiplier(): string {
      if($this->autoPriceDefaultFirstMultiplier) {
        return array_values($this->getMultiplierOptions())[0];
      }
      return '';
    }
    
    function getDefaultDiscounts(): array {
      if($this->defaultDiscounts) {
        return explode(';', $this->defaultDiscounts);
      }
      return [];
    }
    
    function controlLinerDisplay($class): bool {
      return $this->{self::CONTROL_LINER_DISPLAY_PREFIX . $class};
    }
    
    function skipOnLinerDisplay(string $class, string $field): bool {
      return $this->controlLinerDisplay($class) 
        && !$this->{self::SHOW_IN_LINER_DISPLAY_PREFIX . $class . '_' . $field};
    }
    
    function controlSingleDisplay(string $class): bool {
      return $this->{self::CONTROL_SINGLE_DISPLAY_PREFIX . $class};
    }
    
    function skipOnSingleDisplay(string $class, string $field): bool {
      return $this->controlSingleDisplay($class) 
        && !$this->{self::SHOW_IN_SINGLE_DISPLAY_PREFIX . $class . '_' . $field};
    }
    
    function controlListDisplay(string $class): bool {
      return $this->{self::CONTROL_LIST_DISPLAY_PREFIX . $class};
    }
    
    function skipOnListDisplay(string $class, string $field): bool {
      return $this->controlListDisplay($class)
        && !$this->{self::SHOW_IN_LIST_DISPLAY_PREFIX . $class . '_' . $field};
    }
    
    function controlPrintDisplay(string $class): bool {
      return $this->{self::CONTROL_PRINT_DISPLAY_PREFIX . $class};
    }
        
    function skipOnPrintDisplay(string $class, string $field): bool {
      return $this->controlPrintDisplay($class)
        && !$this->{self::SHOW_IN_PRINT_DISPLAY_PREFIX . $class . '_' . $field};
    }
    
    function controlMailFaxDisplay(string $class): bool {
      return $this->{self::CONTROL_MAILFAX_DISPLAY_PREFIX . $class};
    }
        
    function skipOnMailFaxDisplay(string $class, string $field): bool {
      return $this->controlMailFaxDisplay($class)
        && !$this->{self::SHOW_IN_MAILFAX_DISPLAY_PREFIX . $class . '_' . $field};
    }
    
    function mailTemplate() {
      return $this->np_mail_template;
    }
        
    function faxTemplate() {
      return $this->np_fax_template;
    }
        
    function cardTemplate() {
      return $this->np_card_template;
    }
        
    function labelTemplate() {
      return $this->np_label_template;
    }
        
    private function treedSettings(): array {
      $settings = self::$settings;
      $sections = [];
      $parents = [];
      $children = [];
      foreach($settings as $setting_name => $setting_options) {
        $set = new Dynamic($setting_options);
        if($set->parent) {
          $parents[$set->class][$set->context][$setting_name] = $set;
        }
        else if($set->children) {
          $children[$set->class][$set->context][''][$setting_name] = $set;
        }
        else {
          $sections[$set->section][$setting_name] = $set;
        }
      }
      
      foreach($parents as $class => $contexts) {
        foreach($contexts as $context => $settings) {
          foreach($settings as $setting_name => $set) {
            $set->subsections = $children[$class][$context];
            $sections[$set->section][$setting_name] = $set;
          }
        }
      }
      
      return $sections;
    } 
    
    private function defaultSettings(): array {
      $default['useShortDateFormat'] = [
        'description' => t('show two-digits year and omit seconds when appropriate'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('dates'),
      ];

      $default['hideHours'] = [
        'description' => t('show only dates in lists (omit hours)'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('dates'),
      ];

      $default['showInlineDisplayButtons'] = [
        'description' => t('show inline display buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlineDeleteButtons'] = [
        'description' => t('show inline delete buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlineCloneButtons'] = [
        'description' => t('show inline clone buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlineUnloadButtons'] = [
        'description' => t('show inline load/unload buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlineRequestButtons'] = [
        'description' => t('show inline request buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlinePaymentButtons'] = [
        'description' => t('show inline payment buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlineBalanceButtons'] = [
        'description' => t('show inline balance buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['showInlineActivateDocumentButtons'] = [
        'description' => t('show inline activate document buttons'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('buttons'),
      ];

      $default['allowDailyLoads'] = [
        'description' => t('allow loading into the daily document'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('load/unload'),
      ];

      $default['allowAlternateUnloads'] = [
        'description' => t('allow unloading from alternate documents'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('load/unload'),
      ];

      $default['changeLimitWarning'] = [
        'description' => t('excessive loading/unloading warning limit'),
        'widget' => 'number',
        'step' => '1',
        'min' => '0',
        'value' => 12,
        'section' => t('load/unload'),
      ];

      $default['searchHistoryLimit'] = [
        'description' => t('max number of searches to remember'),
        'widget' => 'number',
        'step' => '1',
        'min' => '0',
        'value' => 50,
        'section' => t('load/unload'),
      ];

      $default['autoPriceMultipliers'] = [
        'description' => t('automatic price multipliers (separate different multipliers with a semicolon)'),
        'validator' => 'App\Utils\Settings::validateMultipliers',
        'cleaner' => function($value) {
                       return trim(
                         preg_replace('#\s+#', '', $value),
                       ';');
                     },
        'widget' => 'text',
        'value' => '2;2.2',
        'section' => t('autoprice'),
      ];

      $default['autoPriceDefaultFirstMultiplier'] = [
        'description' => t('use first multiplier by default'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('autoprice'),
      ];

      $default['autoPriceRounding'] = [
        'description' => t('rounding for selling prices after applying a multiplier'),
        'widget' => 'number',
        'value' => '0',
        'step' => '0.01',
        'min' => '0',
        'section' => t('autoprice'),
      ];

      $default['defaultDiscounts'] = [
        'description' => t('default discount values (separate discounts with a semicolon)'),
        'validator' => 'App\Utils\Settings::validateDiscounts',
        'cleaner' => function($value) {
                       return trim(
                         preg_replace('#\s+#', '', $value),
                       ';');
                     },
        'widget' => 'text',
        'value' => '5;10;15',
        'section' => t('discounts'),
      ];

      $default['defaultDiscountRounding'] = [
        'description' => t('rounding for selling prices after applying a discount'),
        'widget' => 'number',
        'value' => '0',
        'step' => '0.01',
        'min' => '0',
        'section' => t('discounts'),
      ];

      $default['enableItemGallery'] = [
        'description' => t('enable item gallery of images'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('images'),
      ];

      $default['enableRequestGallery'] = [
        'description' => t('enable request gallery of images'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('images'),
      ];

      $default['defaultPagerCount'] = [
        'description' => t('default elements per page'),
        'widget' => 'number',
        'min' => 10,
        'max' => 500,
        'value' => 25,
        'section' => t('pager'),
      ];

      $default['maxLogAgeInDays'] = [
        'description' => t('automatically delete log files older than this many days'),
        'widget' => 'number',
        'min' => 3,
        'max' => 365,
        'value' => 30,
        'section' => t('cleanup'),
      ];
      
      return array_merge($default, $this->dynamicSettings());
    }
    
    private function dynamicSettings(): array {
      $classes = Factory::getDefaultSchema();
      $dynamic = array_merge(
        $this->dynamicLinerDisplaySettings($classes),
        $this->dynamicSingleDisplaySettings($classes),
        $this->dynamicListDisplaySettings($classes),
        $this->dynamicPrintDisplaySettings($classes),
        $this->dynamicMailFaxDisplaySettings($classes),
        $this->debugSettings(),
        $this->printSettings()
      );
      return $dynamic;
    }
    
    function dynamicLinerDisplaySettings(array $classes): array {
      $dynamic = [];
      foreach($classes as $class => $fields) {
        $dynamic[self::CONTROL_LINER_DISPLAY_PREFIX . $class] = [
          'section' => t('displays of %s', t($class)),
          'description' => t('control fields in one liner display'),
          'widget' => 'checkbox',
          'value' => false,
          
          'class' => $class,
          'context' => 'liner-display',
          'parent' => true,
        ];
        foreach($fields as $fieldname => $set) {
          unused($set);
          $dynamic[self::SHOW_IN_LINER_DISPLAY_PREFIX . $class . '_' . $fieldname] = [
            'section' => t($class) . ': ' . t('show in one liner'),
            'description' => t($fieldname),
            'widget' => 'checkbox',
            'value' => true,
            
            'field' => $fieldname,
            'class' => $class,
            'context' => 'liner-display',
            'children' => true,
          ];
        }
      }
      return $dynamic;
    }
    
    function dynamicSingleDisplaySettings(array $classes): array {
      $dynamic = [];
      foreach($classes as $class => $fields) {
        $dynamic[self::CONTROL_SINGLE_DISPLAY_PREFIX . $class] = [
          'section' => t('displays of %s', t($class)),
          'description' => t('control fields in single display'),
          'widget' => 'checkbox',
          'value' => false,
          
          'class' => $class,
          'context' => 'single-display',
          'parent' => true,
        ];
        foreach($fields as $fieldname => $set) {
          unused($set);
          $dynamic[self::SHOW_IN_SINGLE_DISPLAY_PREFIX . $class . '_' . $fieldname] = [
            'section' => t($class) . ': ' . t('show in single display'),
            'description' => t($fieldname),
            'widget' => 'checkbox',
            'value' => true,
            
            'field' => $fieldname,
            'class' => $class,
            'context' => 'single-display',
            'children' => true,
          ];
        }
      }
      return $dynamic;
    }
    
    function dynamicListDisplaySettings(array $classes): array {
      $dynamic = [];
      foreach($classes as $class => $fields) {
        $dynamic[self::CONTROL_LIST_DISPLAY_PREFIX . $class] = [
          'section' => t('displays of %s', t($class)),
          'description' => t('control fields in list display'),
          'widget' => 'checkbox',
          'value' => false,
          
          'class' => $class,
          'context' => 'list-display',
          'parent' => true,
        ];
        foreach($fields as $fieldname => $set) {
          unused($set);
          $dynamic[self::SHOW_IN_LIST_DISPLAY_PREFIX . $class . '_' . $fieldname] = [
            'section' => t($class) . ': ' . t('show in list display'),
            'description' => t($fieldname),
            'widget' => 'checkbox',
            'value' => true,
            
            'field' => $fieldname,
            'class' => $class,
            'context' => 'list-display',
            'children' => true,
          ];
        }
      }
      return $dynamic;
    }
    
    function dynamicPrintDisplaySettings(array $classes): array {
      $dynamic = [];
      foreach($classes as $class => $fields) {
        $dynamic[self::CONTROL_PRINT_DISPLAY_PREFIX . $class] = [
          'section' => t('displays of %s', t($class)),
          'description' => t('control fields in print display'),
          'widget' => 'checkbox',
          'value' => false,
          
          'class' => $class,
          'context' => 'print-display',
          'parent' => true,
        ];
        foreach($fields as $fieldname => $set) {
          unused($set);
          $dynamic[self::SHOW_IN_PRINT_DISPLAY_PREFIX . $class . '_' . $fieldname] = [
            'section' => t($class) . ': ' . t('show in print display'),
            'description' => t($fieldname),
            'widget' => 'checkbox',
            'value' => true,
            
            'field' => $fieldname,
            'class' => $class,
            'context' => 'print-display',
            'children' => true,
          ];
        }
      }
      return $dynamic;
    }
    
    function dynamicMailFaxDisplaySettings(array $classes): array {
      $dynamic = [];
      foreach($classes as $class => $fields) {
        $dynamic[self::CONTROL_MAILFAX_DISPLAY_PREFIX . $class] = [
          'section' => t('displays of %s', t($class)),
          'description' => t('control fields in mailfax display'),
          'widget' => 'checkbox',
          'value' => false,
          
          'class' => $class,
          'context' => 'mailfax-display',
          'parent' => true,
        ];
        foreach($fields as $fieldname => $set) {
          unused($set);
          $dynamic[self::SHOW_IN_MAILFAX_DISPLAY_PREFIX . $class . '_' . $fieldname] = [
            'section' => t($class) . ': ' . t('show in mailfax display'),
            'description' => t($fieldname),
            'widget' => 'checkbox',
            'value' => true,
            
            'field' => $fieldname,
            'class' => $class,
            'context' => 'mailfax-display',
            'children' => true,
          ];
        }
      }
      return $dynamic;
    }
    
    function debugSettings(): array {
      $debug = [];
      
      $debug['debugPost'] = [
          'section' => t('debug'),
          'description' => t('debug POST'),
          'widget' => 'checkbox',
          'value' => false,
      ];

      $debug['debugFind'] = [
          'section' => t('debug'),
          'description' => t('debug FIND'),
          'widget' => 'checkbox',
          'value' => false,
      ];

      $debug['debugQuery'] = [
          'section' => t('debug'),
          'description' => t('debug QUERY'),
          'widget' => 'checkbox',
          'value' => false,
      ];

      $debug['debugJavascript'] = [
          'section' => t('debug'),
          'description' => t('debug JS'),
          'widget' => 'checkbox',
          'value' => false,
      ];

      $debug['disableResourcesCaching'] = [
          'section' => t('debug'),
          'description' => t('disable resources caching'),
          'widget' => 'checkbox',
          'value' => false,
      ];

      $debug['recreateCacheContinuously'] = [
          'section' => t('debug'),
          'description' => t('recreate caches at every page request'),
          'widget' => 'checkbox',
          'value' => false,
      ];

      return $debug;
    }
    
    function printSettings(): array {
      $prints = [];
      
      $prints['enableAutoPrint'] = [
        'description' => t('invoke print window automatically'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('prints'),
      ];

      $prints['printRequestGallery'] = [
        'description' => t('print requests gallery'),
        'widget' => 'checkbox',
        'value' => false,
        'section' => t('prints'),
      ];

      $prints['groupRequestsByProvider'] = [
        'description' => t('group requests by provider'),
        'widget' => 'checkbox',
        'value' => true,
        'section' => t('prints'),
      ];

      $prints['hidePrintRequestsHeaders'] = [
        'description' => t('hide requests headers'),
        'widget' => 'checkbox',
        'value' => true,
        'section' => t('prints'),
      ];

      $prints['home_company_id'] = [
          'section' => t('prints'),
          'description' => t('home company id'),
          'widget' => 'number',
          'value' => 1,
      ];

      $prints['home_company_prefix'] = [
          'section' => t('prints'),
          'description' => t('home company prefix'),
          'widget' => 'text',
          'value' => t('home'),
      ];

      $prints['customer_number_prefix'] = [
          'section' => t('prints'),
          'description' => t('customer number prefix'),
          'widget' => 'text',
          'value' => 'CN',
      ];

      $prints['custom_item_prefix'] = [
          'section' => t('prints'),
          'description' => t('custom item prefix'),
          'widget' => 'text',
          'value' => 'CI',
      ];

      $prints['np_card_template'] = [
          'section' => t('prints'),
          'description' => t('card template') . $this->markdownNotice(),
          'widget' => 'textarea',
          'value' => '',
      ];

      $prints['np_mail_template'] = [
          'section' => t('prints'),
          'description' => t('mail template') . $this->markdownNotice(),
          'widget' => 'textarea',
          'value' => '',
      ];

      $prints['np_fax_template'] = [
          'section' => t('prints'),
          'description' => t('fax template') . $this->markdownNotice(),
          'widget' => 'textarea',
          'value' => '',
      ];

      $prints['np_label_template'] = [
          'section' => t('prints'),
          'description' => t('label template') . $this->markdownNotice(),
          'widget' => 'textarea',
          'value' => '',
      ];

      return $prints;
    }
    
    function markdownNotice() {
      $output = ' - ';
      $output .= t('Markdown syntax with [record.field] replacements') 
        . ' - <a href="http://commonmark.org/help/" target="_blank" class="nocover">'
        . t('Markdown example')
        . '</a>';
      return $output;
    }
    
  }
