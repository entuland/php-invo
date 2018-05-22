<?php
  namespace App\Records\Traits;
  
  use App\Utils\Msg;
  use App\Router;
  
  trait Delete {
    
    function deleteRoute() {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $this->processDeletionPost();
      }
      setTitle(t('Confirm deletion of %s', $this->oneLinerDisplay()));
      return $this->renderDeletionForm()
             . $this->renderDisplay()
             . $this->childrenHelper();
    }
    
    function safeDelete($log_prefix = '') {
      if(is_string($log_prefix)) {
        $log_prefix .= '-';
      }
      $identifier = $this->identifier();
      $count = $this->childrenCount();
      if($count) {
        Msg::warning($this->lockedMessageText($count));
        return false;
      }
      $this->selfLog($log_prefix . 'pre-delete');
      if(!$this->delete()) {
        Msg::error(t('Error during deletion of %s, database error', $identifier));
        return false;
      }
      Msg::msg(t('%s deleted successfully', $identifier));      
      return true;
    }

    function lockedMessageText($count) {
      $text = t('Unable to delete %s', $this->identifier()); 
      $text .= ' ';
      $text .= nt('it has %d reference', 
                  'it has %d references', 
                  $count, 
                  $count);
      return $text;
    }
    
    function processDeletionPost() {
      $id = $this->id;
      $class = $this->className();
      if($id) {
        $redirection = "$class/$id/delete";
      }
      else {
        $redirection = $class;
      }
      doubleSubmissionCheck($redirection);
      $posted_id = getPOST('id', FILTER_VALIDATE_INT);
      $action = getPOST('action');
      if($posted_id !== $this->id || $action !== 'delete') {
        Msg::error(t('Bad submission!'));
        Router::redirect(config('publicbase') . '/'. $redirection);
      }
      if($this->safeDelete()) {
        Router::redirect(config('publicbase') . '/'. $class);      
      }
    }

    function renderDeletionForm() {
      Ob_start();
      $this->renderDisplay();
      $count = $this->childrenCount();
      if($count) {
        Msg::warning($this->lockedMessageText($count));
      }
      else {
?>
<form method="POST" class="form-delete record-<?= $this->className() ?>">
  <input type="hidden" name="submission_id" value="<?= \UUID::v4()?>">
  <input type="hidden" name="id" value="<?= $this->id ?>">
  <button class="button delete" 
          type="submit" 
          name="action" 
          value="delete"
  ><?= t('Confirm') ?></button>
</form>
<?php 
      }
      $output = Ob_get_clean();
      return $output;
    }
    
  }