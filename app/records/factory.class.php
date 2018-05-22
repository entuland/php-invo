<?php
  
  namespace App\Records;
  
  use App\Utils\Database;
  use App\Utils\Settings;
  use App\Utils\Msg;
  
  class Factory {
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATE_FORMAT = 'Y-m-d';
    const DATETIME_DISPLAY_FORMAT = 'd/m/Y H:i:s';
    const DATE_DISPLAY_FORMAT = 'd/m/Y';
    const DATETIME_DISPLAY_SHORT_FORMAT = 'd/m/y H:i';
    const DATE_DISPLAY_SHORT_FORMAT = 'd/m/y';
    static $schema = [];
    
    public static function getPrintCacheID($class, $ids, $text_context) {
      if(!self::validClass($class)) {
        Msg::warning(t('Invalid class %s requested for printing', $class));
        return false;
      }
      if(!is_array($ids) || !count($ids)) {
        Msg::warning(t('No valid %s IDS passed for printing', t($class)));
        return false;
      }
      $instance = self::newInstance($class);
      $title_tag = t('Print');
      
      $disable_autoprint = false;
      $raw_output = false;
      switch($text_context) {
        case 'cards':
          $content = $instance->renderPrintCards($ids);
          $raw_output = true;
          break;
        case 'labels':
          $content = $instance->renderPrintLabels($ids);
          $raw_output = true;
          break;
        case 'mail':
          $instance->setContext(Record::MAIL_CONTEXT);
          $disable_autoprint = true;
          break;
        case 'fax':
          $instance->setContext(Record::FAX_CONTEXT);
          $disable_autoprint = true;
          break;
        default:
          $text_context = 'print';
          $instance->setContext(Record::PRINT_CONTEXT);
      }
      
      if(!$raw_output) {
        $instances = [];
        $companies = [];
        foreach($ids as $id) {
          $inst = self::newInstance($class, $id);
          $companies[$inst->id_company] = true;
          $instances[] = $inst;
        }
        $content = $instance->renderIdsForPrint($instances);
        if($instance->mailFaxContext()) {
          $company_id = false;
          if(count($companies) < 1) {
            Msg::warning(t('No company found in chosen records'));
            Msg::warning(t('Company section will be left blank'));
          } else if(count($companies) > 1) {
            Msg::warning(t('Multiple companies found in chosen records'));
            Msg::warning(t('Company section will be left blank'));
          } else {
            $company_id = array_keys($companies)[0];
          }
          $company = self::newCompany($company_id);
          $content = self::wrapInMailFaxTexts($content, $instance, $company);
        }
      }
      return self::printCacheId(self::applyPrintTemplate($title_tag, $content, $text_context, !$disable_autoprint));
    }
    
    static function getPrintBalanceCacheID($person_id, $context) {
      $person = new Person($person_id);
      if(!$person_id || $person->id != $person_id) {
        return false;
      }
      $title_tag = t('Balance print');
      $content = $person->oneLinerDisplay();
      if($context === 'lastyear') {
        $text_context = 'balance lastyear';
        $content .= $person->renderAllPurchases(false);
      } else {
        $text_context = 'balance alltime';
        $content .= $person->renderAllPurchases();
      } 
      return self::printCacheId(self::applyPrintTemplate($title_tag, $content, $text_context));
    }
    
    private static function printCacheId($content) {
      $print_id = rand();
      $_SESSION['print-cache'][$print_id] = $content;
      return $print_id;
    }
    
    private static function applyPrintTemplate(string $title_tag, string $content, string $text_context, bool $autoprint = null) {
      $settings = new Settings();
      if($settings->enableAutoPrint && $autoprint !== false) {
        $content .= '<script>window.print();</script>';
      }
      unused($text_context);
      unused($title_tag);
      unused($content);
      ob_start();
      require "templates/print.tpl.php";
      return compactString(ob_get_clean());
    }
    
    private static function wrapInMailFaxTexts($content, $instance, $company) {
      $settings = new Settings();
      $home = self::newCompany($settings->home_company_id);
      
      if($instance->mailContext()) {
        $template = $settings->mailTemplate();
      } else {
        $template = $settings->faxTemplate();
      }
      $template_with_company = $company->doReplacements($template, t('company'));
      $template_with_home = $home->doReplacements($template_with_company, $settings->home_company_prefix);
      $template_with_markdown = self::parsedown($template_with_home);
      
      $template_with_requests = str_replace('[' . t('requests') . ']', $content, $template_with_markdown);
      return $template_with_requests;      
    }
    
    public static function parsedown($text) {
      $parser = new \Parsedown();
      $parser->setUrlsLinked(false);
      $parser->setBreaksEnabled(true);
      return $parser->text($text);
    }
    
    public static function dateDisplayFormat() {
      $s = new Settings();
      if($s->useShortDateFormat) {
        return self::DATE_DISPLAY_SHORT_FORMAT;
      }
      return self::DATE_DISPLAY_FORMAT;
    }
    
    public static function dateTimeDisplayFormat() {
      $s = new Settings();
      switch(true) {
        case $s->useShortDateFormat && $s->hideHours:
          return self::DATE_DISPLAY_SHORT_FORMAT;
        case $s->hideHours:
          return self::DATE_DISPLAY_FORMAT;
        case $s->useShortDateFormat:
          return self::DATETIME_DISPLAY_SHORT_FORMAT;
        default:
          return self::DATETIME_DISPLAY_FORMAT;
      }
    }
    
    public static function formatDateDisplay($datestring) {
      return date(self::dateDisplayFormat(), strtotime($datestring));
    }
    
    public static function formatDateTimeDisplay($datestring) {
      return date(self::dateTimeDisplayFormat(), strtotime($datestring));
    }
    
    public static function genderClasses() {
      static $gender = [
        'item' => 'male',
        'document' => 'male',
        'company' => 'male',
        'category' => 'female',
        'person' => 'female',
        'request' => 'female',
        'color' => 'male',
        'size' => 'female',
        'make' => 'female',
        'um' => 'female',
        'doctype' => 'male',
        'change' => 'male',
        'payment' => 'male',
      ];
      return $gender;
    }
    
    public static function classGender($class) {
      if(self::validClass($class)) {
        return self::genderClasses()[$class];
      }
      return 'male';
    }
    
    public static function validClasses() {
      return array_keys(self::genderClasses());
    }
    
    public static function validClass($class) {
      return in_array(mb_strtolower($class), self::validClasses());
    }
    
    public static function getDefaultSchema() {
      if(!self::$schema) {
        self::$schema = [];
        $skipInit = true;
        $id = null;
        foreach(self::validClasses() as $class) {
          $instance = self::newInstance($class, $id, $skipInit);
          self::$schema[$class] = $instance->getSchema();
        }
      }
      return self::$schema;
    }
        
    public static function newInstance($class, $id = null, $skipInit = false) {
      $lower_class = mb_strtolower($class);
      if(in_array($lower_class, self::validClasses())) {
        return self::{'new'.$lower_class}($id, $skipInit);      
      }
      return false;
    }
    
    public static function newChange($id = null) {
      return new Change($id);
    }
    
    public static function newCompany($id = null) {
      return new Company($id);
    }
    
    public static function newDocType($id = null) {
      return new DocType($id);
    }
    
    public static function newDocument($id = null, $skipInit = false) {
      return new Document($id, $skipInit);
    }
    
    public static function newItem($id = null) {
      return new Item($id);
    }
    
    public static function newColor($id = null) {
      return new Color($id);
    }
    
    public static function newSize($id = null) {
      return new Size($id);
    }
    
    public static function newMake($id = null) {
      return new Make($id);
    }
    
    public static function newUM($id = null) {
      return new UM($id);
    }
    
    public static function newPerson($id = null) {
      return new Person($id);
    }
    
    public static function newRequest($id = null) {
      return new Request($id);
    }
    
    public static function newCategory($id = null) {
      return new Category($id);
    }
    
    public static function newPayment($id = null) {
      return new Payment($id);
    }
    
  }
  