<?php

  namespace App\Records;
  
  use App\Utils\Msg;
  use App\Query\QueryRunner;
  use App\Utils\Settings;
  use App\Utils\Barcode;
  use App\Utils\Icons;
  use App\Utils\Format;
  
  // todo activate printing in yearly report
  
  class Person extends Record {
    
    function __construct($id = null) {
      $this->table_name = 'person';
      $this->values['id'] = $id;
      $this->reload();
    }
    
    function getCellsAdditionalButtons($global_settings) {
      $additional = parent::getCellsAdditionalButtons($global_settings);
      $base = config('publicbase');
      if($global_settings->showInlineRequestButtons) {
        $icon = Icons::i('clipboard');
        $title = t('Request');
        $additional .=
<<<HTML
        <a class="button request square-button" 
           title="$title"
           href="$base/request/new?person_id={$this->id}"
        >$icon</a>
HTML;
      }      
      
      if($global_settings->showInlinePaymentButtons) {
        $icon = Icons::i('money');
        $title = t('Payment');
        $additional .=
<<<HTML
        <a class="button payment square-button" 
           title="$title"
           href="$base/payment/new?person_id={$this->id}"
        >$icon</a>
HTML;
      }      

      if($global_settings->showInlineBalanceButtons) {
        $icon = Icons::i('file-text');
        $title = t('All time balance');
        $additional .=
<<<HTML
        <a class="button balance square-button" 
           title="$title"
           href="$base/person/{$this->id}/balance"
        >$icon</a>
HTML;
      }      

      if($global_settings->showInlineBalanceButtons) {
        $icon = Icons::i('file-text-o');
        $title = t('This year balance');
        $additional .=
<<<HTML
        <a class="button balance square-button" 
           title="$title"
           href="$base/person/{$this->id}/balance/lastyear"
        >$icon</a>
HTML;
      }      

      return $additional;
    }
    
    function validateNewValues($values, $full_check = true) {
      $new_values = parent::validateNewValues($values, $full_check);
      if(!$full_check) {
        return $new_values;
      }
      $customer_number = $new_values['customer_number'] ?? null;
      $person = $this->personFromCustomerNumber($customer_number);
      if($person->id && $person->id !== $this->id) {
        Msg::error(t(
          'Customer number %s is already assigned to %s',
          $customer_number,
          $person->oneLinerDisplay(Record::ADD_LINK)
        ));
        return false;
      }
      return $new_values;
    }
    
    function personFromCustomerNumber($customer_number) {
      if(intval($customer_number)) {
        $result = $this->findFromFilterPath('customer_number/' . $customer_number);
        $ids = $result['ids'];
        $count = $result['count'];
        if($count) {
          return Factory::newPerson($ids[0]);
        }
      }
      return Factory::newPerson();
    }
    
    function personByName($name) {
      $result = $this->findFromFilterPath('person/' . $name);
      $ids = $result['ids'];
      $count = $result['count'];
      if($count) {
        return Factory::newPerson($ids[0]);
      }
      return Factory::newPerson();
    }
    
    function editRoute() {
      $this->customerNumberMessage();
      return parent::editRoute();
    }
    
    function balanceRoute($args = []) {
      $display = $this->displayRoute();
      if(count($args) && $args[0] === 'lastyear') {
        setTitle(t('This year balance'));
        return $display . $this->renderAllPurchases(false);
      }
      setTitle(t('All time balance'));
      return $display . $this->renderAllPurchases();
    }
    
    function getIntroButtons() {
      $output = '';
      if($this->id) {
        $balance_text = t('All time balance');
        $lastyear_balance_text = t('This year balance');
        $base = config('publicbase');
        $output = <<<HTML
    <a class="button" href="$base/person/{$this->id}/balance">$balance_text</a>   
    <a class="button" href="$base/person/{$this->id}/balance/lastyear">$lastyear_balance_text</a>   
HTML;
        if($this->customer_number) {
          $output .= $this->renderPrintCustomerCardButton();
        }
      }
      $output .= $this->renderPrintCardsButton();
      return $output . parent::getIntroButtons();
    }
    
    function renderPrintCustomerCardButton() {
      $n = $this->customer_number;
      $text = t('Print card for this customer');
      return <<<HTML
<a class="button nocover" href="javascript:print.cards($n)">$text</a> 
HTML;
    }
    
    function renderPrintCardsButton() {
      $text = t('Print customer cards');
      return <<<HTML
<a class="button nocover" href="javascript:print.cards()">$text</a> 
HTML;
    }
    
    function renderPrintCards($numbers) {
      $output = '';
      foreach($numbers as $number) {
        $output .= $this->renderPrintCard($number);
      }
      return $output;
    }
    
    function renderPrintCard($number) {
      $settings = new Settings();
      $home_company = Factory::newCompany($settings->home_company_id);
      $customer_number = $this->formatCustomerNumber($number);
      $card_template = $settings->cardTemplate();
      $person = $this->personFromCustomerNumber($number);
      $card_with_company = $home_company->doReplacements($card_template, $settings->home_company_prefix);
      $card_with_person = $person->doReplacements($card_with_company, t($this->className()));
      $card_with_markdown = Factory::parsedown($card_with_person);     
      $barcoder = new Barcode($customer_number);
      $datauri = $barcoder->imageSrc();
      $barcode = <<<HTML
      <figure class="barcode">
        <img src="$datauri">
        <figcaption>$customer_number</figcaption>
      </figure>
HTML;

      $card_with_barcode = str_replace('[barcode]', $barcode, $card_with_markdown);      
      
      return <<<HTML
<div class="card">
  <div class="card-inner">
      $card_with_barcode
  </div>
</div>
HTML;
    }
    
    function formatCustomerNumber($number) {
      $settings = new Settings();
      $prefix = $settings->customer_number_prefix;
      return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
    
    function customerNumberMessage() {
      $max = $this->getMaxCustomerNumber();
      if($max) {
        Msg::msg(t('Max existing customer number is %s', $max));
      } else {
        Msg::msg(t('You haven\'t assigned any customer number yet'));
      }
      Msg::msg(t('Customer numbers are free, you can assign any (unless assigned to some other person)'));
    }
    
    function getMaxCustomerNumber() {
      $sql = <<<SQL
        SELECT
          MAX(`customer_number`)
        FROM
          `person`
SQL;
      return QueryRunner::runQueryColumn($sql)[0];
    }
    
    function specialListButtons() {
      ob_start();
?>
<div class="button-group">
  <span class="button-group-card"><?= t('Selected')?>:</span> 
  <a class="button card-button card-selected" href="javascript:print.selected('cards')"><?= t('Print cards') ?></a>
</div>
<div class="button-group">
  <span class="button-group-card"><?= t('All') ?>:</span> 
  <a class="button card-button card-all" href="javascript:print.all('cards')"><?= t('Print cards') ?></a>
</div>
<?php
      return ob_get_clean();
    }
    
    private function renderPurchases($ids) {
      $global_settings = new Settings();
      $filtered = $this->filteredChanges();
      $statuses = $filtered['statuses'];
      $credits = $filtered['credits'];
      $lines = [[ 
        Icons::i('gear'),
        t('date'),
        t('description'),
        t('price'),
        t('pcs'),
        t('sub'),
        t('status'),
        t('balance'),
      ]];
      $grandtotal = 0;
      $duetotal = 0;
      $classes = [''];
      foreach($ids as $id) {
        $change_instance = new Change($id);
        $due = $statuses[$id]['due'];
        $status = $statuses[$id]['status'];
        $t_status = $statuses[$id]['t_status'];
        $change = factory::newChange($id);
        $itemID = $change->id_item;
        $item = Factory::newItem($itemID);
        $subtotal = $change->price * -$change->change;
        $grandtotal += $subtotal;
        $duetotal += $due;
        if($change->change === 0) {
          $classes[] = 'person-change trial-item';
          $t_status = t('Trial');
        } else {
          $classes[] = 'person-change purchase-item ' . $status . '-item';
        }
        $lines[] = [
          'button' => $change_instance->getCellsAdditionalButtons($global_settings),
          'time' => Factory::formatDateTimeDisplay($change->date),
          'item' => $item->oneLinerDisplay(),
          'price' => Format::money($change->price),
          'quantity' => $change->change ? -$change->change : t('Trial'),
          'subtotal' => Format::money($subtotal),
          'status' => $t_status,
          'due' => $due != 0 ? Format::money(-$due) : '<hr>',
        ];
      }

      $classes[] = 'person-change item-totals';
      $lines[] = [
        'button' => '<hr>',
        'time' => '<hr>',
        'item' => '<hr>',
        'price' => '<hr>',
        'quantity' => t('Total purchased'),
        'subtotal' => Format::money($grandtotal),
        'status' => '<hr>',
        'due' => '<hr>',
      ];
      
      foreach($credits as $credit_id => $credit) {
        $credit_amount = $credit['amount'];
        $duetotal -= $credit_amount;
        $classes[] = 'person-change credits';
        $payment = new Payment($credit_id);
        $lines[] = [
          'button' => $payment->getCellsAdditionalButtons($global_settings),
          'time' => $credit['date'],
          'item' => $credit['notes'],
          'price' => '<hr>',
          'quantity' => '<hr>',
          'subtotal' => '<hr>',
          'status' => t('credit'),
          'due' => Format::money($credit_amount),
        ];
      }

      $classes[] = 'person-change item-totals';
      $lines[] = [
        'button' => '<hr>',
        'time' => '<hr>',
        'item' => '<hr>',
        'price' => '<hr>',
        'quantity' => '<hr>',
        'subtotal' => '<hr>',
        'status' => $duetotal < 0 ? t('total credit') : ($duetotal > 0 ? t('due') : t('balance')),
        'due' => Format::money(-$duetotal),
      ];
      
      return [
        'markup' => Format::tablarizeMatrix($lines, true, $classes),
        'total' => $duetotal,
      ];
    }
    
    function renderAllPurchases($all_years = true) {
      if(!$this->id) {
        return '';
      }
      $change = Factory::newChange();
      
      if(!$all_years) {
        $date = date('Y');
        $changes = $change->findFromFilterPath("date/$date%/id_person/{$this->id}")['ids'];
        $trials = $change->findFromFilterPath("change/0/id_person/{$this->id}")['ids'];
        $pending = $this->filteredChanges()['pending'];
        $ids = array_keys(array_flip($changes) + array_flip($trials) + $pending);
        $h2 = t('This year purchases');
        $icon = Icons::i('print', 2, t('Print this year balance'));
        $h2 .= <<<HTML
  <a class="button right noprint" href="javascript:print.balance({$this->id}, 'lastyear')">$icon</a>
HTML;
      } else {
        $changes = $change->findFromFilterPath("id_person/{$this->id}")['ids'];
        $ids = array_keys(array_flip($changes));
        $h2 = t('All time purchases');
        $icon = Icons::i('print', 2, t('Print all time balance'));
        $h2 .= <<<HTML
  <a class="button right noprint" href="javascript:print.balance({$this->id})">$icon</a>
HTML;
      }
      sort($ids);
      return '<hr><h2>' . $h2 . '</h2>' . $this->renderPurchases($ids)['markup'];
    }
    
    private function renderTodaysTicket() {
      if(!$this->id) {
        return '';
      }
      $change = Factory::newChange();
      $date = date('Y-m-d');
      $changes = $change->findFromFilterPath("date/$date%/id_person/{$this->id}")['ids'];
      $trials = $change->findFromFilterPath("change/0/id_person/{$this->id}")['ids'];
      $pending = $this->filteredChanges()['pending'];
      
      $ids = array_keys(array_flip($changes) + array_flip($trials) + $pending);
      
      sort($ids);
      
      $purchases = $this->renderPurchases($ids);
      
      return $purchases['markup']
        . '<a class="button nocover rapid-payment" '
        . 'title="' . t('Payment') . '" ' 
        . 'href="javascript:person.deposit('.$this->id.', '.str_replace(',', '.', $purchases['total']).')">' 
        . Icons::i('money-bill', 2) 
        . '</a>';
    }
    
    function allChanges() {
      $change = Factory::newChange();
      $changes = $change->findFromFilterPath('id_person/' . $this->id);
      $result = [];
      if($changes['count']) {
        foreach($changes['ids'] as $id) {
          $instance = Factory::newChange($id);
          $result[$id] = -$instance->change * $instance->price;
        }
      }
      ksort($result);
      return $result;
    }
    
    function allPayments() {
      $payment = Factory::newPayment();
      $payments = $payment->findFromFilterPath('id_person/' . $this->id);
      $result = [];
      if($payments['count']) {
        foreach($payments['ids'] as $id) {
          $instance = Factory::newPayment($id);
          if($instance->id_change) {
            $result[$instance->id_change][] = $instance->amount;
          }
        }
      }
      ksort($result);
      return $result;
    }

    function allCredits() {
      $payment = Factory::newPayment();
      $payments = $payment->findFromFilterPath('id_person/' . $this->id);
      $result = [];
      if($payments['count']) {
        foreach($payments['ids'] as $id) {
          $instance = Factory::newPayment($id);
          if(!$instance->id_change) {
            $result[$instance->id] = [
              'amount' => $instance->amount,
              'date' => Factory::formatDateTimeDisplay($instance->date),
              'notes' => $instance->notes,
            ];
          }
        }
      }
      ksort($result);
      return $result;
    }

    function filteredChanges() {
      $changes = $this->allChanges();
      $payments = $this->allPayments();
      $pending_changes = [];
      $statuses = [];
      foreach($changes as $change_id => $amount) {
        $temp_due = $amount;
        $deposits = $payments[$change_id] ?? [];
        foreach($deposits as $deposit) {
          $temp_due -= $deposit;
        }
        $due = round($temp_due * 100) / 100;
        $status = 'paid';
        $t_status = t('paid');
        if($due) {
          $pending_changes[$change_id] = $due;
          if($amount > $due) {
            $status = 'partial';
            $t_status = t('partial');
          } else {
            $status = 'due';
            $t_status = t('due');
          }
        }
        $statuses[$change_id] = [
          'due' => $due,
          'status' => $status,
          't_status' => $t_status,
        ];
      }
      return [
        'pending' => $pending_changes,
        'statuses' => $statuses,
        'credits' => $this->allCredits(),
      ];
    }
    
    static function getPending() {
      $sql = <<<SQL
    SELECT
      `id_person`
    FROM
      `change`
    WHERE
      IFNULL(`id_person`,0) > 0
    AND
      `date` LIKE ?
    GROUP BY
      `id_person`
SQL;
      $ids = QueryRunner::runQueryColumn($sql, [date('Y-m-d').'%']);
      $persons = [];
      foreach($ids as $id) {
        $persons[] = Factory::newPerson($id);
      }
      return $persons;
    }
        
    static function getCurrent() {
      $current_number = $_SESSION['cur_person_number'] ?? 0;
      $person = factory::newPerson();
      return $person->personFromCustomerNumber($current_number);
    }
    
    static function setCurrent(int $number = null) {
      if($number) {
        $inst = factory::newPerson();
        $person = $inst->personFromCustomerNumber($number);
        if($person->customer_number) {
          $_SESSION['cur_person_number'] = $person->customer_number;
          return self::renderPersonsBox();
        }
      } else if($number === 0) {
        unset($_SESSION['cur_person_number']);
        return self::renderPersonsBox();
      }
      return false;
    }
    
    static function renderPersonsBox() {
      $person = self::getCurrent();
      $deactivate_button = '';
      if($person->customer_number) {
        $current = $person->oneLinerDisplay();
        $status = 'active-person';
        $title = t('Deactivate current person');
        $deactivate_button = 
          '<a title="'.$title.'" class="button nocover deactivate-person hide" href="javascript:person.setActive(0)">' 
          . Icons::i('user-times', 2) 
          . '</a>';
      } else {
        $current = t('No current person');
        $status = 'no-active-person';
      }
      ob_start();
?>
<div id="persons-box">
  <div id="current-person"
       data-current-person-name="<?= safeMarkup($person->oneLinerDisplay()) ?>"
        data-current-person-number="<?= $person->customer_number ?>"
        data-current-person-discount="<?= $person->discount ?>"
  >
    <?= $deactivate_button ?>
    <?= Icons::i('user '. $status, 2) ?>
    <?= $current ?>
    <div class="hide">
    <?= $person->renderTodaysTicket() ?>
    </div>
  </div>
</div>
<div class="hide">
<?= self::renderPendingPersonsBoxes() ?>
</div>
<?php
      return ob_get_clean();
    }
    
    static function renderPendingPersonsBoxes() {
      $persons = self::getPending();
      if(!$persons) {
        return '';
      }
      $current = self::getCurrent();
      ob_start();
      foreach($persons as $person) {
        if($person->customer_number === $current->customer_number) {
          continue;
        }
?>
<div class="pending-person-box" data-pending-person-number="<?= $person->customer_number ?>">
  <?= Icons::i('user pending-person', 2) ?>
  <?= $person->oneLinerDisplay() ?>
</div>
<?php
      }
      return ob_get_clean();
    }
    
    static function renderPersonsBoxContainer() {
      if(Document::alternateMode()) {
        return '';
      }
      return '<div id="persons-box-container">' . self::renderPersonsBox() . '</div>';
    }
    
    function recalcRoute() {
      self::recalculateAllBalances();
      Msg::msg(t('All persons\' balances recalculated'));
    }
    
    static function recalculateBalance($id) {
      $sql = <<<SQL
        UPDATE 
          `person` 
        SET 
          `balance` = IFNULL((
              SELECT
                SUM(`amount`)
              FROM 
                `payment`
              WHERE 
                `id_person` = :id
              GROUP BY 
                `id_person`
            ),0) + IFNULL((
              SELECT 
                SUM(`change` * `price`)/100 
              FROM
                `change`
              WHERE 
                `id_person` = :id 
              GROUP BY 
                `id_person`
            ),0),
            `year_purchases` = -IFNULL((
              SELECT 
                SUM(`change` * `price`)/100
              FROM
                `change`
              WHERE 
                `id_person` = :id
              AND
                YEAR(`date`) = YEAR(CURDATE()) 
              GROUP BY 
                `id_person`
            ),0)
        WHERE
          `id` = :id
        LIMIT 1
SQL;
      QueryRunner::runQuery($sql, ['id' => $id]);
    }

    static function recalculateAllBalances() {
      $sql = <<<SQL
        UPDATE 
          `person` 
        SET 
          `balance` = IFNULL((
              SELECT
                SUM(`amount`)
              FROM 
                `payment`
              WHERE 
                `id_person` = `person`.`id`
              GROUP BY 
                `id_person`
            ),0) + IFNULL((
              SELECT 
                SUM(`change` * `price`)/100
              FROM
                `change`
              WHERE 
                `id_person` = `person`.`id` 
              GROUP BY 
                `id_person`
            ),0),
            `year_purchases` = -IFNULL((
              SELECT 
                SUM(`change` * `price`)/100
              FROM
                `change`
              WHERE 
                `id_person` = `person`.`id`
              AND
                YEAR(`date`) = YEAR(CURDATE()) 
              GROUP BY 
                `id_person`
            ),0)
SQL;
      QueryRunner::runQuery($sql);
    }
    
    function defaultSettings() {
      $global_settings = new Settings();
      $settings = parent::defaultSettings();
      unset($settings[$this->table_name]);
      $own_settings = [
        'customer_number' => [
          'widget' => 'number',
          'schema' => self::intSchema(),
          'prefix' => $global_settings->customer_number_prefix,
        ],
        'person' => [
          'widget' => 'text',
          'required' => true,
          'schema' => self::varcharSchema(64),
          'autocomplete' => true,
        ],
        'contact' => [
          'widget' => 'textarea',
          'schema' => self::varcharSchema(256),
        ],
        'discount' => [
          'widget' => 'number',
          'postfix' => '%',
          'schema' => self::tinyintSchema(),
        ],
        'balance' =>  [
          'widget' => 'number',
          'readonly' => true,
          'keep_visible' => true,
          'storagemultiplier' => 100,
          'prefix' => '€',
          'schema' => self::intSchema(),
        ],
        'year_purchases' =>  [
          'widget' => 'number',
          'readonly' => true,
          'keep_visible' => true,
          'prefix' => '€',
          'storagemultiplier' => 100,
          'schema' => self::intSchema(),
        ],
      ];
      return array_merge($settings, $own_settings);
    }
    
  }
