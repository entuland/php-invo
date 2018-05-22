<!DOCTYPE html>
<html>
  <head>
    <meta http_equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?= config('sitename') ?> | <?= $title_tag ?></title>
    <link rel="stylesheet" href="<?= config('publicbase') ?>/css/print.css?<?= rand() ?>">
  </head>
<body class="<?= $text_context ?>">
<div id="page">
  <div id="page-editable" contenteditable="true"><?= $content ?></div>
  <div id="buttons" class="noprint">
    <button onclick="window.print()"><?= t('Print') ?></button><br>
    <button onclick="selectAndCopy()"><?= t('Copy all') ?></button>
    <script>
      function selectAndCopy() {
        var editable = document.getElementById("page-editable");
        var mirror = document.getElementById("mirror");
        mirror.innerHTML = editable.innerHTML;
        mirror.style.display = 'block';
        var range = document.createRange();
        range.selectNodeContents(mirror);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        document.execCommand('copy');
        mirror.style.display = 'none';
        mirror.innerHTML = '';
        alert(<?= json_encode(t('copied to clipboard')) ?>);
      }
    </script>
  </div>
</div>
<div id="mirror" style="display: none"></div>
</body>
</html>