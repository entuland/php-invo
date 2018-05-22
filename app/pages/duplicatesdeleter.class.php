<?php

  namespace App\Pages;
  
  use App\Utils\Database;
  use App\Utils\Msg;
  use App\Utils\Format;
  
  class DuplicatesDeleter {
    private $db;
    private $checktables = [
      'make',
      'color',
      'um',
      'size',
    ];
    
    static function main() {
      setTitle(t('Delete duplicated records'));
      $dup = new DuplicatesDeleter();
      return $dup->form();        
    }
    
    function __construct() {
      $this->db = Database::getConnection();
    }
    
    private function getDuplicates($table) {
      $sql = "
        SELECT 
          TRIM(`$table`) AS 'value',
          COUNT(TRIM(`$table`)) AS 'count'
        FROM
          `$table`
        GROUP BY
          TRIM(`$table`)
        ORDER BY
          TRIM(`$table`)
        DESC";
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      $result = array_filter($result, function($array) {
        return $array['count'] > 1;
      });
      return $result;
    }
    
    private function getCandidates($table) {
      $dups = $this->getDuplicates($table);
      $ids = [];
      foreach($dups as $dup) {
        $value = $dup['value'];
        $count = intval($dup['count']);
        $sql = "
          SELECT id
          FROM `$table`
          WHERE TRIM(`$table`) = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if(intval($dup['count']) === count($result)) {
          $ids[$value] = $result;
        }
        else {
          Msg::error("Mismatch: $table $value $count");
        }
      }
      return $ids;
    }
    
    private function uniformReferences($table) {
      $candidates = $this->getCandidates($table);
      foreach($candidates as $value => $ids) {
        if(count($ids) < 2) {
          Msg::warning(t('Skipping value %s from table %s', $value, $table));
          continue;
        }
        $first = array_shift($ids);
        foreach($ids as $id) {
          $this->replaceInItem($table, $id, $first);
          $this->deleteFromTable($table, $id);
        }
      }
    }
    
    private function deleteFromTable($table, $id) {
      $sql = 
        "DELETE FROM
          `$table`
        WHERE
          `id` = ?";
      $stmt = $this->db->prepare($sql);
      if(!$stmt->execute([$id])) {
        Msg::error(t('Deletion of ID %s from table %s failed', $id, $table));
      }
    }
    
    private function replaceInItem($table, $from, $to) {
      $sql = 
        "UPDATE
          `item`
        SET
          `id_$table` = :to
        WHERE
          `id_$table` = :from";
      $stmt = $this->db->prepare($sql);
      if(!$stmt->execute([
            'to' => $to,
            'from' => $from,
          ])) {
        Msg::error(t('Update of %s from %s to %s failed', $table, $from, $to));
      }
    }

    private function deleteDuplicates() {
      foreach($this->checktables as $table) {
        $this->uniformReferences($table);
      }
    }
    
    private function getPreview() {
      $preview = [];
      foreach($this->checktables as $table) {
        $ids = $this->getCandidates($table);
        if($ids) {
          $preview[$table] = $ids;
        }
      }
      return $preview;
    }
    
    function form() {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $confirm = getPost('confirm_delete_duplicates');
        if($confirm === 'confirm') {
          $this->deleteDuplicates();
          Msg::msg(t('All duplicated records were deleted'));
        }
      }
      $preview = $this->getPreview();
      if(count($preview)) {
        Format::dump($preview);
        Ob_start();
?>
<form method="POST">
  <input type="hidden" name="confirm_delete_duplicates" value="confirm">
  <button class="button delete"
          type="submit"
  ><?= t('Delete duplicated records') ?></button>
</form>
<?php
        return Ob_get_clean();
      }
      else {
        Msg::msg(t('No duplicated records to delete'));
      }
    }
  }