<?php

  namespace App\Records\Traits;
  
  use App\Utils\Format;
  use App\Utils\Pager;
  use App\Utils\Msg;
  use App\Utils\Field;
  use App\Utils\Dynamic;
  use App\Records\Record;
  
  trait Search {
    
    function searchRoute() {
      setTitle(t('Search %s', t($this->className())));
      return $this->search();
    }
    
    function search() {
      $this->addContext(Record::SEARCH_CONTEXT);
      $class = $this->className();
      $form_params = [];
      $show_toggle = true;
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        doubleSubmissionCheck("$class/search");
        $post_params = $_POST;
        $output = $this->renderSearchForm($form_params, $show_toggle);
        $output .= $this->processSearchPostParams($post_params);
        $_SESSION['search_post'][$class] = $post_params;
      }
      else if(isset($_SESSION['search_post'][$class])) {
        $post_params = $_SESSION['search_post'][$class];
        
        $_SESSION['post'] = $post_params;
        $output = $this->renderSearchForm($form_params, $show_toggle);
        unset($_SESSION['post']);
        
        $output .= $this->processSearchPostParams($post_params);
      }
      else {
        $output = $this->renderSearchForm();
      }
      return $output;
    }
    
    function prepareSearchForm($form) {
      $form->class = 'search-form record-' . $this->className();
      $form->table_class = 'record-' . $this->className();
      $form->caption = t($this->className());
            
      $form->fields = new \stdClass();

      $display = $form->display_fields;
      
      if(!count($display) || count($display) > 1) {
        $field_value = 'AND';
        $field_name = 'matching';
        $form->fields->matching = new Field(self::matchingSettings(), $field_value, $this->className(), $field_name);
      }

      $settings = $this->settings();
      
      foreach($this->fields() as $field_name) {
        if(count($display) && !in_array($field_name, $display)) {
          continue;
        }
        $value = '';
        $field_settings = $settings[$field_name];
        $field = new Field($field_settings, $value, $this->className(), $field_name);
        $form->fields->$field_name = $field;
      }
      
      foreach($form->hidden_fields as $field_name => $field_value) {
        $field_settings = [
          'widget' => 'hidden',
        ];
        $field = new Field($field_settings, $field_value, $this->className(), $field_name);
        $form->fields->$field_name = $field;
      }
      
      $form->buttons .= self::searchButtons(count($display) !== 1);
      
      $form->after_table .= self::operatorsTooltip();
      
      return $form;
    }
    
    function renderSearchForm($form_params = [], $show_toggle = false) {
      $form = new Dynamic($form_params);
      if(!$form->display_fields) {
        $form->display_fields = [];
      }
      if(!$form->hidden_fields) {
        $form->hidden_fields = [];
      }
      $form->show_toggle = $show_toggle;
      $form->toggle_text = t('Search form');
      $form->action = '/' . $this->className() . '/search';
      $prepared_form = $this->prepareSearchForm($form);
      return Format::renderFormObject($prepared_form, Record::SEARCH_CONTEXT);
    }
    
    function prepareSearchParam($field_name, $value, $settings) {
      $result = [
        'value' => false,
      ];
      if(!strlen($value)) {
        return $result;
      }
      $field = new Field($settings, $value, $this->className(), $field_name);
      
      switch(true) {
        
        case $field->name === 'id':
          $value = intval($value);
          if($value) {
            $result['value'] = $value;
          }
          break;
        
        case $field->widget === 'datetime-local':
        case $field->widget === 'date':
          $result = self::prepareDateSearchParam($value);
          break;
        
        case $field->widget === 'number':
          $result = self::prepareNumberSearchParam($field, $value);
          break;
        
        case $field->reference:
          $result = self::prepareReferenceSearchParam($field, $value, $settings);
          $result['original'] = $field->displayValue(Record::OMIT_LINKS);
          break;
        
        default:
          $result['operator'] =  'LIKE';
          if(strpos($value, '%') === false) {
            $value = "%$value%";
          }
          $result['value'] =  $value;
      }
      if(!isset($result['original']) || trim($result['original']) === '') {
        $result['original'] = $value;      
      }
      return $result;
    }
    
    function postParamsToSearchParams($post_params) {
      $settings = $this->settings();
      $settings['matching'] = self::matchingSettings();
      $search_params = [];
      if(isset($post_params['matching'])) {
        $search_params['matching'] = $post_params['matching'];
        unset($post_params['matching']);
      }
      foreach($settings as $field_name => $field_settings) {
        $value = '';
        if(isset($post_params[$field_name])) {
          $value = $post_params[$field_name];
        }
        $param = $this->prepareSearchParam($field_name, $value, $field_settings);
        if(isset($param['value']) && !is_null($param['value']) && $param['value'] !== false) {
          $search_params[$field_name] = $param;
        }
      }
      return $search_params;
    }
    
    function processSearchPostParams($post_params) {
      $search_params = $this->postParamsToSearchParams($post_params);
      return $this->processSearch($search_params);
    }
    
    function processSearch($search_params) {
      $result = $this->find($search_params, Pager::$start, Pager::$count);
      $ids = $result['ids'];
      $count = $result['count'];
      $this->searchMessages($search_params, $count);
      return $this->renderIDS($ids) . Pager::renderControls($count);
    }
    
    function searchMessages($search_params, $count) {
      $entries = [];
      $settings = $this->settings();
      $settings['matching'] = self::matchingSettings();
      if(!isset($search_params['matching'])) {
        $search_params['matching'] = 'AND';
      }
      if($this->filterContext()) {
        unset($search_params['matching']);
      }
      foreach($search_params as $field_name => $param) {
        if(is_string($param)) {
          $original = $param;
        } else {
          $original = $param['original'];
        }
        if(!array_key_exists($field_name, $settings)
            || !strlen($original)) {
          continue;
        }
        if($field_name === 'matching') {
          $original = $settings['matching']['options'][$original];
        }
        else if(!intval($original)) {
          $original = t(trim(mb_strtolower(str_replace('%', ' ', $original))));
        }
        $operator = $param['operator'] ?? '';
        switch($operator) {
          case 'LIKE':
            $operator = t('contains');
            break;
          case '<>':
            $operator = t('different from');
            break;
          default:
            $operator = '';
        }
        $entries[] = t($field_name) . ' ' . $operator . ': <kbd>' . $original . '</kbd>';
      }
      if(count($entries)) {
        if($this->filterContext()) {
          Msg::msg(implode(', ', $entries));
        } else {
          Msg::msg(
            t('Search params:') 
            . '<ul>'
            . implode(Format::wrap('<li>', $entries, '</li>'))
            . '</ul>'
          );
        }
      }
      Msg::msg(t('Search returned %d %s', $count, nt('result', 'results', $count)));
    }
    
    function redoSearch() {
      $class = $this->className();
      $output = '';
      $_SESSION['post'] = $_SESSION['search_post'][$class];
      $result = $this->getFindResultFromSearchPost();
      $ids = $result['ids'];
      $count = $result['count'];
      $results = nt('result', 'results', $count);
      Msg::msg(t('Search returned %d %s', $count, $results));
      $search_params = [];
      $show_toggle = true;
      $output = $this->renderSearchForm($search_params, $show_toggle) 
        . $this->renderIDS($ids) 
        . Pager::renderControls($count);
      unset($_SESSION['post']);
      return $output;
    }
    
  }
