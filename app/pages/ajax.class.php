<?php

  namespace App\Pages;

  use App\Records\Factory;
  use App\Records\Record;
  use App\Records\Item;
  use App\Records\Document;
  use App\Records\Company;
  use App\Utils\Msg;
  use App\Utils\Gallery;
  use App\Router;
  use App\Pages\Theme;
  use App\Records\Person;
  use App\Records\Payment;
  use App\Records\Category;

  class Ajax {

    private $response = [];

    static function main() {
      new Ajax();
    }

    function __construct() {
      $this->response['success'] = false;
      try {
        if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') !== 'POST') {
          Msg::error(t('Invalid AJAX request'));
          $this->finish();
        }
        $callback = 'do' . str_replace('-', '', getPOST('action'));
        if(method_exists($this, $callback)) {
          $this->$callback();
        }
        else {
          Msg::error(t('Unsupported AJAX action'));
        }
      }
      catch(\Exception $e) {
        Msg::error($e->getMessage());
      }
      $this->finish();
    }

    private function setSuccess() {
      $this->response['success'] = true;
    }

    private function doApplySpecialCategory() {
      $class_name = getPOST('class');
      $record_id = getPOST('id');
      $category = getPOST('category');
      $instance = Factory::newInstance($class_name, $record_id);
      if(!in_array('category', $instance->fields())) {
        return;
      }
      $categories = array_diff(explode('|', $instance->category), Category::getSpecials());
      $categories[] = $category;
      $new_category = trim(implode('|', $categories), '|');
      $instance->category = $new_category;
      $instance->selfLog('ajax-pre-save');
      $instance->save();
      $category_instance = Category::byName($instance->category);
      $this->response['category']['name'] = $category_instance->category;
      $this->response['category']['id'] = $category_instance->id;
      $this->setSuccess();
    }

    private function doCreateDeposits() {
      $deposit = getPOST('deposit', FILTER_VALIDATE_FLOAT);
      $person_id = getPOST('personID', FILTER_VALIDATE_INT);

      $person = new Person($person_id);
      if($person->id !== $person_id) {
        Msg::error(t('Must specify an existing person name'));
        return false;
      }
      $notes = getPOST('notes');
      $result = Payment::createDeposits($person, $deposit, $notes);
      foreach($result as $level => $messages) {
        foreach($messages as $message) {
          Msg::msg($message, $level);
        }
      }
      if(!$result['error']) {
        $this->setSuccess();
        $this->response['personsBox'] = $person->renderPersonsBox();
      }
    }

    private function doActivatePerson() {
      $number = getPOST('number');
      $result = \App\Records\Person::setCurrent($number);
      if($result) {
        $this->setSuccess();
        $this->response['personsBox'] = $result;
      }
    }

    private function doGetPrintBalanceCacheUrl() {
      $person_id = getPOST('personID');
      $context = getPOST('context') ?? 'alltime';
      $print_id = Factory::getPrintBalanceCacheID($person_id, $context);
      if($print_id) {
        $this->response['url'] = config('publicbase') . '/print.php?print-cache-id=' . $print_id;
        $this->setSuccess();
      }
    }

    private function doGetPrintCacheUrl() {
      $class = getPOST('class');
      $ids_string = getPOST('ids');
      $context = getPOST('context') ?? 'print';
      $ids = explode(',', $ids_string);
      if(trim($ids_string) === '') {
        $ids = [];
      }
      $print_id = Factory::getPrintCacheID($class, $ids, $context);
      if($print_id) {
        $this->response['url'] = config('publicbase') . '/print.php?print-cache-id=' . $print_id;
        $this->setSuccess();
      }
    }

    private function doSaveGalleryImage() {
      $radix = getPost('radix');
      $data = getPost('data');
      if($radix && $data) {
        $gallery = new Gallery($radix);
        $item = $gallery->saveImage($data);
        if($item) {
          $this->response['item'] = $item;
          $this->setSuccess();
        }
      }
    }

    private function doDeleteGalleryImage() {
      $radix = getPost('radix');
      $index = getPost('index');
      $ext = getPost('ext');
      if($radix && $index && $ext) {
        $gallery = new Gallery($radix);
        if($gallery->deleteImage($index, $ext)) {
          $this->setSuccess();
        }
      }
    }

    private function doGetGalleryItems() {
      $radix = getPost('radix');
      if($radix) {
        $gallery = new Gallery($radix);
        $this->response['items'] = $gallery->getGalleryItems();
        $this->setSuccess();
      }
    }

    private function doGetCurrentTheme() {
      $theme_name = Theme::currentThemeName();
      $this->response['themeName'] = $theme_name;
      $this->response['theme'] = Theme::loadTheme($theme_name);
      $this->setSuccess();
    }

    private function doResetCssCache() {
      $theme_name = Theme::resetCssCache();
      if($theme_name) {
        $this->response['themeName'] = $theme_name;
        $this->setSuccess();
      }
    }

    private function doSetThemeMarker() {
      $theme_name = getPost('theme-name');
      $marker = getPost('marker');
      $value = getPost('value');
      if(Theme::setThemeMarker($theme_name, $marker, $value)) {
        $this->setSuccess();
      }
    }

    private function doEnsureTheme() {
      $theme_name = getPost('theme-name');
      $this->response['cssName'] = Theme::activateTheme($theme_name,
                                                        Theme::SILENT) . '?' . rand();
      $this->setSuccess();
    }

    private function doLoadUnload() {
      $change_value = getPost('change', FILTER_VALIDATE_FLOAT);
      $price = getPost('price', FILTER_VALIDATE_FLOAT);
      $id_document = getPost('id_document', FILTER_VALIDATE_INT);
      $id_item = getPost('id_item', FILTER_VALIDATE_INT);

      $this->response['errors'] = [];

      if($price <= 0) {
        $this->response['errors'][] = t('Change price must be positive');
      }
      if(is_null($change_value)) {
        $this->response['errors'][] = t('Invalid change value');
      }
      if(!$id_item) {
        $this->response['errors'][] = t('Missing item ID');
      }
      if(!$id_document) {
        $this->response['errors'][] = t('Missing document ID');
      }

      $change = Factory::newChange();
      $values = [
        'change' => $change_value,
        'price' => $price,
        'id_document' => $id_document,
        'id_item' => $id_item,
      ];

      $current_customer_number = getPOST('customer_number');
      $personInstance = Factory::newPerson();
      $person = $personInstance->personFromCustomerNumber($current_customer_number);
      if($person->id) {
        $values['id_person'] = $person->id;
      }
      else if(!$change_value) {
        $this->response['errors'][] = t('Cannot set an item in trial without assigning a person to it');
      }
      if($this->response['errors']) {
        return;
      }

      $new_change_response = $change->newFromValuesAndSave($values, 'ajax');
      if($new_change_response) {
        $item = Factory::newItem($id_item);
        $this->response['new']['stock'] = $item->stock;
        $this->setSuccess();
        $this->response['new']['markup'] = Router::route('document/' . $id_document);
        if($person->id) {
          $this->response['new']['personsBox'] = \App\Records\Person::renderPersonsBox();
        }
      }
    }

    private function doGetNewItemCode() {
      $item = Factory::newItem();
      $custom_code = $item->getNextCustomItemNumber();
      $this->setSuccess();
      $this->response['newcode'] = $custom_code;
      return;
    }

    private function doPrepareRapidChange() {
      $item = Factory::newItem();
      $params = [
        'barcode' => getPost('barcode'),
      ];
      $newcodes = [
        'newcode', '*', '-', '/', '+'
      ];
      if(in_array(trim(strtolower($params['barcode'])), $newcodes)) {
        $item = Factory::newItem();
        $custom_code = $item->getNextCustomItemNumber();
        $this->response['error'] = ' ';
        $this->response['newcode'] = $custom_code;
        return;
      }

      $matches = [];
      if(preg_match('#^\s*[/*+-](\d+)\s*$#', $params['barcode'], $matches)) {
        $inst = Factory::newItem();
        $params['barcode'] = $inst->formatCustomItemNumber($matches[1]);
      }

      $ids = $item->find($params)['ids'];
      if(count($ids) === 1) {
        $id_document = getPOST('id_document');
        Document::setCurrent($id_document);
        $document = Document::getCurrent();
        $company = new Company($document->id_company);
        $item = Factory::newItem($ids[0]);
        $this->response['item']['name'] = compactString($item->oneLinerDisplay());
        $this->response['item']['barcode'] = $item->barcode;
        $this->response['item']['category'] = $item->category;
        $this->response['item']['display'] = compactString($item->renderDisplay());
        $this->response['item']['id'] = $item->id;
        $this->response['document']['id'] = $document->id;
        $this->response['company']['name'] = $company->name;
        $this->response['company']['multiplier'] = $company->multiplier;
        $this->setSuccess();
        return;
      }
      else if(count($ids) > 1) {
        $this->response['newcode'] = $params['barcode'];
        $this->response['error'] = t('Multiple items found with barcode %s!',
                                     $params['barcode']);
      }
      else {
        $this->response['newcode'] = $params['barcode'];
        $this->response['error'] = t('No item found with barcode %s',
                                     $params['barcode']);
      }
    }

    private function doChangeStock() {
      $stock = getPost('stock', FILTER_VALIDATE_FLOAT);
      $price = getPost('price', FILTER_VALIDATE_FLOAT);
      $id = getPOST('id', FILTER_VALIDATE_INT);
      $class = getPOST('class');
      if($class !== 'item') {
        Msg::error(t('Unexpected %s as record class in AJAX request', $class));
        return;
      }
      $log_prefix = 'ajax';
      $verified = 1;
      $item = Item::forceStock($id, $stock, $price, $verified, $log_prefix);
      if($item) {
        $this->response['fields'] = $item->resolvedFieldPairs();
        $this->setSuccess();
      }
    }

    private function doDelete() {
      $class = getPOST('class');
      $id = getPOST('id', FILTER_VALIDATE_INT);
      $instance = Factory::newInstance($class, $id);
      if(!$instance || $id !== $instance->id) {
        Msg::error(t('Invalid record type or ID in AJAX request'));
        return;
      }

      $this->response['success'] = $instance->safeDelete('ajax');
    }

    private function doEdit() {
      $class = getPOST('class');
      $id = getPOST('id', FILTER_VALIDATE_INT);
      $instance = Factory::newInstance($class, $id);
      if(!$instance || $id !== $instance->id) {
        Msg::error(t('Invalid record type or ID in AJAX request'));
        return;
      }
      $test_values = [];
      foreach($instance->fields() as $field_name) {
        $posted_value = getPOST($field_name);
        if(!is_null($posted_value)) {
          $test_values[$field_name] = $posted_value;
        }
      }
      $new_values = $instance->validateNewValues($test_values,
                                                 Record::SKIP_FULL_CHECK);
      if(!$new_values) {
        return false;
      }
      $instance->selfLog('ajax-pre-edit');
      foreach($new_values as $field_name => $value) {
        $instance->$field_name = $value;
      }
      $instance->selfLog('ajax-pre-save');
      if($instance->save()) {
        $this->response['fields'] = $instance->resolvedFieldPairs();
        if($instance->className() === 'change' && array_key_exists('change',
                                                                   $new_values)) {
          $item = Factory::newItem($instance->id_item);
          $this->response['item']['display'] = $item->renderDisplay();
          $this->response['item']['id'] = $item->id;
          $this->response['personsBox'] = \App\Records\Person::renderPersonsBox();
        }
        $this->setSuccess();
      }
      else {
        Msg::error(t('Error during save of %s %d, database error', t($class),
                                                                     $id));
      }
    }

    private function finish() {
      $return = true;
      $this->response['messages'] = Msg::flush($return);
      header('Content-type: application/json; charset=utf-8');
      echo json_encode($this->response, JSON_PRETTY_PRINT);
      die();
    }

  }
  