<?php

  namespace App\Records;

  use App\Utils\Msg;
  use App\Router;
  
  class Payment extends Record  {

    function __construct($id = null) {
      $this->table_name = 'payment';
      $this->unique_column = 'id';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    function prepareEditForm($form) {
      $person_id = filter_input(INPUT_GET, 'person_id');
      if($person_id) {
        $this->id_person = $person_id;
      }
      return parent::prepareEditForm($form);
    }
    
    function processEditPost() {
      if($this->id) {
        return parent::processEditPost(); 
      }
      $deposit = getPOST('amount');
      $notes = getPOST('notes');
      $person_name = getPOST('id_person');
      if(!$person_name) {
        Msg::error(t('Must specify an existing person name'));
        return false;
      }
      $instance = Factory::newPerson();
      $person = $instance->personByName($person_name);
      if($person->person !== $person_name) {
        Msg::error(t('Must specify an existing person name'));
        return false;
      }
      $result = self::createDeposits($person, $deposit, $notes);
      foreach($result as $level => $messages) {
        foreach($messages as $message) {
          Msg::msg($message, $level);
        }
      }
      if($result['error']) {        
        return false;
      }
      Router::redirect(config('publicbase') . '/person/' . $person->id);
    }

    static function createDeposits($person, $deposit, $notes) {
      $result = [
        'error' => [],
        'warning' => [],
        'normal' => [],
      ];
      $filtered = $person->filteredChanges();
      $pending_changes = $filtered['pending'];
      $credits = $filtered['credits'];
      $total_credit = 0;
      foreach($credits as $credit) {
        $total_credit += $credit['amount'];
      }
      
      if($deposit < 0) {
        $result['error'][] = t('The payment cannot be negative');
        return $result;
      }
      
      if($deposit <= 0 && !$total_credit) {
        $result['error'][] = t('There is no credit available');
        return $result;
      }
      $delete_existing_credits = false;
      if($total_credit > 0) {
        $delete_existing_credits = true;
        $deposit += $total_credit;
      }
      foreach($pending_changes as $changeID => $amount) {
        $change = new Change($changeID);
        if($deposit <= 0) {
          break;
        }
        if($deposit >= $amount) {
          $result['normal'][] = t('Change %s completely paid', $change->oneLinerDisplay());
          $value = $amount;
          $deposit -= $amount;
        } else {
          $result['normal'][] = t(
            'Change %s partially paid (%s remaining)', 
            $change->oneLinerDisplay(),
            '&euro;' . ($amount - $deposit)
          );
          $value = $deposit;
          $deposit = 0;
        }
        if(!self::createDeposit($changeID, $value, $person->id, $notes)) {
          $result['error'][] = t('Unable to create deposit!');
          return $result;
        }
      }
      if($deposit > 0) {
        if(!self::createDeposit(null, $deposit, $person->id, $notes)) {
          $result['error'][] = t('Unable to create credit!');
          return $result;
        }
        $result['warning'][] = t('The payment you specified was greater than due pending changes');
        $result['warning'][] = t('The remaining amount of %s has been registered as a credit', '&euro;' . $deposit);
      }
      if($delete_existing_credits) {
        foreach($credits as $credit_id => $credit) {
          $payment = new Payment($credit_id);
          $payment->delete();
        }
      }
      return $result;
    }
    
    static function createDeposit($changeID, $amount, $personID, $notes) {
      $payment = new Payment();
      $payment->disable_readonly_checks = true;
      $payment->id_change = $changeID;
      $payment->id_person = $personID;
      $payment->notes = $notes;
      $payment->amount = $amount;
      $payment->selfLog('deposit-pre-save');
      return $payment->save();
    }
        
    function postUpdate() {
      Person::recalculateBalance($this->id_person);
      parent::postUpdate();
    }
    
    function defaultSettings() {
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'id_change' => [
          'widget' => 'number',
          'reference' => 'change',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'readonly' => true,
          'keep_visible' => true,
        ],
        'id_person' => [
          'widget' => 'text',
          'reference' => 'person',
          'default_mode' => 'key',
          'schema' => self::referenceSchema(),
          'autocomplete' => true,
          'required' => true,
        ],
        'notes' => [
          'widget' => 'textarea',
          'schema' => self::varcharSchema(256),
        ],        
        'amount' => [
          'widget' => 'number',
          'required' => true,
          'prefix' => '€',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
        'date' => [
          'widget' => 'datetime-local', 
          'default' => date(Factory::DATETIME_FORMAT),
          'schema' => self::datetimeSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
  
