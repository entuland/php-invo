<?php

  namespace App\Pages;

  use App\Utils\Format;
  use App\Utils\Msg;
  use App\Query\QueryRunner;

  // todo integrate field skips!

  class Inventory {
    private $year = null;
    private $data = null;
    private $verified_only = null;
    private static $modes = ['html' => 'html', 'csv' => 'csv'];

    static function main() {
      setTitle(t('Inventory'));
      return Inventory::overview();
    }

    function __construct($year, $verified_only) {
      $this->year = $year;
      $this->verified_only = $verified_only;
      $this->data = $this->getData();
    }

    static function overview() {
      $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
      $mode = filter_input(INPUT_GET, 'mode');
      $verified_only = filter_input(INPUT_GET, 'verified');
      if($year && in_array($mode, self::$modes)) {
        $inventory = new Inventory($year, $verified_only);
        if($mode === 'html') {
          return $inventory->getAsHTML();
        }
        return $inventory->getAsCSV();
      }
      return self::getForm();
    }

    static private function getForm() {
      Ob_start();
      $years = self::getYearInterval();
      $options = [];
      for($year = $years['min']; $year <= $years['max'] + 1; ++$year) {
        $options[intval($year)] = intval($year);
      }
      $year_select = Format::select($options, intval($years['max']), 'year');
      $mode_select = Format::select(self::$modes, 'csv', 'mode');
      $yesNo = [
        0 => t('no'),
        1 => t('yes'),
      ];
      $verified_select = Format::select($yesNo, t('yes'), 'verified');
?>
<p><?= t('The year refers to its beginning (January the 1st)') ?></p>
<p><?= t('Verified = NO will output both verified and unverified stocks') ?></p>
<form method="get" target="_blank">
  <table>
    <tr>
      <th><?= t('Year') ?></th>
      <th><?= t('verified') ?></th>
      <th><?= t('Mode') ?></th>
    </tr>
    <tr>
      <td><?= $year_select ?></td>
      <td><?= $verified_select ?></td>
      <td><?= $mode_select ?></td>
    </tr>
  </table>
  <button type="submit"><?= t('Generate') ?></button>
</form>
<?php
      return Ob_get_clean();
    }

    static function getYearInterval() {
      $sql = "
        SELECT
          MIN(YEAR(`date`)) as `min`,
          MAX(YEAR(`date`)) as `max`
        FROM
          `change`
      ";
      return QueryRunner::runCachedQuery($sql);
    }

    private function getData() {
      $year = intval($this->year);
      $yes = mb_strtoupper(t('yes'));
      $no = mb_strtoupper(t('no'));
      $sql = <<<SQL
SELECT
          `item`.`id`,
          `barcode`,
          `description`,
          `make`,
          `article`,
          `producer_code`,
          `size`,
          `color`,
          ROUND(`price` / 100, 2) AS `sell_price`,
          ROUND(`B`.`sums` / 100, 2) AS `computed_stock`,
          IF(`verified`, '$yes', '$no') AS `verified`,
          ROUND((SELECT `sell_price`) * (SELECT `computed_stock`), 2) AS `total`
        FROM
          `item`
            INNER JOIN (
              SELECT
                `change`.`id_item`,
                sum(`change`.`change`) AS sums
              FROM
                `change`
              WHERE
                YEAR(`change`.`date`) < $year
              GROUP BY
                `change`.`id_item`
              ) B
            ON
              `B`.`id_item` = `item`.`id`

            INNER JOIN
              `make`
            ON
              `make`.`id` = `item`.`id_make`

            INNER JOIN
              `color`
            ON
              `color`.`id` = `item`.`id_color`

            INNER JOIN
              `size`
            ON
              `size`.`id` = `item`.`id_size`


            WHERE
              ROUND(`B`.`sums`) <> 0

SQL;
      if($this->verified_only) {
        $sql .= ' AND `verified` = 1';
      }

      return QueryRunner::runCachedQuery($sql);
    }

    private function getTablarizedData($add_euro = false) {
      if(!count($this->data)) {
        Msg::warning(t('Empty inventory!'));
        return false;
      }
      $result = [
        'headers' => array_keys($this->data[0]),
        'rows' => [],
      ];
      $result['headers'] = array_map('t', $result['headers']);
      foreach($this->data as $row) {
        $row['sell_price'] = str_replace('.', ',', $row['sell_price']);
        $row['computed_stock'] = str_replace('.', ',', $row['computed_stock']);
        $row['total'] = str_replace('.', ',', $row['total']);
        if($add_euro) {
          $row['sell_price'] = '€ ' . $row['sell_price'];
          $row['total'] = '€ ' . $row['total'];
        }
        $result['rows'][] = array_values($row);
      }
      return $result;
    }

    function getAsHTML() {
      $add_euro = true;
      $table_data = $this->getTablarizedData($add_euro);
      if(!$table_data) {
        return;
      }
      Ob_start();
      $title = 'TeknoSport - ' . t('Inventory') . ' - 01/01/' . $this->year;
      if($this->verified_only) {
        $title .= ' - ' . t('verified');
      }
      $headers_row = '<tr>'
        . implode(Format::wrap('<th>', $table_data['headers'], '</th>'))
        . '</tr>';
      $table_rows = array_map(function($row) {
       return '<tr>'
        . implode(Format::wrap('<td>', $row, '</td>'))
         . '</tr>';
      }, $table_data['rows']);
      unused($headers_row, $table_rows);
      require 'templates/inventory.tpl.php';
      echo Ob_get_clean();
      die();
    }

    function getAsCSV() {
      $table_data = $this->getTablarizedData();
      header('Content-Disposition: attachment; filename="' . config('dbname') . '-' . $this->year . '.csv"');
      header('Content-Type: text/csv; charset=utf-8');
      $out = fopen('php://output', 'w');
      fputcsv($out, $table_data['headers']);
      foreach($table_data['rows'] as $row) {
        fputcsv($out, $row);
      }
      fclose($out);
      die();
    }

  }
