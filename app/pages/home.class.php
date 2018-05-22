<?php

  namespace App\Pages;
  
  use App\Records\Document;
  use App\Records\Factory;
  use App\Router;
  use App\Utils\Format;
  use App\Utils\Msg;
  use App\Records\Person;
  
  class Home {
    
    static function main() {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $barcode = trim(getPOST('barcode', FILTER_SANITIZE_STRING));
        $id_document = getPOST('id_document', FILTER_VALIDATE_INT);
        if($barcode && $id_document) {
          self::search($barcode, $id_document);
        }
      }
      return self::show();
    }
    
    static function show() {
      Ob_start();
      $item = Factory::newItem();
      $title = t('Unload from daily document');
      $main_classes = 'home daily';
      $current = Document::getCurrent();
      if(Document::alternateMode()) {
        $title = t('Load on alternate document');
        $main_classes = 'home alternate';
        $newtext = t("Go back to today's daily document");
        $daily = Document::getDaily();
        Msg::warning(t('You are working on %s', $current->oneLinerDisplay()));
        Msg::warning("<a class=\"button\" href='" . config('publicbase') . "/?cur_doc_id={$daily->id}'>$newtext</a>");
      }
      $form_params = [
        'display_fields' => ['barcode'],
        'hidden_fields' => ['id_document' => $current->id],
      ];
      echo $item->renderSearchForm($form_params);
      $collapsible_id = 'home-list';
      $collapsible_title = t('Current document and changes');
      $markup = $current->displayRoute();
      echo Format::collapsible($markup, $collapsible_title, $collapsible_id);
      setTitle($title);
      setMainClasses($main_classes);
      return Ob_get_clean() . Person::renderPersonsBoxContainer();
    }
  
    static function search($barcode, $id_document) {
      $item = Factory::newItem();
      $params = [
        'barcode' => $barcode,
      ];
      $found = $item->find($params)['ids'];
      if(count($found) < 1) {
        Msg::warning(t('Unable to find barcode %s', safeMarkup($barcode)));
        Msg::warning(t('Redirected to the creation of a new item'));
        Router::redirect(config('publicbase') . "/item/new?barcode=$barcode");
      }
      else if(count($found) > 1) {
        Msg::error(t('Found multiple Items for barcode %s!', safeMarkup($barcode)));
      }
      else {
        $id_item = $found[0];
        Router::redirect(config('publicbase') . "/change/new?id_document=$id_document&id_item=$id_item");
      }
    }

    function testClasses() {
      Ob_start();    
      //extractTablesAndColumnsForGetText();
      foreach(Factory::validClasses() as $class) {
        test($class);
      }
      return Ob_get_clean();    
    }

    function test($classname) {
      echo '<hr>';
      echo "<h1>$classname</h1>";
      $obj = Factory::newInstance($classname, 1);
      echo $obj->renderDisplay();
      echo $obj->renderEditForm();
    }

  }
