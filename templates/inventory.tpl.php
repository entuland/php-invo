<!doctype html>
<html>
  <head>
    <title><?= $title ?></title>
    <link rel='stylesheet' href='<?= config('publicbase') ?>/css/print.css?<?= rand() ?>'>
  </head>
  <body>
    <h1><?= $title ?></h1>
    <table>
      <thead>
        <?= $headers_row ?>
      </thead>
      <tbody>
        <?= implode($table_rows) ?>
      </tbody>
    </table>
  </body>
</html>
