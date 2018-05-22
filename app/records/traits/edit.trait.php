<?php

  namespace App\Records\Traits;
  
  use App\Records\Record;
  use App\Utils\Field;
  use App\Utils\Msg;
  use App\Utils\Format;
  use App\Utils\Dynamic;
  use App\Router;
  use App\Records\Factory;
  
  trait Edit {
    
    function editRoute() {
      $class = $this->className();
      $id = $this->id;
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        if(!$this->processEditPost()) {
          Router::redirect(config('publicbase') . '/'. Router::latestRoute());
        }
      }
      if($id) {
        setTitle(t('Edit') . ' ' . t($class) . ' ' . $id);
      }
      else {
        setTitle(ct('male', 'New') . ' ' . t($class) . ' ' . $id);
        if($this->gender() === 'female') {
          setTitle(ct('female', 'New') . ' ' . t($class) . ' ' . $id);
        }
      }
      $this->setContext(Record::EDIT_CONTEXT);
      return $this->getIntroButtons()
             . $this->renderEditForm()
             . $this->childrenHelper();
    }
  
    function processEditPost() {
      $id = $this->id;
      $class = $this->className();      

      if($id) {
        $redirection = "$class/$id/edit";
      }
      else {
        $redirection = $class;
      }
      doubleSubmissionCheck($redirection);
      
      if(!$this->validateEditId(getPOST('id', FILTER_VALIDATE_INT))) {
        return false;
      }
      
      $test_values = [];
      foreach($this->settings() as $field_name => $set) {
        $value = trim(getPOST($field_name));
        $reference = $set['reference'] ?? '';
        $autocomplete = $set['autocomplete'] ?? false;
        if($reference && $autocomplete) {
          $inst = Factory::newInstance($reference);
          $value = $inst->getForcedID($value);
        }
        $test_values[$field_name] = $value;
      }
      
      $new_values = $this->validateNewValues($test_values);
      if($new_values === false) {
        return false;
      }

      if(!$this->validateUniqueColumn($new_values)) {
        return false;
      }
      
      $is_new = !$id;     
      if(!$is_new) {
        $this->selfLog('pre-edit');
      }
      
      foreach($new_values as $field_name => $value) {
        if($field_name === 'verified' && trim($value) === '') {
          $value = null;
        }
        $this->values[$field_name] = $value;
      }
      
      return $this->processEditPostSave($is_new);
    }
    
    function processEditPostSave($is_new) {
      if($is_new) {
        $this->selfLog('pre-create');
      }
      else {
        $this->selfLog('pre-save');
      }
      
      if(!$this->save()) {
        Msg::error(t('Error during save of %s, database error', 
          $this->identifier()
        ));
        return false;
      }
      $redirection = $this->processEditPostRedirection($is_new);
      Router::redirect(config('publicbase') . '/'. $redirection);
    }
    
    function processEditPostRedirection($is_new) {
      $edit_button = $this->identifier(Record::ADD_LINK);
      $created = ct('male', 'created');
      $saved = ct('male', 'saved');
      if($this->gender() === 'female') {
        $created = ct('female', 'created');
        $saved = ct('female', 'saved');        
      }
      $action = $saved;
      if($is_new) {
        $action = $created;
      }
      Msg::warning(t('%s %s successfully', $edit_button, $action));
      return $this->className();     
    }
    
    function prepareEditForm($form) {
      $form->class = 'record-' . $this->className();
      $form->table_class = 'record-' . $this->className();
      
      if($this->listContext()) {
        $form->class .= ' list list-edit';
      }
      else {
        $form->class .= ' single-edit';
      }
      
      $values = $this->values;
      $fields = new \stdClass();
      $settings = $this->settings();
      foreach($this->fields() as $field_name) {
        $value = $values[$field_name] ?? null;
        $field_settings = $settings[$field_name];
        $field = new Field($field_settings, $value, $this->className(), $field_name);
        if($this->listContext() && $field->readonly) {
          continue;
        }
        $fields->{$field_name} = $field;
      }

      $form->fields = $fields;
      
      $form->unique_class = $this->className();
      $form->unique_field = $this->uniqueColumn();

      if($this->id) {
        $form->class .= ' edit-record edit-' . $this->className();
        Ob_start();
        ?>
  <a class="button delete" 
     data-context="edit-form" 
     data-class="<?= $this->className() ?>" 
     data-id="<?= $this->id ?>" 
     href="<?= config('publicbase') . '/' . $this->deleteURL() ?>"
  ><?= t('delete') . ' ' . t($this->className()) ?></a>
        <?php
        $form->buttons .=Ob_get_clean();
      } else {
        $form->class .= ' new-record new-' . $this->className();
      }

      if($this->galleryEnabled()) {
        $form->after_form = $this->getGallery()->renderEdit();
      }
      
      return $form;
    }
    
    function renderEditForm($params = []) {
      $formObj = new Dynamic($params);
      $form = $this->prepareEditForm($formObj);
      return $this->renderEditFormObject($form);
    }
    
    function renderEditFormObject($form) {
      Ob_start();
?>
    <button class="button save" 
            type="submit" 
            name="action" 
            value="save"
    ><?= t('Save') ?></button>
    <button class="button reset" 
            type="reset"
    ><?= t('Reset') ?></button>
    <a class="button clear" href="javascript:"
    ><?= t('Clear') ?></a>
<?php
      $form->buttons = Ob_get_clean() . $form->buttons;
      return Format::renderFormObject($form, $this->context);
    }

  }
