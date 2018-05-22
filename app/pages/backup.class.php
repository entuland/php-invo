<?php

  namespace App\Pages;

  use App\Utils\Format;
  use App\Utils\Msg;
  use App\Utils\Logger;
  use App\Query\QueryRunner;
  use App\Pages\Schema;

  class Backup {
    const BACKUP_FOLDER = 'files/backup';

    function __construct() {
      ensureFolder(self::BACKUP_FOLDER);
    }

    static function main() {
      setTitle(t('Database backup'));
      $backup = new Backup();
      return $backup->manager();
    }

    function manager() {
      if(!enabledFunction('exec')) {
        Msg::error(t('exec function disabled'));
        return;
      }
      $this->checkPost();
      return $this->backupForm() . $this->restoreForm();
    }

    private function newFilename($tag = '') {
      $fixed_tag = preg_replace('#[^\w\d\.\-_]#', '', $tag);
      if($fixed_tag) {
        $fixed_tag = '_' . $fixed_tag;
      }
      return self::BACKUP_FOLDER
        . '/' . config('dbname') . '_'
        . date('Y-m-d_H.i.s')
        . $fixed_tag
        .'.sql';
    }

    private function unlink($filename) {
      if(unlink($filename)) {
        Msg::msg(t('Unlink %s succeeded', $filename));
        return true;
      }
      Msg::error(t('Unlink %s failed', $filename));
      return false;
    }

    private function backup($tag = '') {
      $filename = $this->newFilename($tag);
      $chunks = ['mysqldump'];
      $chunks[] = config('dbname');
      $chunks[] = '-u' . config('dbuser');
      $chunks[] = '-p' . config('dbpass');
      $chunks[] = '--add-drop-table';
      $chunks[] = '--no-autocommit';
      $chunks[] = '>';
      $chunks[] = windowsStylePath($filename);
      $export_success = $this->execute($chunks, t('Database export'));
      $unlink_success = false;
      if($export_success) {
        $report = (object)[
          'dumpfile' => $filename,
        ];
        Logger::log($report, 'database', 'backup');
        compress($filename);
        $unlink_success = $this->unlink($filename);
      }
      return $export_success && $unlink_success;
    }

    private function validFilename($filename) {
      if(!maybeInFolder(self::BACKUP_FOLDER, $filename)) {
        Msg::error(t('Invalid filename %s, not in the backup folder', $filename));
        return false;
      }
      if(!file_exists($filename)) {
        Msg::error(t('Invalid filename %s, file does not exist', $filename));
        return false;
      }
      if(!preg_match('#.*\.sql\.gz$#', $filename)) {
        Msg::error(t('Invalid filename %s, bad extension', $filename));
        return false;
      }
      return true;
    }

    private function emptyDatabase() {
      $schema = Schema::getInstance()->getCurrentSchema();
      $tables = array_keys($schema);
      if(count($tables)) {
        $wrapped = Format::wrap('`', $tables, '`');
        $imploded = implode(', ', $wrapped);
        $sql = 'DROP TABLE IF EXISTS ' . $imploded;
        QueryRunner::runQuery($sql);
      }
    }

    private function restore($compressed) {
      if(!$this->validFilename($compressed)) {
        return false;
      }
      Msg::msg(t('Filename %s is valid', $compressed));
      if(!$this->backup('auto')) {
        return false;
      }
      return $this->confirmedRestore($compressed);
    }

    private function confirmedRestore($compressed) {
      $filename = decompress($compressed);
      if($filename === false) {
        return false;
      }
      $this->emptyDatabase();
      $chunks = ['mysql'];
      $chunks[] = config('dbname');
      $chunks[] = '-u' . config('dbuser');
      $chunks[] = '-p' . config('dbpass');
      $chunks[] = '<';
      $chunks[] = windowsStylePath($filename);
      $import_success = $this->execute($chunks, t('Database import'));
      $unlink_success = false;
      if($import_success) {
        $report = (object)[
          'dumpfile' => $filename,
        ];
        Logger::log($report, 'database', 'restore');
        $unlink_success = $this->unlink($filename);
      }
      return $import_success && $unlink_success;
    }

    private function execute($chunks, $description) {
      $command = implode(' ', $chunks);
      $output = [];
      $result = null;
      exec($command, $output, $result);
      foreach($output as $line) {
        Msg::warning(mb_convert_encoding($line, 'utf-8', 'cp850'));
      }
      if(!$result) {
        Msg::msg($description . ': ' . t('success'));
        return true;
      }
      Msg::error($description . ': ' . t('failure'));
      return false;
    }

    private function checkPost() {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
        doubleSubmissionCheck('backup');
        $action = mb_strtoupper(getPost('action'));
        if($action === 'BACKUP') {
          $this->backup('manual');
        }
        else if($action === 'RESTORE') {
          $filename = getPost('filename');
          $this->restore(self::BACKUP_FOLDER . '/' . $filename);
        }
        else {
          Msg::error(t('Unrecognized action %s', $action));
        }
      }
    }

    private function backupForm() {
      Ob_start();
?>
<form method="POST">
  <input type="hidden" name="submission_id" value="<?= \UUID::v4()?>">
  <input type="hidden" name="action" value="backup">
  <button type="submit"><?= t('Backup database') ?></button>
  <a class="button" href="<?= config('publicbase') ?>/schema"><?= t('Database Schema') ?></a>
  <a class="button" href="<?= config('publicbase') ?>/query-runner"><?= t('Query runner') ?></a>
</form>
<?php
      return Ob_get_clean();
    }

    private function restoreForm() {
      $entries = scandir(self::BACKUP_FOLDER);
      $filenames = array_filter($entries, function($entry) {
        return preg_match('#.*\.sql\.gz$#', $entry);
      });
      if(!count($filenames)) {
        return '';
      }
      $options = ['<option>-- ' . t('select') . ' --</option>'];
      foreach($filenames as $filename) {
        $size = filesize(self::BACKUP_FOLDER . '/' . $filename);
        $text = $filename . ' [' . Format::bytes($size) . ']';
        $options[] = '<option value="' . $filename . '">' . $text . '</option>';
      }
      Ob_start();
?>
<h1><?= t('Restore database from a previous dump') ?></h1>
<p><?= t('Before attempting to restore the current database will be backed up automatically') ?></p>
<form method="POST">
  <input type="hidden" name="submission_id" value="<?= \UUID::v4()?>">
  <input type="hidden" name="action" value="restore">
  <select name="filename">
    <?= implode($options) ?>
  </select>
  <button type="submit"><?= t('Restore database') ?></button>
</form>
<?php
      return Ob_get_clean();
    }
  }
