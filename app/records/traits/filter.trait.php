<?php

  namespace App\Records\Traits;
  
  use App\Utils\Msg;
  use App\Records\Record;
  
  trait Filter {
    
    function filterRoute($raw_params) {
      $this->addContext(Record::FILTER_CONTEXT);
      $this->setFilterRouteTitles();
      $post_params = $this->rawParamsToPostParams($raw_params);
      $search_params = $this->postParamsToSearchParams($post_params);
      return $this->filterButtons() . $this->processSearch($search_params);
    }
    
    function setFilterRouteTitles() {
      setMainClasses('filter');
      $title = ct('male', 'List of filtered %s', t($this->className().'s'));
      if($this->gender() === 'female') {
        $title = ct('female', 'List of filtered %s', t($this->className().'s'));
      }
      setTitle($title);
    }
    
    function checkFilterRouteParams($raw_params) {
      if(count($raw_params) < 2) {
        Msg::error(t('Too few parameters passed to filter route'));
        Router::redirect($this->className);
      }
      if(count($raw_params) % 2) {
        Msg::warning(t('Odd number of params passed to filter route, the last param will be discarded'));
      }
    }
    
    function rawParamsToPostParams($raw_params) {
      if(is_string($raw_params)) {
        $raw_params = explode('/', $raw_params);
      }
      $paired_params = [];
      while(count($raw_params) >= 2) {
        $paired_params[array_shift($raw_params)] = array_shift($raw_params);
      }
      if(!isset($paired_params['matching'])) {
        $paired_params['matching'] = 'AND';
      }
      return $paired_params;
    }
    
    function filterButtons() {
      return '';
    }
    
    function getFilterButton($filter_path, $title) {
      $full_filter_path = "request/filter/$filter_path";
      $active = '';
      if(\App\Router::latestRoute() === trim($full_filter_path, '/')) {
        $active = 'active';
        $singular = mb_strtolower(t($this->className()));
        $plural = mb_strtolower(t($this->className().'s'));
        $page_title = mb_strtolower($title);
        if(strpos($page_title, $singular) === false
            && strpos($page_title, $plural) === false) {
          $page_title = $plural . ': ' . $title;
        }
        setTitle($page_title);
      }
      $count = $this->findFromFilterPath($filter_path)['count'];
      $title .= ' (' . $count . ')';
      $base = config('publicbase');
      return <<<HTML
<a class="button $active" href="$base/$full_filter_path">$title</a> 
HTML;
    }
    
    function findFromFilterPath($filter_path) {
      $paired_params = $this->rawParamsToPostParams($filter_path);
      $search_params = $this->postParamsToSearchParams($paired_params);
      return $this->find($search_params);
    }
    
  }
