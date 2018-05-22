<?php

  namespace App\Pages;
  
  use App\Records\Factory;
  use App\Records\Item;
  use App\Utils\Msg;
  
  class StockFixer {
    static function main() {
      setTitle(t('Stock fixer'));
      $ids = self::getCandidates();
      if(!count($ids)) {
        Msg::msg(t('No negative stock to fix'));
        return;
      }
      $log_prefix = 'list';
      $verified = 0;
      $new_stock = 0;
      foreach($ids as $id) {
        $item = Factory::newItem($id);
        $price = $item->price;
        Item::forceStock(intval($id), $new_stock, $price, $verified, $log_prefix);
      }
    }
    
    private static function getCandidates() {
      $item = Factory::newItem();
      $fields = [
        'stock' => '<0',
      ];
      $search_params = [];
      foreach($fields as $field_name => $value) {
        $settings = $item->settings()[$field_name];
        $param = $item->prepareSearchParam($field_name, $value, $settings);
        if($param['value']) {
          $search_params[$field_name] = $param;
        }
      }
      return $item->find($search_params)['ids'];
    }
  }

