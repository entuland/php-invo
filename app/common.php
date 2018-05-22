<?php
  
  use App\Router;
  use App\Utils\Msg;
  
  class Debug {
    static $flags = 0;
    const POST = 1;
    const FIND = 2;
    const QUERY = 4;
    
    static function enable($flag) {
      self::$flags |= $flag;
    }
    
    static function disable($flag) {
      self::$flags &= ~$flag;
    }
    
    static function track($flag) {
      return self::$flags & $flag;
    }
    
    static function maybe($flag, $callback) {
      if(self::track($flag)) {
        $callback();
      }
    }
  }
  
  function safeMarkup($text) {
    $double_encode = false;
    return htmlspecialchars($text, ENT_COMPAT | ENT_HTML5, 'UTF-8', $double_encode);
  }
  
  function escapeHtmlAttribute($text) {
    return safeMarkup($text);
  }
  
  function var_export54($var, $indent = "") {
    // stolen from http://stackoverflow.com/a/24316675
    switch (gettype($var)) {
        case "string":
            return "'" . addcslashes($var, "\\\$'\r\n\t\v\f") . "'";
        case "array":
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];
            foreach ($var as $key => $value) {
                $r[] = "$indent  "
                     . ($indexed ? "" : var_export54($key) . " => ")
                     . var_export54($value, "$indent  ");
            }
            return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
        case "boolean":
            return $var ? "TRUE" : "FALSE";
        default:
            return var_export($var, TRUE);
    }
  }
  
  function var_export_separated($root, $level, $array, $prev_root = '') {
    $output = '';
    $indent = str_repeat('  ', $level) . str_repeat(' ', strlen($root)); 
    if($level <= 0 || !is_array($array)) {
      $output .= $prev_root . ' = ' . var_export54($array, $indent) . ";\n\n";
    } else {
      --$level;
      foreach($array as $key => $value) {
        $new_root = $prev_root . $root . '[' . var_export($key, true) . ']';
        $output .= var_export_separated('', $level, $value, $new_root);
      }
      $output .= "\n";
    }
    return $output;
  }
  
  function unused(...$params) {}
  
  function array_insert(&$array, $insert, $position) {
    // http://blog.leenix.co.uk/2010/03/php-insert-into-middle-of-array.html
    if($position === 0) {
      $array = array_merge($insert, $array);
    } 
    else if($position >= count($array) - 1) {
      $array = array_merge($array, $insert);
    } 
    else {
      $head = array_slice($array, 0, $position);
      $tail = array_slice($array, $position);
      $array = array_merge($head, $insert, $tail);
    }
  }
  
  function normalizePath($path) {
    $path = str_replace('\\', '/', $path);
    return preg_replace('#/+#', '/', $path);
  }
  
  function ensureFolder($path) {
    if(!is_dir($path)) {
      if(!mkdir($path, 0644, true)) {
        throw new \Exception(t('Unable to create folder %s', $path));
      }
    }
  }

  if (!function_exists('glob_recursive')) {
      // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0) {
      $files = glob($pattern, $flags);
      foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
          $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
      }
      return $files;
    }
  }
  
  function getSubfolders($path) {
    return glob($path . '/*', GLOB_ONLYDIR);
  }
  
  function rrmdir($dir) {
    // http://nl3.php.net/manual/en/function.rmdir.php#98622
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (filetype($dir."/".$object) == "dir") 
             rrmdir($dir."/".$object); 
          else unlink   ($dir."/".$object);
        }
      }
      reset($objects);
      rmdir($dir);
    }
  }
  
  function sanitizeFilename($filename) {
    static $subject = 'Any-Latin; Latin-ASCII; Lower()';
    $base = basename($filename);
    $trans = transliterator_transliterate($subject, $base);
    return preg_replace('#[^\w\d\.-]+#', '_', $trans);
  }
  
  function uniformPath($path) {
    return mb_strtolower(unixStylePath(realpath($path)));
  }
  
  function maybeInFolder($folder, $subfolder) {
    $uniform_folder = uniformPath($folder);
    $uniform_subfolder = uniformPath($subfolder);
    return strpos($uniform_subfolder, $uniform_folder) === 0;
  }
  
  function unixStylePath($path) {
    return str_replace('\\', '/', $path);
  }
  
  function windowsStylePath($path) {
    return str_replace('/', '\\', $path);
  }
  
  function requireCompact($filename) {
    ob_start();
    require $filename;
    return compactString(ob_get_clean()); 
  }
  
  function compactString($string) {
    return preg_replace('# +#', ' ', $string);
  }
  
  function compress($filename) {
    if(!file_exists($filename)) {
      Msg::error(t('File %s does not exist', $filename));
      return false;
    } 
    $compressed = gzCompressFile($filename);
    if($compressed === false) {
      Msg::error(t('Compression of %s failed', $filename));
      return false;
    }
    Msg::msg(t('File compressed to %s', $compressed));
    sleep(1);
    return $compressed;
  }
  
  function decompress($compressed) {
    if(!file_exists($compressed)) {
      Msg::error(t('File %s does not exist', $compressed));
      return false;
    } 
    $filename = gzDecompressFile($compressed);
    if($filename === false) {
      Msg::error(t('Decompression of %s failed', $compressed));
      return false;
    }
    Msg::msg(t('File decompressed to %s', $filename));
    sleep(1);
    return $filename;
  }
  
  function setTitle($new_title_tag, $new_h1_tag = false) {
    global $title_tag;
    global $h1_tag;
    $h1_tag = $new_h1_tag ? $new_h1_tag : $new_title_tag;
    $title_tag = $new_title_tag;
  }
  
  function setMainClasses($classes, $append = false) {
    global $main_classes;
    if($append) {
      $main_classes .= ' ' . $classes;
    } else {
      $main_classes = $classes;
    }
  }
  
  function appendMainClasses($classes) {
    setMainClasses($classes, true);
  }

  function cacheBuster() {
    if((new \App\Utils\Settings())->disableResourcesCaching) {
      return '';
    }
    return '?' . rand();
  }
  
  function enabledFunction($function_name) {
    return 
      function_exists($function_name) 
      && !in_array($function_name, array_map('trim', explode(', ', ini_get('disable_functions')))) 
      && mb_strtolower(ini_get('safe_mode')) != 1;
  }
  
  function doubleSubmissionCheck($redirection = '') {
    $uid = getPOST('submission_id');
    if(!$uid) {
      Msg::error(t('Missing UID from form submission'));
      Router::redirect($redirection);
    }
    if(isset($_SESSION['submission_ids'][$uid])) {
      Msg::error(t('This form has already been submitted'));
      Router::redirect($redirection);
    }
    $_SESSION['submission_ids'][$uid] = true;
  }
  
  function getPOST($key, $filter = FILTER_DEFAULT, $filter_options = null) {
    if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
      $value = filter_input(INPUT_POST, $key, $filter, $filter_options);
      return $value;
    }
    if(isset($_SESSION['post'][$key])) {
      $value = filter_var($_SESSION['post'][$key], $filter, $filter_options);
      return $value;
    }
    return null;
  }
  
  function getGET($key, $filter = FILTER_DEFAULT, $filter_options = null) {
    return filter_input(INPUT_GET, $key, $filter, $filter_options);
  }
  
  function request_path() {
    static $path = null;

    if (!is_null($path)) {
      return $path;
    }

    if (isset($_GET['q']) && is_string($_GET['q'])) {
      // This is a request with a ?q=foo/bar query string. $_GET['q'] is
      // overwritten in drupal_path_initialize(), but request_path() is called
      // very early in the bootstrap process, so the original value is saved in
      // $path and returned in later calls.
      $path = $_GET['q'];
    }
    elseif (isset($_SERVER['REQUEST_URI'])) {
      // This request is either a clean URL, or 'index.php', or nonsense.
      // Extract the path from REQUEST_URI.
      $request_path = strtok($_SERVER['REQUEST_URI'], '?');
      $base_path_len = strlen(rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/'));
      // Unescape and strip $base_path prefix, leaving q without a leading slash.
      $path = substr(urldecode($request_path), $base_path_len + 1);
      // If the path equals the script filename, either because 'index.php' was
      // explicitly provided in the URL, or because the server added it to
      // $_SERVER['REQUEST_URI'] even when it wasn't provided in the URL (some
      // versions of Microsoft IIS do this), the front page should be served.
      if ($path == basename($_SERVER['PHP_SELF'])) {
        $path = '';
      }
    }
    else {
      // This is the front page.
      $path = '';
    }

    // Under certain conditions Apache's RewriteRule directive prepends the value
    // assigned to $_GET['q'] with a slash. Moreover we can always have a trailing
    // slash in place, hence we need to normalize $_GET['q'].
    $path = trim($path, '/');

    return $path;
  }

  /**
   * GZIPs a file on disk (appending .gz to the name)
   *
   * From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
   * Based on function by Kioob at:
   * http://www.php.net/manual/en/function.gzwrite.php#34955
   * 
   * @param string $source Path to file that should be compressed
   * @param integer $level GZIP compression level (default: 9)
   * @return string New filename (with .gz appended) if success, or false if operation fails
   */
  function gzCompressFile($source, $level = 9){ 
    $dest = $source . '.gz'; 
    $mode = 'wb' . $level; 
    $error = false; 
    if ($fp_out = gzopen($dest, $mode)) { 
        if ($fp_in = fopen($source,'rb')) { 
            while (!feof($fp_in)) 
                gzwrite($fp_out, fread($fp_in, 1024 * 512)); 
            fclose($fp_in); 
        }
        else {
            $error = true; 
        }
        gzclose($fp_out); 
    }
    else {
        $error = true; 
    }
    if ($error)
        return false; 
    else
        return $dest; 
  } 

  function gzDecompressFile($source){ 
    $dest = preg_replace('#\.gz$#', '', $source);
    if($dest === $source) {
      return false;
    }
    if(file_exists($dest)) {
      return false;
    }
    $error = false; 
    if ($fp_out = fopen($dest, 'wb')) { 
        if ($fp_in = gzopen($source,'rb')) { 
            while (!gzeof($fp_in)) 
                fwrite($fp_out, gzread($fp_in, 1024 * 512)); 
            gzclose($fp_in); 
        }
        else {
            $error = true; 
        }
        fclose($fp_out); 
    }
    else {
        $error = true; 
    }
    if ($error)
        return false; 
    else
        return $dest; 
  }
  
  function t($msgid, ...$params) {
    if(config('localecode') == "en") {
      return sprintf($msgid, ...$params);
    }
    return sprintf(gettext($msgid), ...$params);
  }
  
  function nt($singular, $plural, $n, ...$params) {
    if(config('localecode') == "en") {
      if($n == 1) {
        return sprintf($singular, ...$params);
      }
      return sprintf($plural, ...$params);
    }
    return sprintf(ngettext($singular, $plural, $n), ...$params);
  }

  function ct($context, $msgid, ...$params) {
    if(config('localecode') == "en") {
      return sprintf($msgid, ...$params);
    }
    return sprintf(pgettext($context, $msgid), ...$params);
  }
  
  if(!function_exists('pgettext')) {
    //http://stackoverflow.com/questions/31994975/gettext-to-work-with-context-pgettext
    function pgettext($context, $msgid) {
      $contextString = "{$context}\004{$msgid}"; 
      $translation = gettext($contextString);
      if($translation == $contextString) {
        return $msgid;
      }
      return $translation;
    }
  }

