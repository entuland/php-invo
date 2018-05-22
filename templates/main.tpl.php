<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title><?= config('sitename') ?> | <?= $title_tag ?></title>
    <link rel="stylesheet" href="<?= config('publicbase') ?>/res/fa/web-fonts-with-css/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="<?= config('publicbase') ?>/css/main.css">
    <link rel="stylesheet" title="theme-css" href="<?= config('publicbase') . '/' . $theme_css ?>">
<?php

  if($valid_user) {
    ?><link rel="gettext" type="application/x-po" href="<?= config('publicbase') ?>/locale/<?= config('localecode') ?>/LC_MESSAGES/app.po"><?php 
    print $javascript;
    print $js_settings;
  }

?>
  </head>
<body>
<?php
  if($valid_user) {
?>
<div id="theme-inspector"></div>
<div id="working-cover" title="<?= t('Doubleclick to remove this cover if needed') ?>">
  <div class="working-cover-inner">
    <img class="spinner" src="<?= config('publicbase') ?>/css/img/spinner.gif">
    <div class="please-wait"><?= t('please wait...') ?></div>
  </div>
</div>
<div id="dialog-cover">
  <div id="dialog-container">
    <div id="dialog-message"></div>
    <div id="dialog-input-container"></div>
    <div id="dialog-buttons">
      <button class="button" id="dialog-confirm-button"><?= t("ok") ?></button>
      <button class="button" id="dialog-cancel-button"><?= t("cancel") ?></button>
      </div>
  </div>
</div>
<?php
    print $menu;
  }
?>
<div id="main" class="<?= $main_classes ?>">
  <h1><?= $h1_tag ?></h1>
  <?= $messages ?>
<?php

  if($valid_user) {
    ?><div id="static-js-messages"></div><div id="temp-js-messages"></div><?php
  }

  print $main_content;

  if($valid_user) {
    $microtime_stop = microtime(true);
    $microtime_duration = round($microtime_stop - $microtime_start, 3);
    $date = date("c");
    $duration = t('Page generated in %s seconds @ %s', $microtime_duration, $date);
?>
  <div class="timing"><?= $duration ?></div>
<?php
  }

?>
</div><!-- #main -->
</body>
</html>