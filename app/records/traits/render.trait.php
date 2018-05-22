<?php
  namespace App\Records\Traits;
  
  use App\Records\Record;
  use App\Records\Factory;
  use App\Utils\Pager;
  use App\Utils\Field;
  use App\Utils\Settings;
  use App\Utils\Msg;
  use App\Utils\Icons;
  
  trait Render {
    
    function childrenRoute($params) {
      return $this->displayRoute(array_pop($params));
    }
    
    function displayRoute($desired_child_class = '') {
      $class = $this->className();
      setTitle(t('Display') . ' ' . t($class) . ' ' . $this->id);
      $output = $this->renderDisplay();
      return $this->getIntroButtons()
              . $output
              . $this->childrenHelper($desired_child_class);
    }
    
    function listRoute() {
      $this->setContext(Record::LIST_CONTEXT);
      $class = $this->className();
      setTitle(t('List') . ' ' . t($class . 's'));
      return $this->getIntroButtons() . $this->renderList();
    }
    
    function renderList($find_result_override = false, $caption = false) {
      if($find_result_override) {
        $find_result = $find_result_override;
      }
      else {
        $find_result = $this->find([], Pager::$start, Pager::$count);
      }
      $rendered_ids = $this->renderIDS(
        $find_result['ids'],
        [], // skip fields
        true, // add links
        $caption
      );
      $rendered_pager = Pager::renderControls($find_result['count']);
      if(!$find_result['count']) {
        Msg::warning(t('This list is empty'));
      }
      return $rendered_ids . $rendered_pager;
    }
    
    function renderIDS($ids_or_instances, $skip_fields = [], $add_links = true, $caption = false) {
      $output = '';
      $class = $this->className();
      $settings = new Settings();
      if($this->listContext()) {
        foreach($this->fields() as $field) {
          if($settings->skipOnListDisplay($class, $field)) {
            $skip_fields[] = $field;
          }
        }
      }
      if($this->displayContext()) {
        foreach($this->fields() as $field) {
          if($settings->skipOnSingleDisplay($class, $field)) {
            $skip_fields[] = $field;
          }
        }
      }
      $table_classes = 'list shy list-' . $class;
      if(!$caption) {
        if(!$this->mailFaxContext()) {
          $caption = t('List') . ' ' . t($class.'s');
        }
        if($this->displayContext()) {
          $instance = Factory::newInstance($class, $ids_or_instances[0]);
          $table_classes = 'display shy display-' . $class; 
          $caption = $instance->oneLinerDisplay();
        }
      }
      $list_intro = '';
      $list_buttons = '';
      $list_outro = '';
      if($this->listContext()) {
        $list_intro = self::listIntro($this);
        $list_buttons = self::listButtons($this);
        $list_outro = self::listOutro();
      }
      
      $caption_content = $caption.$list_buttons;
      if(trim(strip_tags($caption_content))) {
        $caption_content = '<caption>' . $caption_content . '</caption>';
      } else {
        $caption_content = '';
      }
      
      $output .= <<<HTML
        <div class="$table_classes">
          $list_intro
          <table>
            $caption_content
HTML;
      $output .= $this->renderTableHeaders($skip_fields);
      $output .= '<tbody>';
      foreach($ids_or_instances as $id_or_instance) {
        if($id_or_instance instanceof Record) {
          $instance = $id_or_instance;
        }
        else {
          $instance = Factory::newInstance($this->className(), $id_or_instance);
        }
        $instance->setContext($this->context);        
        $cells = $instance->renderAsCells($skip_fields, $add_links);
        $class = $this->className();
        $output .= <<<HTML
          <tr 
            class="display-row" 
            data-class="$class"
            data-id="{$instance->id}"
          >
            $cells
          </tr>
HTML;
      }
      $output .= '</tbody></table>' . $list_buttons . $list_outro . '</div>';
      return $output;
    }
    
    function prepareTableHeaders($fields, $skip_fields) {
      $headers = [];
      if(!$this->printMailFaxContext()) {
        $headers[] = [
          'text' => Icons::i('cog'),
          'sortable' => false,
          'translatable' => false,
        ];
      }
      foreach($fields as $field) {
        if(in_array($field, $skip_fields)) {
          continue;
        }
        $headers[$field] = [
          'text' => $field,
          'sortable' => true,
          'translatable' => true,
        ];
      }
      return $headers;
    }
    
    function wrapAndTranslateHeaders($headers) {
      $ths = [];
      foreach($headers as $header) {
        $text = $header['text'];
        $sortable = $header['sortable'];
        $translatable = $header['translatable'];
        $translated = $text;
        if(!trim($text)) {
          $translated = '&nbsp;';
        }
        else if($translatable) {
          $translated = t($text);
        } 
        $data_sortable = $sortable ? 'data-sortable="1"' : ''; 
        $data_column = $sortable ? 'data-column="'.$text.'"' : ''; 
        $ths[] = <<<HTML
    <th $data_sortable $data_column>$translated</th>
HTML;
      }
      return $ths;
    }
    
    function renderTableHeaders($skip_fields = []) {
      $headers = $this->prepareTableHeaders($this->fields(), $skip_fields);
      $wrapped = $this->wrapAndTranslateHeaders($headers);
      if($this->listContext()) {
        array_unshift($wrapped, '<th class="list-column">S</th>');
      }
      $imploded = implode($wrapped);
      if(!$imploded) {
        return '';
      }
      $output = '<thead><tr>' . $imploded . '</tr></thead>';
      return $output;
    }
    
    function prepareCells($fields, $skip_fields, $add_links = true) {
      $cells = [];
      if($add_links) {
        static $global_settings = false;
        if(!$global_settings) {
          $global_settings = new Settings();
        }
        $cells[] = [
          'content' => $this->getCellsAdditionalButtons($global_settings),
          'class' => 'additional',
        ];
      }
      foreach($fields as $fieldname => $settings) {
        if(in_array($fieldname, $skip_fields)) {
          continue;
        }
        $field_value = $this->$fieldname;
        $field = new Field($settings, $field_value, $this->className(), $fieldname);
        $class = 'field-' . $field->name . ' widget-' . $field->widget;
        $cells[$field->name] = [
          'content' => $field->displayValue($add_links, Field::ADD_PREFIX, $this->context),
          'class' => $class
        ];
      }
      return $cells;
    }
    
    function getCellsAdditionalButtons($global_settings) {
      $class = $this->className();
      $output = '';
      $edit_url = $this->editURL();
      $icon = Icons::i('pencil-alt');
      $title = t('Edit');
      $base = config('publicbase');
      $output .= <<<HTML
      <a class="button edit square-button" 
        data-context="table-row" 
        data-class="$class"
        data-id="{$this->id}" 
        href="$base/$edit_url"
        title="$title"
      >$icon</a>
HTML;
      if($global_settings->showInlineDisplayButtons) {
        $display_url = $this->displayURL();
        $icon = Icons::i('eye'); 
        $title = t('Display');
        $output .= <<<HTML
        <a class="button display square-button" 
          data-context="table-row" 
          data-class="$class"
          data-id="{$this->id}" 
          href="$base/$display_url"
          title="$title"
        >$icon</a>
HTML;
      }            
      if($global_settings->showInlineDeleteButtons) {
        $delete_url = $this->deleteURL();
        $icon = Icons::i('remove'); 
        $title = t('Delete');
        $output .= <<<HTML
        <a class="button delete square-button" 
          data-context="table-row" 
          data-class="$class"
          data-id="{$this->id}" 
          href="$base/$delete_url"
          title="$title"
        >$icon</a>
HTML;
      }            
      return $output;
    }
    
    function renderAsCells($skip_fields = [], $addlinks = true) {
      $class = $this->className();
      Ob_start();
      $cells = $this->prepareCells($this->settings(), $skip_fields, $addlinks);
      if($this->listContext()) {
        $cells[] = [
          'content' => "<input class='never-dirty' type='checkbox' name='id[{$this->id}]'>",
          'class' => 'list-column',
        ];
      }
      foreach($cells as $cell) {
        $class = $cell['class'];
        $content = str_replace('|', ' | ', $cell['content']);
        if($this->printMailFaxContext()) {
          $content = nl2br($content);
        }
        echo "<td class='$class'>$content</td>";
      }
      $output = Ob_get_clean();
      return $output;
    }
    
    function renderDisplay() {
      $this->setContext(Record::DISPLAY_CONTEXT);
      $output = '';
      if($this->galleryEnabled()) {
        $output .= $this->getGallery()->renderDisplay();
      }
      return $this->renderIDS([$this->id]) . $output;
    }
    
    function getPrintSkipFields() {
      $settings = new Settings();
      $skip_fields = [];
      $class = $this->className();
      foreach($this->fields() as $field) {
        if($this->mailFaxContext()) {
          if($settings->skipOnMailFaxDisplay($class, $field)) {
            $skip_fields[] = $field;
          }
        } 
        else if($settings->skipOnPrintDisplay($class, $field)) {
          $skip_fields[] = $field;
        }
      }
      return $skip_fields;
    }
    
    function renderIdsForPrint($ids) {
      $skip_fields = $this->getPrintSkipFields();
      return $this->renderIds($ids, $skip_fields, Record::OMIT_LINKS);
    }
    
  }