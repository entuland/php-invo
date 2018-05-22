<?php

  namespace App\Pages;
  
  use App\Utils\Database;
  use App\Utils\Format;
  use App\Utils\Msg;
  
  class UnusedDeleter {
    private $db;
    private $checktables = [
      'make',
      'color',
      'um',
      'size',
    ];
    
    static function main() {
      setTitle(t('Delete unused records'));
      $unu = new UnusedDeleter();
      return $unu->form();        
    }

    function __construct() {
      $this->db = Database::getConnection();
    }
        
    private function getCandidates($table) {
      $sql = 
        "SELECT 
          `id_$table`
        FROM
          `item`
        GROUP BY
          `id_$table`";
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $used = $stmt->fetchAll(\PDO::FETCH_COLUMN);
      
      $imploded_used = implode(',', $used);
      $sql = 
        "SELECT 
          `id`, `$table`
        FROM
          `$table`
        WHERE
          `id` NOT IN ($imploded_used)";
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $unused = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
      
      return $unused;      
    }
    
    private function deleteUnusedOf($table) {
      $candidates = $this->getCandidates($table);
      $imploded_candidates = implode(',', array_keys($candidates));
      $sql = 
        "DELETE FROM
          `$table`
        WHERE
          `id` IN ($imploded_candidates)";
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
    }
    
    private function deleteUnused() {
      foreach($this->checktables as $table) {
        $this->deleteUnusedOf($table);
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
        $confirm = getPost('confirm_delete_unused');
        if($confirm === 'confirm') {
          $this->deleteUnused();
          Msg::msg(t('All unused records were deleted'));
        }
      }
      $preview = $this->getPreview();
      if(count($preview)) {
        Format::dump($preview);
        Ob_start();
?>
<form method="POST">
  <input type="hidden" name="confirm_delete_unused" value="confirm">
  <button type="submit"><?= t('Delete unused records') ?></button>
</form>
<?php
        return Ob_get_clean();
      }
      else {
        Msg::msg(t('No unused records to delete'));
      }
    }
  }
