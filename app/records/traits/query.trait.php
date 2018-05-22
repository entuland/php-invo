<?php

  namespace App\Records\Traits;
  
  use App\Records\Factory;
  use App\Records\Record;
  use App\Utils\Pager;
  use App\Utils\Field;
  use App\Utils\Msg;
  use App\Utils\Format;
  use App\Utils\Gallery;
  use App\Utils\Settings;
  use App\Query\QueryRunner;
    
  trait Query {
    
    function gender() {
      return Factory::classGender($this->className());
    }
    
    function anythingNew($values) {
      foreach($values as $field_name => $value) {
        if(!in_array($field_name, $this->fields())) {
          Msg::error(t(
            'Field %s not found in %s',
            t($field_name),
            $this->identifier(Record::ADD_LINK)
          ));
        }
        else if($field_name === 'modified') {
          continue;
        }
        else if($value !== $this->$field_name) {
          return true;
        }
      }
      return false;
    }
    
    function validColumn($col) {
      if(array_key_exists($col, $this->settings())) {
        return true;
      }
      return false;
    }
    
    function childrenHelper($desired_child_class = '') {
      $children_classes = $this->getChildren();
      $count = count($children_classes);
      
      $output = '';
      if(!$count) {
        return $output;
      } else if($count > 1) {
        $output .= $this->getChildrenButtons();
        if(!$desired_child_class) {
          return $output;
        }
      }
      
      $child_class = array_keys($children_classes)[0];
      $collapsible_open = false;
      if(array_key_exists($desired_child_class, $children_classes)) {
        $child_class = $desired_child_class;
        $collapsible_open = true;
      }
      $child_ids = $children_classes[$child_class];
      
      if(!count($child_ids)) {
        return '';
      }
      
      $collapsible_title = t($child_class.'s') . ' - ' . $this->oneLinerDisplay();
      
      $children_class_instance = Factory::newInstance($child_class);
      $children_class_instance->addContext(Record::LIST_CONTEXT);
      $collapsible_content = $children_class_instance->renderIDS($child_ids);

      $affect_pager = true;
      $collapsible_id = null;
      $output .= Format::collapsible(
        $collapsible_content, 
        $collapsible_title, 
        $collapsible_id,
        $collapsible_open,
        $affect_pager
      );
      $output .= Pager::renderControls($this->childrenCount($desired_child_class));
      return $output;
    }
    
    function getIntroButtons() {
      $class = $this->className();
      $new = ct('male', 'new');
      if($this->gender() === 'female') {
        $new = ct('female', 'new');
      }
      $newText = t('Create %s %s', $new, t($class));
      $searchText = t('Search %s', t($class));
      $base = config('publicbase');
      $buttons = <<<HTML
<a class="button" href="$base/$class/new">$newText</a> 
<a class="button" href="$base/$class/search">$searchText</a> 
HTML;
      return $buttons;
    }
    
    function getChildrenButtons() {
      $children_buttons = [];
      $children = $this->getChildren();
      foreach($children as $class => $ids) {
        if(count($ids)) {
          $children_url = $this->displayURL() . '/children/' . $class;
          $children_text = t($class . 's') . ' (' . count($ids) . ')';
          $base = config('publicbase');
          $children_buttons[] = <<< HTML
  <a class="button" href="$base/$children_url">$children_text</a>
HTML;
          }
      }
      if(count($children_buttons)) {
        return '<div>' . t('Children records') . '</div>' . implode($children_buttons);
      }
      return '';
    }
    
    function resolvedFieldPairs() {
      $result = [];
      $fields = array_keys($this->settings());
      foreach($fields as $field_name) {
        $result[$field_name] = [
          'displayed' => $this->resolveFieldValue($field_name),
          'stored' => $this->$field_name,
        ];
      }
      return $result;
    }
    
    function resolveFieldValue($field_name) {
      $settings = $this->settings()[$field_name];
      $value = $this->$field_name;
      $field = new Field($settings, $value, $this->className(), $field_name);
      return $field->displayValue(Field::OMIT_LINK, Field::ADD_PREFIX);
    }
    
    function getLatest() {
      $sql = "SELECT MAX(ID) FROM `{$this->table_name}`";
      $result = QueryRunner::runQueryColumn($sql);
      if(is_array($result) && count($result)) {
        return Factory::newInstance($this->className(), $result[0]);
      }
      return false;
    }
    
    function getReferences() {
      $references = [];
      foreach($this->settings() as $field_name => $settings) {
        if(array_key_exists('reference', $settings)) {
          $references[$settings['reference']] = $field_name;
        }
      }
      return $references;
    }
    
    function reference($table_name) {
      $references = $this->getReferences();
      if(array_key_exists($table_name, $references)) {
        return $references[$table_name];
      }
      return false;
    }
    
    function getDependencies() {
      static $dep = null;
      if(is_null($dep)) {
        $dep = [];
        $classes = Factory::validClasses();
        foreach($classes as $class) {
          $inst = Factory::newInstance($class);
          $fields = $inst->settings();
          foreach($fields as $field_name => $settings) {
            if(array_key_exists('reference', $settings)
                && $settings['reference'] == $this->className()) {
              $dep[$class][] = $field_name;
            }
          }
        }
      }
      return $dep;
    }
    
    function getChildrenFindParams($class, $field_name) {
      unused($class);
      return [
        $field_name => $this->id
      ];
    }
    
    function getChildren() {
      $pager_start = $pager_count = null;
      if(is_null($this->children)) {
        $this->children = []; 
        $dep = $this->getDependencies();
        foreach($dep as $class => $fields) {
          $inst = Factory::newInstance($class);
          $this->children[$class] = [];
          foreach($fields as $field_name) {
            $params = $this->getChildrenFindParams($class, $field_name);
            $ids = $inst->find($params, $pager_start, $pager_count)['ids'];
            $this->children[$class] = array_merge($this->children[$class], $ids);
          }
        }
      }
      return $this->children;
    }

    function childrenCount() {
      if(is_null($this->children_count)) {
        $dep = $this->getDependencies();
        $this->children_count = 0; 
        foreach($dep as $class => $fields) {
          $inst = Factory::newInstance($class);
          foreach($fields as $field_name) {
            $params = $this->getChildrenFindParams($class, $field_name);
            $result = $inst->find($params);
            $this->children_count += $result['count'];
          }
        }
      }
      return $this->children_count;
    }
    
    function galleryEnabled() {
      $settings = new Settings();
      $property_name = 'enable' . ucfirst($this->className()) . 'Gallery';
      return $this->id && $settings->$property_name;
    }
    
    function printImagesEnabled() {
      $settings = new Settings();
      $property_name = 'print' . ucfirst($this->className()) . 'Gallery';
      return $this->galleryEnabled() && $settings->$property_name;
    }
    
    function getGallery() {
      return new Gallery($this->className() . '-' . Gallery::padNumber($this->id));
    }
    
    function getImageItems() {
      if(!$this->galleryEnabled()) {
        return [];
      }
      return $this->getGallery()->getGalleryItems();
    }
    
  }