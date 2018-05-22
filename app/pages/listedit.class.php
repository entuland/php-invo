<?php

  namespace App\Pages;
  
  use App\Records\Factory;
  use App\Utils\Field;
  use App\Utils\Format;
  use App\Utils\Msg;
  use App\Utils\Dynamic;
  use App\Records\Document;
  use App\Records\Record;
  use App\Query\QueryRunner;
  
  // todo implement send via mail
  
  class ListEdit {
    
    private static $list_errors = [];
    
    static function main() {
      setTitle(t('Mass edit'));
      setMainClasses('list-edit');
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        $ids = getPost('id', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        if(!$ids) {
          Msg::warning(t('No rows selected for mass editing'));
          return '';
        }
        $class = getPost('list-class');
        if(!$class) {
          Msg::error(t('Invalid mass edit class'));
          return '';
        }
        Msg::warning(self::backupNotice());
        $action = getPost('list-action');
        $button_action = getPOST('button-action');
        switch(true) {
          case $class === 'change' && $button_action === 'confirm-move-changes':
            return self::confirmMoveChanges($ids);
          case $class === 'change' && $button_action === 'process-move-changes':
            return self::processMoveChanges($ids);
          case $action === 'prepare':
            return self::renderForms($class, $ids);
          case $action === 'delete':
            return self::processDelete($class, $ids);
          case $action === 'edit':
            return self::processEdit($class, $ids);
          default:
            Msg::error(t('Invalid mass edit submission'));
        }
      }
    }
    
    private static function confirmMoveChanges($ids) {
      $form = new Dynamic();
      $form->before_table = self::renderSelectionBox('change', $ids);
      $curdoc = Document::getCurrent();
      $form->caption = t('Confirm moving the above changes into %s?', $curdoc->oneLinerDisplay());
      $form->buttons = 
        '<input type="hidden" name="list-class" value="change">'
        . '<input type="hidden" name="list-document-id" value="'.$curdoc->id.'">'
        . '<button class="button" name="button-action" value="process-move-changes">'
        . t('Confirm, move them')
        . '</button>';
      return Format::renderFormObject($form);
    }
    
    private static function processMoveChanges($list_ids) {
      $doc_id = getPOST('list-document-id', FILTER_VALIDATE_INT);
      if(!$doc_id) {
        Msg::error(t('Missing document ID in order to move changes'));
        return;
      }
      $curdoc = Document::getCurrent();
      if($curdoc->id !== $doc_id) {
        Msg::error(t('Chosen document is not the current document any more, please start over'));
        return;
      }
      $ids = array_keys($list_ids);
      $qMarks = str_repeat('?,', count($ids) - 1) . '?';
      $sql = "UPDATE `change` SET `id_document` = $doc_id WHERE `id` IN ($qMarks)";
      QueryRunner::runQuery($sql, $ids);
      Msg::msg(t('All changes moved successfully'));
      Msg::msg(t('(if you see no error messages above)'));
    }

    private static function renderForms($class, $ids) {
      $editable_classes = [
        'item',
        'document',
        'request',
      ];
      $output = '';
      if(in_array($class, $editable_classes)) {
        $output .= self::renderEditForm($class, $ids);
      }
      else {
        Msg::warning(t('Mass edit of %s not supported', t($class)));
      }
      $output .= self::renderDeleteForm($class, $ids);
      return $output;
    }
    
    private static function deletableRecords($class, $ids) {
      $deletable = [];
      $undeletable = [];
      foreach(array_keys($ids) as $id) {
        $instance = Factory::newInstance($class, $id);
        if($instance->childrenCount()) {
          $undeletable[] = $instance;
        }
        else {
          $deletable[] = $instance;
        }
      }
      if(count($undeletable)) {
        foreach($undeletable as $instance) {
          Msg::warning(t(
            'Unable to delete %s, has %d dependencies', 
            $instance->oneLinerDisplay(),
            $instance->childrenCount()
          ));
        }
        return false;
      }
      return $deletable;
    }
    
    private static function processDelete($class, $ids) {
      doubleSubmissionCheck();
      $deletable = self::deletableRecords($class, $ids);
      if(!$deletable) {
        Msg::warning(t('Mass delete operation cancelled, no records were deleted'));
        return self::renderForms($class, $ids);
      }
      $errors = 0;
      foreach($deletable as $instance) {
        $instance->selfLog('list-pre-delete');
        if(!$instance->delete()) {
          Msg::error(t('Error deleting %s!', $instance->oneLinerDisplay())); 
          ++$errors;
        }
      }
      if($errors) {
        Msg::error(t(
          'Unable to delete %d records out of %d. %s',
          $errors,
          count($deletable),
          self::recoverNotice()
        ));
      }
      else {
        Msg::msg(t(
          'All %d records were successfully deleted. %s', 
          count($deletable),
          self::recoverNotice()
        ));
      }
    }
    
    private static function prepareFields($class) {
     $enabled_fields = getPost('list-enabled', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
      if(!$enabled_fields) {
        Msg::warning(t('No field selected for mass editing!'));
        return false;
      }
      $fields = [];
      $instance = Factory::newInstance($class);
      $field_settings = $instance->settings();
      foreach(array_keys($enabled_fields) as $field_name) {
        $value = getPost($field_name);
        $field = new Field($field_settings[$field_name], $value, $instance->className(), $field_name);
        $fields[] = $field;
      }
      return $fields;
    }
    
    private static function processInstance($class, $id, $fields) {
      if(!intval($id)) {
        Msg::error(t('Invalid ID %s during mass edit!', $id));
        return false;
      }
      $local_errors = 0;
      foreach($fields as $field) {
        if(!$field->valid()) {
          self::$list_errors[$field->name] = true;
          ++$local_errors;
        }
      }
      if($local_errors) {
        return false;
      }
      $instance = Factory::newInstance($class, $id);
      $instance->selfLog('list-pre-edit');
      foreach($fields as $field) {
        $instance->{$field->name} = $field->storageValue();
      }
      $instance->selfLog('list-pre-save');
      return $instance->save();
    }
    
    private static function processEdit($class, $ids) {
      $fields = self::prepareFields($class);
      if(!$fields) {
        return;
      }
      $errors = 0;
      self::$list_errors = [];
      foreach(array_keys($ids) as $id) {
        $result = self::processInstance($class, $id, $fields);
        if(!$result) {
          ++$errors;
        }
      }
      if(count(self::$list_errors)) {
        $failures = implode(', ', array_map('t', array_keys(self::$list_errors)));
        Msg::error(t('The following fields are required or invalid: %s', $failures));
      }
      if($errors) {
        Msg::error(t(
          'Error during save of %d out of %d records',
          $errors,
          count($ids)
        ));
      }
      else {
        Msg::msg(nt(
          '%d record saved successfully',
          '%d records saved successfully',
          count($ids),
          count($ids)
        ));
      }
    }
    
    private static function recoverNotice() {
      $log_link = '<a class="button" href="'.config('publicbase').'/log">' . t('log') . '</a>';
      $backup_link = '<a class="button" href="'.config('publicbase').'/backup">' . t('backup') . '</a>';
      return t(
        'Check pages %s and %s to recover data if necessary',
        $log_link,
        $backup_link
      );
    }    
    
    private static function backupNotice() {
      $backup_link = '<a class="button" href="'.config('publicbase').'/backup">' . t('backup') . '</a>';
      return t(
        'Creating a backup is advised before performing mass edits/deletes: %s',
        $backup_link
      );
    }
    
    private static function renderEditForm($class, $ids) {
      $instance = Factory::newInstance($class);
      $instance->addContext(Record::LIST_CONTEXT);
      $additional = '<input type="hidden" name="list-action" value="edit">';
      $additional .= '<input type="hidden" name="list-class" value="'.$class.'">';
      $additional .= self::renderSelectionBox($class, $ids);
      $params = [
        'before_table' => $additional,
      ];
      return $instance->renderEditForm($params);
    }
    
    private static function renderDeleteForm($class, $ids) {
      $list = [];
      foreach(array_keys($ids) as $id) {
        $instance = Factory::newInstance($class, $id);
        $list[$id] = $instance->childrenCount();
      }
      Ob_start();
?>
<hr>
<h1><?= t('Mass delete') ?></h1>
<form method="POST" class="list list-delete">
    <input type="hidden" name="submission_id" value="<?= \UUID::v4()?>">
    <input type="hidden" name="list-class" value="<?= $class ?>">
    <input type="hidden" name="list-action" value="delete">
    <?= self::renderSelectionBox($class, $list) ?>
    <button class="button delete"
            type="submit"
    ><?= t('Mass delete') ?></button>
</form>
<?php
      return Ob_get_clean();
    }

    private static function renderSelectionBox($class, $ids) {
      $container_id = \UUID::v4();
      $count_id = \UUID::v4();
      $rows = [];
      $valid_ids = 0;
      foreach($ids as $id => $count) {
        if(!intval($count)) {
          ++$valid_ids;
        }
        $rows[] = self::renderSelectionRow($id, $class, $container_id, $count_id, $count);
      }
      $markup = '<table><tbody>' . implode($rows) . '</tbody></table>';
      $count_placeholder = " (<span id='$count_id'>$valid_ids</span>)";
      $title = t('Affected elements') . $count_placeholder;
      
      return Format::collapsible($markup, $title, $container_id);
    }
  
    private static function renderSelectionRow($id, $class, $container_id, $count_id, $count) {
      Ob_start();
      $instance = Factory::newInstance($class, $id);
      $display_value = $instance->oneLinerDisplay();
      if(intval($count)) {
        ?>
          <tr>
            <td>
              [<?= t('Locked, %d dep.', $count) ?>]
              <?= $display_value ?>
            <td>
          </tr>
        <?php
      }
      else {
        ?>
          <tr class="selected">
            <td>
              <label>
                <input 
                  class="countable-checkbox"
                  type="checkbox" 
                  name="id[<?= $id ?>]"
                  data-container-id="<?= $container_id ?>"
                  data-count-id="<?= $count_id ?>"
                  checked
                >
                <?= $display_value ?>
              </label>
            <td>
          </tr>
        <?php
      }
      return Ob_get_clean();
    }
    
    
  }

