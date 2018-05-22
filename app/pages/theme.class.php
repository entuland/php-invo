<?php

  namespace App\Pages;
  
  use App\Utils\Format;
  use App\Utils\Msg;
  use App\Router;
  
  class Theme {
    const THEMES_FOLDER = 'css/dynamic/themes';
    const OUTPUT_FOLDER = 'files/css';
    const MANUAL_MARKERS_FILE = 'css/dynamic/source/markers.manual.css';
    const AUTO_MARKERS_FILE = 'css/dynamic/source/markers.auto.php';
    const SKIP_FILE_VALIDATION = true;
    const SILENT = true;
    
    static function main() {
      setTitle(t('Theme manager'));
      $output = self::processPost();
      return $output . self::themeManager();
    }
    
    private static function themeManager() {
      Ob_start();
      $themes = self::getExistingThemes();
      $rows = [];
      foreach($themes as $filename) {
        $rows[] = self::manageThemeRow($filename);
      }
      ?>
<p><?= t(
  'Press F7 keyboard button in any page to display the rapid theme editing '
  . 'dialog. The dialog will show theme options for the element under the '
  . 'mouse cursor and all of its ancestors. Changes made in that dialog will '
  . 'be immediately saved into the currently active theme. The F7 shortcut '
  . 'is disabled in these theme manager pages.'
) ?>
<table id="theme-manager">
  <tbody>
    <tr>
      <th><?= t('Theme') ?></th>
      <th><?= t('Activation') ?></th>
      <th><?= t('Editing') ?></th>
      <th><?= t('Deletion') ?></th>
      <th><?= t('Cloning') ?></th>
    </tr>
    <?= implode($rows) ?>
  </tbody>
</table>
      <?php
      return Ob_get_clean();
    }
    
    private static function manageThemeRow($filename) {
      $theme_name = self::themeName($filename);
      if(!$theme_name) {
        return;
      }
      $active = $theme_name === self::currentThemeName();
      $default = $theme_name === 'default';
      Ob_start();
?>
<tr>
  <td><?= $theme_name ?></td>
  <td><?= self::renderActionForm('activate', $theme_name, $active, $default) ?></td>
  <td><?= self::renderActionForm('edit', $theme_name, $active, $default) ?></td>
  <td><?= self::renderActionForm('delete', $theme_name, $active, $default) ?></td>
  <td><?= self::renderActionForm('clone', $theme_name, $active, $default) ?></td>
</tr>
<?php
      return Ob_get_clean();
    }
    
    private static function renderActionForm($action, $theme_name, $active, $default) {
      t('edit');
      t('delete');
      t('activate');
      t('clone');
      $valid_actions = [
        'edit',
        'delete',
        'activate',
        'clone',
      ];
      $input = '';
      
      $invalid_action = !in_array($action, $valid_actions);
      $skip_delete = $action === 'delete' && ($active || $default);

      if($invalid_action || $skip_delete) {
        return '-';
      }
      
      $skip_activate = $action === 'activate' && $active;
      if($skip_activate) {
        return t('Active');
      }
      
      if($action === 'clone') {
        $input = 
          '<input type="text" name="clone-name" placeholder="'
          . t('Clone name') . '">';
      }
        
      Ob_start();
      ?>
<form class="manage-theme" method="POST"><input type="hidden" name="theme-name" 
value="<?= $theme_name ?>"><?= $input ?><button type="submit" name="action" 
value="<?= $action ?>"><?= t($action) ?></button></form>
      <?php
      return Ob_get_clean();
    }
    
    private static function getExistingThemes() {
      $entries = scandir(self::THEMES_FOLDER);
      $filenames = [];
      foreach($entries as $entry) {
        if(!preg_match('#theme\.(.+)\.json#', $entry)) {
          continue;
        }
        $filenames[] = self::THEMES_FOLDER . '/' . $entry;
      }
      return $filenames;
    }
    
    private static function themeName($filename) {
     if(self::validThemeFile($filename)) {
        return preg_replace('#^.*theme\.(.+)\.json$#', '$1', $filename);
      }
      return false;
    }
    
    static function getThemeNames() {
      $filenames = self::getExistingThemes();
      array_walk($filenames, function(&$filename) {
        $filename = self::themeName($filename);
      });
      return $filenames;
    }
    
    private static function validThemeFile($filename) {
      $base = realpath(self::THEMES_FOLDER);
      if(strpos(realpath($filename), $base) !== 0 || !is_file($filename)) {
        return false;
      }
      return $filename;
    }
    
    private static function themeFile($theme_name, $skip_validation = false) {
      $filename = self::THEMES_FOLDER . '/theme.' . $theme_name . '.json';
      if($skip_validation) {
        return $filename;
      }
      if(self::validThemeFile($filename)) {
        return $filename;
      }
      return false;
    }
    
    private static function themeOutputFile($theme_name) {
      return self::OUTPUT_FOLDER . '/theme.' . $theme_name . '.generated.css';
    }
    
    static function currentThemeName() {
      $theme_name = filter_input(INPUT_COOKIE, 'current-theme');
      if(self::themeFile($theme_name)) {
        return $theme_name;
      }
      return 'default';
    }
    
    static function setThemeMarker($theme_name, $marker, $value) {
      $markers = self::loadTheme($theme_name);
      $flat = [];
      $markers[$marker]['value'] = $value;
      foreach($markers as $marker => $settings) {
        $flat[$marker] = $settings['value'];
      }
      return self::saveMarkersAsTheme($flat, $theme_name);
    }
    
    static function currentThemeCss() {
      $theme_name = self::currentThemeName();
      $filename = self::themeOutputFile($theme_name);
      if(!file_exists($filename)) {
        $filename = self::recreateThemeCss($theme_name); 
      }
      return $filename;
    }
    
    static function loadTheme($theme_name) {
      self::recreateMarkers();
      $markers = self::getStoredMarkers();
      $default = self::addDefaultSettings($markers);
      $filename = self::themeFile($theme_name);
      return self::addCustomSettings($default, $filename);
    }

    static function loadCurrentTheme() {
      return self::loadTheme(self::currentThemeName());
    }
    
    static function resetCssCache() {
      $theme_name = self::currentThemeName();
      if(self::recreateThemeCss($theme_name)) {
        return $theme_name;
      }
    }
    
    private static function recreateThemeCss($theme_name) {
      $markers = self::loadTheme($theme_name);
      $filename = self::themeOutputFile($theme_name);
      $output = file_get_contents(self::MANUAL_MARKERS_FILE);
      foreach($markers as $marker => $settings) {
        $output = preg_replace("#$marker#", $settings['value'] . ' ' . "/* $marker */", $output);
      }
      $output = <<<CSS
  /* THIS IS AN AUTOMATICALLY GENERATED FILE, DO NOT EDIT MANUALLY! */
$output    
CSS;
      if(false !== file_put_contents($filename, $output)) {
        return $filename;
      }
      return false;
    }
    
    private static function addCustomSettings($markers, $filename) {
      if(!self::validThemeFile($filename)) {
        Msg::msg(t('Custom theme not found, using default theme'));
        $filename = self::themeFile('default');
        if(!$filename) {
          Msg::msg(t('Default theme not found, using basic settings'));
          return $markers;
        }
      }
      $assoc = true;
      $custom = json_decode(file_get_contents($filename), $assoc);
      if(is_null($custom)) {
        return $markers;
      }
      $sorted_custom = [];
      foreach($custom as $marker => $value) {
        $sorted_custom[self::sortMarkerParts($marker)] = $value;
      }
      foreach($markers as $marker => &$settings) {
        $sorted_marker = self::sortMarkerParts($marker);
        if(array_key_exists($sorted_marker, $sorted_custom)) {
          $settings['value'] = $sorted_custom[$sorted_marker];
        }
      }
      return $markers;
    }
    
    private static function sortMarkerParts($marker) {
        $parts = explode('_', $marker);
        sort($parts);
        return implode('_', $parts);
    }
    
    static function activateTheme($theme_name, $silent = false) {
      setcookie('current-theme', $theme_name);
      $filename = self::themeOutputFile($theme_name);
      if(!file_exists($filename)) {
        $filename = self::recreateThemeCss($theme_name); 
      }
      if(!$silent) {
        Msg::msg(t('Theme %s activated', $theme_name));
      }
      return $filename;
    }
    
    private static function processPost() {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $action = getPOST('action');
        $theme_name = getPOST('theme-name');
        $theme_file = self::themeFile($theme_name);
        if(!$theme_file) {
          return;
        }
        switch($action) {
          case 'activate':
            self::activateTheme($theme_name);
            Router::redirect(config('publicbase') . '/theme');
          case 'edit':
            return self::renderEditForm($theme_name);
          case 'delete':
            return self::processDeletePOST($theme_name);
          case 'save':
            return self::processSavePOST($theme_name);
          case 'clone':
            return self::processClonePOST($theme_name);
        }
      }
    }
    
    private static function processDeletePost($theme_name) {
      if(unlink(self::themeFile($theme_name))) {
        Msg::msg(t('Theme %s deleted successfully', $theme_name));
      }
      else {
        Msg::error(t('Unable to delete theme %s', $theme_name));
      }
    }

    private static function processClonePost($theme_name) {
      $clone_name = preg_replace('#[^\w\d_-]+#', '', getPOST('clone-name'));
      if(!$clone_name || self::themeFile($clone_name)) {
        Msg::error(t('Unable to create theme %s, duplicated or invalid name', $clone_name));
        return;  
      }
      $source_filename = self::themeFile($theme_name);
      $clone_filename = self::themeFile($clone_name, self::SKIP_FILE_VALIDATION);
      if(copy($source_filename, $clone_filename)) {
        Msg::msg(t('Theme %s cloned as %s', $theme_name, $clone_name));
      }
      else {
        Msg::error(t('Unable to copy %s into %s', $source_filename, $clone_filename));
      }
    }
    
    private static function saveMarkersAsTheme($markers, $theme_name) {
      $theme_file = self::themeFile($theme_name, self::SKIP_FILE_VALIDATION);
      if(file_put_contents($theme_file, json_encode($markers, JSON_PRETTY_PRINT))) {
        self::recreateThemeCss($theme_name);
        return true;
      }
      return false;
    }

    private static function processSavePost($theme_name) {
      $markers = [];
      foreach($_POST as $marker => $value) {
        if($marker === 'theme-name') {
          continue;
        }
        $markers[self::sortMarkerParts($marker)] = $value;  
      }
      if(self::saveMarkersAsTheme($markers, $theme_name)) {
        Msg::msg(t('Theme %s saved', $theme_name));
      }
      else {
        Msg::error(t('Theme %s save failure', $theme_name));
      }
    }
    
    private static function addDefaultSettings($markers) {
      $values = [
        'BACKGROUND' => '#ffffff', 
        'COLOR' => '#000000', 
        'BORDER' => '#000000', 
        'OPACITY' => '1',
      ];
      foreach($markers as $marker => &$settings) {
        $parts = explode('_', $marker);
        foreach($values as $widget => $value) {
          if(in_array($widget, $parts)) {
            $settings['type'] = $widget === 'OPACITY' ? 'number' : 'color';
            $settings['value'] = mb_strtolower($value);
          }
        }
      }
      return $markers;
    }
    
    private static function markerRow($marker, $settings) {
      Ob_start()
      ?>
        <tr>
          <td>
            <input
              type="<?= $settings['type'] ?>" 
              name="<?= $marker ?>"
              value="<?= $settings['value'] ?>"
              <?php if($settings['type'] === 'number') { ?>
              step="0.01"
              min="0"
              max="1"
              <?php } ?>
            > <?= $settings['description'] ?>
          </td>
        </tr>
      <?php
      return Ob_get_clean();
    }
    
    private static function renderEditForm($theme_name) {
      $custom = self::loadTheme($theme_name);
      $active = $theme_name === self::currentThemeName();
      uasort($custom, function($a, $b) {
        return strcmp($a['description'], $b['description']);
      });
      Ob_start();
      ?>
<form class="theme" method="POST" data-theme-name="<?= $theme_name ?>">
  <input type="hidden" name="theme-name" value="<?= $theme_name ?>">
  <table>
    <caption>
      <?= t('Edit theme %s', $theme_name) 
          . ($active?' ('.t('Current theme').')':'') ?>
    </caption>
    <tbody>
      <?php
        foreach($custom as $marker => $settings) {
          echo self::markerRow($marker, $settings);
        }
      ?>
      <tr>
        <td>
          <button class="button save"
                  type="submit" 
                  name="action" 
                  value="save"
          ><?= t('Save theme %s', $theme_name) ?></button>
          <?php if($theme_name !== 'default') { ?>
          <button class="button delete"
                  type="submit" 
                  name="action" 
                  value="delete"
          ><?= t('Delete theme %s', $theme_name) ?></button>
          <?php } ?>
        </td>
      </tr>
    </tbody>
  </table>
</form>
      <?php
      return Ob_get_clean();
    }
    
    private static function getStoredMarkers() {
      $markers = [];
      require self::AUTO_MARKERS_FILE;      
      asort($markers);
      return $markers;
    }
    
    private static function recreateMarkers() {
      $markers = self::extractMarkers();
      self::saveConfig($markers);
    }
    
    private static function saveConfig($markers) {
      $lines = [];
      $lines[] = '<?php';
      $lines[] = '// DO NOT EDIT MANUALLY!';
      $lines[] = '// THIS IS AN AUTOMATICALLY GENERATED FILE';
      $lines[] = '$markers = [';
      foreach($markers as $marker => $selector) {
        $words = explode('_', $marker);
        $t_words = Format::wrap("t('", $words, "')");
        $description = implode(".' '.", $t_words);
        $lines[] = "  '$marker' => [";
        $lines[] = "    'selector' => '$selector',";
        $lines[] = "    'description' => $description,";
        $lines[] = "   ],";
      }
      $lines[] = '];';
      return file_put_contents(self::AUTO_MARKERS_FILE, implode(PHP_EOL, $lines));
    }
    
    private static function extractMarkersOld() {
      //self::extractMarkersAndSelectors();
      $filename = self::MANUAL_MARKERS_FILE;
      $content = file_get_contents($filename);
      $matches = [];
      preg_match_all('#[A-Z-]*(_[A-Z-]*){1,}#', $content, $matches);
      return $matches[0];
    }
    
    private static function extractMarkers() {
      $filename = self::MANUAL_MARKERS_FILE;
      $content = file_get_contents($filename);
      $pure_content = preg_replace('#/\*.+?\*/#ms', '', $content);
      $lines = preg_split('#\r\n|\r|\n#', $pure_content);
      $current_selector = '';
      $current_rules = [];
      $declarations = [];
      $add_to_selector = function($text) use(&$current_selector) {
        $current_selector .= trim(str_replace('{', '', $text)) . ' ';
      };
      $add_rule = function($rule) use(&$current_rules) {
        $current_rules[] = $rule;
      };
      $close_selector = function($text) 
                        use(&$current_selector, &$current_rules, &$declarations) {
        $declarations[$current_selector] = $current_rules;
        $current_rules = [];
        $current_selector = '';
      };
      foreach($lines as $line) {
        $line = trim($line);
        if(!$line) {
          continue;
        }
        switch(substr($line, -1, 1)) {
          case ',':
          case '{':
            $add_to_selector($line);
            break;
          case ';':
            $add_rule($line);
            break;
          case '}':
            $close_selector($line);
            break;
        }
      }
      $markers = [];
      foreach($declarations as $selector => $rules) {
        foreach($rules as $rule) {
          $matches = [];
          preg_match_all('#[A-Z-]*(_[A-Z-]*){1,}#', $rule, $matches);
          foreach($matches[0] as $marker) {
            if(!array_key_exists($marker, $markers)) {
              $markers[$marker] = trim($selector);
            }
            else {
              $markers[$marker] .= ', ' . trim($selector);
            }
          }
        }
      }
      return $markers;
    }
  }