<?php

  namespace App\Utils;
  
  use App\Records\Record;
    
  class Gallery {
    const BASE_FOLDER = 'files/gallery';
    const VALID_EXT = ['jpeg', 'jpg', 'png', 'gif'];
    
    private $radix;
    
    static function test() {
      $gallery = new Gallery('test');
      return $gallery->renderEdit();
    }
    
    function __construct($radix) {
      ensureFolder(self::BASE_FOLDER);
      $this->radix = sanitizeFilename($radix);
    }
    
    private function renderSavedSection($context) {
      ob_start();
?>
  <div class="box image-section" id="gallery-saved-section">
<?php if($context === 'edit') { ?>
    <h2><?= t('Saved images') ?></h2>
    <div>
      <button class="button" id="gallery-delete-saved-button"><?= t('Delete all saved images') ?></button>
    </div>
<?php } ?>
  </div>
<?php
      return ob_get_clean();
    }
    
    private function renderDropSection() {
      ob_start();
?>
  <div class="box" id="gallery-drop-section">
    <p><?= t('Drop images here or click here to select them') ?></p>
  </div>
<?php
      return ob_get_clean();
    }
    
    private function renderPendingSection() {
      ob_start();
?>    
  <div class="box image-section" id="gallery-pending-section">
    <h2><?= t('Pending images') ?></h2>
    <div>
      <button class="button" id="gallery-save-button"><?= t('Save these images') ?></button>
      <button class="button" id="gallery-delete-pending-button"><?= t('Delete all pending images') ?></button>
    </div>
  </div>
<?php
      return ob_get_clean();
    }
    
    private function renderStreamSection() {
      ob_start();
?>

  <div class="box" id="gallery-streaming-section">    
    <button class="button" id="gallery-start-button"><?= t('Start stream') ?></button>
    <div class="box streaming-interface" style="display: none;">
      <div class="box figure-container">
        <figure class="box video">
          <figcaption><?= t('Live camera stream') ?></figcaption>
          <video id="gallery-video"></video>
          <button class="button" id="gallery-stop-button"><?= t('Stop stream') ?></button>
        </figure>
      </div>
      <div class="box figure-container">
        <figure class="box picture">
          <figcaption><?= t('Captured picture') ?></figcaption>
          <img id="gallery-picture">
          <button class="button" id="gallery-capture-button"><?= t('Capture') ?></button>
        </figure>
      </div>
    </div>
    <canvas id="gallery-canvas"></canvas>
  </div>    
<?php
      return ob_get_clean();
    }
    
    private function renderDataSection($context) {
      ob_start();
      if($context === 'edit') {
?>
<script src='/js/lib/filedrop-min.js'></script>
<?php
      }
      $json_items = htmlspecialchars(json_encode($this->getGalleryItems()));
?>
<div data-radix="<?= $this->radix ?>" 
     data-context="<?= $context ?>"
     data-items="<?= $json_items ?>"
     id="gallery-data"></div>
<?php
      return ob_get_clean();
    }
    
    function renderEdit() {
      ob_start();
?>
<div class="gallery box edit"
     data-radix="<?= $this->radix ?>"
     id="gallery-container">
    <?= $this->renderDataSection('edit') ?>
    <?= $this->renderSavedSection('edit') ?>
    <?= $this->renderDropSection() ?>
    <?= $this->renderStreamSection() ?>
    <?= $this->renderPendingSection() ?>
</div>
<?php
      return ob_get_clean();
    }
    
    function renderDisplay() {
      ob_start();
?>
<div class="gallery box display" id="gallery-container">
    <?= $this->renderDataSection('display') ?>
    <?= $this->renderSavedSection('display') ?>
</div>
<?php
      return ob_get_clean();
    }
    
    function getBasename() {
      return self::BASE_FOLDER . '/' . $this->radix . '_img_';
    }
    
    static function padNumber($number) {
      return str_pad($number, 5, '0', STR_PAD_LEFT);
    }
    
    function buildPath($index, $ext) {
      $basename = $this->getBasename();
      return $basename . self::padNumber($index) . '.' . $ext;
    }
    
    function parsePath($path) {
      $basename = $this->getBasename();
      if(strpos($path, $basename) !== 0) {
        Msg::error(t('Impossible! %s does not contain %s!', $path, $basename));
        return false;
      }
      $ending = str_replace($basename, '', $path);
      if(!$ending) {
        Msg::error(t('Bad filename %s!', $path));
        Msg::error(t('Missing ending part'));
        return false;
      }
      $index = intval($ending, 10);
      $parts = explode('.', $ending);
      if(count($parts) !== 2) {
        Msg::error(t('Bad filename %s!', $path));
        Msg::error(t('Multiple extensions'));
        return false;
      }
      $ext = $parts[1];
      if(!self::supportedExtensions($ext)) {
        Msg::error(t('Bad filename %s!', $path));
        Msg::error(t('Unsupported extension'));
        return false;
      }
      $item = (object)[
        'radix' => $this->radix,
        'path' => $path,
        'src' => '/' . $path,
        'index' => $index,
        'ext' => $ext,
      ];
      return $item;
    }
    
    static function supportedExtensions($ext) {
      return in_array(mb_strtolower($ext), self::VALID_EXT);
    }
    
    private function getNewGalleryItem($ext) {
      $basename = $this->getBasename();
      $files = glob($basename . '*');
      $index = 0;
      foreach($files as $file) {
        $ending = str_replace($basename, '', $file);
        $index = max($index, intval($ending, 10));
      }
      ++$index;
      $path = $this->buildPath($index, $ext); 
      $item = (object)[
        'radix' => $this->radix,
        'path' => $path,
        'src' => '/' . $path,
        'index' => $index,
        'ext' => $ext,
      ];
      return $item;
    }
    
    function saveImage($base64data) {
      $matches = [];
      $valid_ext = implode('|', self::VALID_EXT);
      if(!preg_match('#^data:image/('.$valid_ext.');base64,(.*)$#', $base64data, $matches)) {
        Msg::error(t('Bad image data - format extraction failed'));
        return false;
      }
      $strict = true;
      $data = base64_decode($matches[2], $strict);
      if($data === false) {
        Msg::error(t('Bad image data - data extraction failed'));
        return false;
      }
      $ext = $matches[1];
      if($ext === 'jpeg') {
        $ext = 'jpg';
      }
      $item = $this->getNewGalleryItem($ext);
      if(file_put_contents($item->path, $data)) {
        return $item;
      }      
      return false;
    }
    
    function deleteImage($index, $ext) {
      $path = $this->buildPath($index, $ext);
      if(!file_exists($path)) {
        Msg::error(t('Image %d of item %s does not exist'));
        return false;
      }
      if(!unlink($path)) {
        Msg::error(t('Unable to delete image %d of item %s'));
        return false;
      }
      return true;
    }
    
    function getGalleryItems() {
      $basename = self::BASE_FOLDER . '/' . $this->radix . '_img_';
      $files = glob($basename . '*');
      $items = [];
      foreach($files as $file) {
        $item = $this->parsePath($file);
        if($item) {
          $items[] = $item;
        }
      }
      return $items;
    }
    
    function renderImages($radix_replacer = false) {
      $items = $this->getGalleryItems();
      if(!count($items)) {
        return;
      }
      $output = '';
      foreach($items as $item) {
        if($radix_replacer) {
          $caption = $radix_replacer . ' [' . $item->index . ']';
        } else {
          $caption = $item->radix . ' [' . $item->index . ']';
        }
        $output .= <<<HTML
  <figure class="gallery-item">
     <figcaption>$caption</figcaption>
     <img src="{$item->src}">
  </figure>
HTML;
      }
      return $output;
    }
    
  }
  