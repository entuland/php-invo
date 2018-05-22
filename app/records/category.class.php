<?php

  namespace App\Records;

  use App\Utils\Format;
  use App\Query\QueryRunner;

  class Category extends Record {

    function __construct($id = null) {
      $this->table_name = 'category';
      $this->values['id'] = $id;
      $this->reload();
    }

    function save() {
      if($this->category) {
        $this->ensureParents($this->category);
      }
      return parent::save();
    }

    static function getExplicitDiscounts() {
      $sql = "
        SELECT 
          category, discount
        FROM 
          category 
        WHERE 
          category LIKE '%\%%' 
        AND
          category NOT LIKE '%|%'";
      return QueryRunner::runQuery($sql, [], \PDO::FETCH_KEY_PAIR);
    }
    
    static function getFixedPrices() {
      $sql = "
        SELECT 
          category, (fixedprice/100)
        FROM 
          category 
        WHERE 
          IFNULL(fixedprice, 0) > 0 
        AND
          category NOT LIKE '%|%'";
      return QueryRunner::runQuery($sql, [], \PDO::FETCH_KEY_PAIR);
    }
    
    static function getSpecials() {
      $sql = "
        SELECT 
          id, category
        FROM 
          category 
        WHERE 
          (category LIKE '%\%%' OR IFNULL(fixedprice, 0) > 0) 
        AND
          category NOT LIKE '%|%'";
      return QueryRunner::runQuery($sql, [], \PDO::FETCH_KEY_PAIR);
    }

    static function specialsDropdown($class, $id) {
      $specials = self::getSpecials();
      ob_start();
?>
<select class="never-dirty" onchange='rapidEdit.applySpecialCategory(this, <?= json_encode($class) ?>, <?= json_encode($id) ?>);'>
  <option value="">--</option>
  <?= implode('', Format::wrap('<option>', $specials, '</option>')) ?>
</select>
<?php
      return ob_get_clean();
    }
    
    static function byName($category) {
      return self::quickSave($category);
    }

    static function getDiscounts($category) {
      $cats = explode('|', $category);
      $explicit = self::getExplicitDiscounts();
      $discounts = [];
      foreach($cats as $cat) {
        $discount = $explicit[$cat] ?? 0;
        if($discount) {
          $discounts[] = [
            'name' => $cat,
            'percent' => $discount,
          ];
        }
      }
      $discounts[] = [
        'name' => $category,
        'percent' => self::byName($category)->discount,
      ];
      foreach(self::getParents($category) as $parent) {
        $discounts[] = [
          'name' => $parent,
          'percent' => self::byName($parent)->discount,
        ];
      }
      $filtered_discounts = array_filter($discounts,
                                         function($discount) {
        return $discount['percent'];
      });
      return array_values($filtered_discounts);
    }

    static function getParents($category) {
      $parts = explode('|', $category);
      array_walk($parts, function(&$part) {
        $part = trim($part);
      });
      if(!count($parts)) {
        return [];
      }
      $parents = [];
      while(count($parts) - 1) {
        array_pop($parts);
        $parents[] = implode('|', $parts);
      }
      return $parents;
    }

    static function quickSave($category) {
      $cat = Factory::newCategory();
      if(!is_string($category) || !trim($category)) {
        return $cat;
      }
      $uniques = $cat->uniqueValues();
      if(in_array($category, $uniques)) {
        $id = array_search($category, $uniques);
        return Factory::newCategory($id);
      }
      $cat->category = $category;
      $cat->discount = 0;
      $cat->selfLog('auto-pre-create');
      $cat->save();
      return $cat;
    }

    function ensureParents($category) {
      foreach(self::getParents($category) as $parent) {
        self::quickSave($parent);
      }
    }

    function getChildrenFindParams($class, $field_name) {
      if($class === $this->className()) {
        return [$field_name => $this->category . '|%'];
      }
      return [$field_name => $this->category . '%'];
    }

    function getIntroButtons() {
      $output = '';
      $parent_buttons = [];
      $parents = self::getParents($this->category);
      foreach(array_reverse($parents) as $parent) {
        $parent_instance = self::byName($parent);
        $parent_url = $parent_instance->editURL();
        $parent_text = $parent;
        $base = config('publicbase');
        $parent_buttons[] = <<< HTML
  <a class="button" href="$base/$parent_url">$parent_text</a>
HTML;
      }
      if(count($parent_buttons)) {
        $output .= '<div>' . t('Upper categories') . '</div>' . implode($parent_buttons);
      }
      return parent::getIntroButtons() . $output;
    }

    function getDependencies() {
      static $dep = null;
      if(is_null($dep)) {
        $dep = [];
        $classes = Factory::validClasses();
        foreach($classes as $class) {
          $inst = Factory::newInstance($class);
          if(in_array('category', $inst->fields())) {
            $dep[$class][] = 'category';
          }
        }
      }
      return $dep;
    }

    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'category' => [
          'widget' => 'text',
          'required' => true,
          'schema' => self::varcharSchema(128),
          'autocomplete' => true,
        ],
        'discount' => [
          'widget' => 'number',
          'min' => 0,
          'max' => 100,
          'postfix' => '%',
          'schema' => self::tinyintSchema(),
        ],
        'fixedprice' => [
          'widget' => 'number',
          'prefix' => '€',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'multiplier' => [
          'widget' => 'number',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }

  }
  