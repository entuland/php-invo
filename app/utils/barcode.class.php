<?php
  declare(strict_types = 1);
  
  namespace App\Utils;
  
  class Barcode {
    private $source = '';
    private $image = null;
    
    public $padding = 5;
    public $font_file = '';
    public $font_angle = 0;
    public $font_size = 16;
    
    public $bar_size = 3;
    public $bar_height = 60;
    
    public $outline_text = false;
    
    private $onlyA = [];
    private $onlyB = [];
    private $A = [];
    private $B = [];
    private $C = [];
    
    private $raw = false;
    
    private $log = [];
    private $details = [];
    
    function __construct(string $source = null) {
      $this->A = array_flip(CODE128A);
      $this->B = array_flip(CODE128B);
      $this->C = array_flip(CODE128C);
      $this->onlyA = array_flip(array_diff(CODE128A, CODE128B));
      $this->onlyB = array_flip(array_diff(CODE128B, CODE128A));
      $this->source = $source;
      $this->font_file = config('fontfile');
    }
    
    static function main() {
      $inst = new self("1234567890123");
      $output = self::imageTag($inst->encode());
      Format::dump($inst->details);
      return $output;
    }
    
    private function log($msg) {
      $this->log[] = [
        'step' => $msg,
        'incoming' => $this->incoming,
      ];
    }
    
    private function detail($chartext) {
      $this->details[] = "[$chartext] {$this->code}";
    }
                
    private function need(string $string): string {
      foreach(str_split($string) as $char) {
        $chartext = ASCII[ord($char)];
        if(array_key_exists($chartext, $this->onlyA)) {
          return 'A';
        }
        if(array_key_exists($chartext, $this->onlyB)) {
          return 'B';
        }
      }
      return '';
    }
    
    private function start($code) {
      $this->log("starting with $code");
      $chartext = 'Start '.$code;
      $startKey = $this->A[$chartext];
      $this->bars = CODE128BARS[$startKey];
      $this->checksum = $startKey;
      $this->code = $code;
      $this->detail($chartext);
    }
        
    private function switchTo($code) {
      if($code === $this->code) {
        return;
      }
      $this->log("switching to $code");
      $chartext = "CODE $code";
      $key = $this->{$this->code}[$chartext];
      $this->bars .= CODE128BARS[$key];
      $this->checksum += $key * $this->index;
      $this->index++;
      $this->code = $code;
      $this->detail($chartext);
    }
    
    private function encodeSection($code) {
      $this->log("encoding section with $code");
      
      $length = 1;
      if($code === 'C') {
        $length = 2;
      }
      $firstLoopDone = false;
      foreach(str_split($this->incoming, $length) as $chunk) {
        if($code === 'C') {
          $chartext = $chunk;
        }
        else if($firstLoopDone) {
          return;
        }
        else {
          $chartext = ASCII[ord($chunk)];
        }
        $key = $this->{$code}[$chartext] ?? false;
        if($key === false) {
          return;
        }
        $this->bars .= CODE128BARS[$key];
        $this->checksum += $key * $this->index;
        $this->incoming = substr($this->incoming, $length);
        $this->index++;
        $this->detail($chartext);
        $firstLoopDone = true;
      }
    }
        
    private function lookAhead() {
      $matches = [];
      if(preg_match("#^\D+#", $this->incoming, $matches)) {
        return $this->need($matches[0]);
      }
      return '';
    }
    
    private function numericStart() {
      return preg_match("#^(\d\d){2,}#", $this->incoming);
    }
    
    private function numericMiddle() {
      return 
        preg_match("#^(\d\d){3,}\D#", $this->incoming)
        || preg_match("#^(\d\d){2,}$#", $this->incoming);
    }
    
    public function imageSrc(): string {
      return $this->encode();
    }
    
    private function encode(): string  {
      return $this->error(t('Empty barcode source'));
      $this->incoming = $this->source;
      
      $this->log("encoding start");
      
      $this->index = 1;
      
      $chars = str_split($this->incoming);

      if(!count($chars)) {
        return $this->error(t('Empty barcode source'));
      }
      
      foreach($chars as $index => $ch) {
        if(!array_key_exists(ord($ch), ASCII)) {
          return $this->error(t('Invalid barcode character') . ' ' . $index);
        }
      }
      
      $code = 'B';
      if($this->numericStart()) {
        $code = 'C';
      }
      else if($this->lookAhead() === 'A') {
        $code = 'A';
      }
      $this->start($code);
      $this->encodeSection($code);
      
      while(strlen($this->incoming)) {
        $code = 'B';
        if($this->numericMiddle()) {
          $code = 'C';
        }
        else if($this->lookAhead() === 'A') {
          $code = 'A';
        }
        $this->switchTo($code);
        $this->encodeSection($code);        
      }
      
      $check = $this->checksum % 103;
      $this->bars .= CODE128BARS[$check];
      $this->bars .= CODE128BARS[$this->A['Stop']];
      
      $this->detail($check);
      $this->detail('Stop');

      return $this->renderBarcode();
    }
    
    private function renderBarcode(): string  {
      $bars = str_split($this->bars);
      $size = array_sum($bars);
      $quiet = $this->bar_size * 20;
      $width = $size * $this->bar_size + 2 * $quiet;
      $this->createImage($width, $this->bar_height, 0);
      $start_x = $quiet;
      foreach($bars as $index => $bar) {
        $color = $this->black;
        if($index % 2) {
          $color = $this->white;
        }
        $x1 = $start_x;
        $x2 = $start_x + $bar * $this->bar_size;
        $y1 = 0;
        $y2 = $this->bar_height;
        imagefilledrectangle($this->image, $x1, $y1, $x2, $y2, $color);
        $start_x = $x2;
      }
      return $this->renderedImgSrc();
    }
    
    static function imageTag($imgSrc) {
      return '<img src="' . $imgSrc . '">';
    }
    
    private function error($message): string {
      $bbox = self::getBBobject($message);
      $this->createImage($bbox->width, $bbox->height);
      $this->renderString($message, $this->padding, $this->padding);
      return $this->renderedImgSrc();
    }
    
    private function renderedImgSrc(): string {
      ob_start();
      imagepng($this->image);
      $image_data = ob_get_clean();
      if($this->raw) {
        return $image_data;
      }
      $base64_data = base64_encode($image_data);
      return 'data:image/png;base64,'.$base64_data;
    }
    
    private function renderString(string $string, int $left, int $top) {
      $bbox = self::getBBobject($string);
      imagettftext(
        $this->image, 
        $this->font_size,
        $this->font_angle, 
        $left - $bbox->left,
        $top - $bbox->top,
        $this->black, 
        $this->font_file, 
        $string
      );
      if($this->outline_text) {
        imagerectangle($this->image, $left, $top, $left + $bbox->width, $top + $bbox->height, $this->red);
      }
    }
    
    private function createImage(int $width, int $height, int $padding = null) {
      if($this->image) {
        imagedestroy($this->image);
        $this->image = null;
      }
      $p = $padding ?? $this->padding;
      $this->width = $width + $p * 2;
      $this->height = $height + $p * 2;
      $this->image = imagecreatetruecolor($this->width, $this->height);
      $this->white = imagecolorallocate($this->image, 255, 255, 255);
      $this->black = imagecolorallocate($this->image, 0, 0, 0);
      $this->red = imagecolorallocate($this->image, 255, 0, 0);
      $this->green = imagecolorallocate($this->image, 0, 255, 0);
      $this->blue = imagecolorallocate($this->image, 0, 0, 255);
      $this->yellow = imagecolorallocate($this->image, 255, 255, 0);
      $this->cyan = imagecolorallocate($this->image, 0, 255, 255);
      $this->magenta = imagecolorallocate($this->image, 255, 0, 255);
      imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $this->white);
    }
    
    private function getBBobject($text): \stdClass {
      $bbox = self::imagettfbboxFixed($this->font_size, $this->font_angle, $this->font_file, $text);
      $x_array = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
      $y_array = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];
      $min_y = min($y_array);
      $max_x = max($x_array);
      $max_y = max($y_array);
      $min_x = min($x_array);
      $width = $max_x - $min_x;
      $height = $max_y - $min_y;
      return (object) [
        'left' => intval($min_x),
        'top' => intval($min_y),
        'width' => intval($width),
        'height' => intval($height),
        'rect' => $bbox,
      ];
    }
    
    private static function imagettfbboxFixed($size, $angle, $fontfile, $text){
      // https://ruquay.com/sandbox/imagettf/
      // compute size with a zero angle
      $coords = imagettfbbox($size, 0, $fontfile, $text);
      
      if(!$angle) {
        return $coords;
      }
      
      // convert angle to radians
      $a = deg2rad($angle);
      // compute some usefull values
      $ca = cos($a);
      $sa = sin($a);
      $ret = array();
      // perform transformations
      for($i = 0; $i < 7; $i += 2){
          $ret[$i] = intval(round($coords[$i] * $ca + $coords[$i+1] * $sa));
          $ret[$i+1] = intval(round($coords[$i+1] * $ca - $coords[$i] * $sa));
      }
      return $ret;
    }
    
  }
  