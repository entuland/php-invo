<?php
  session_start();
  $print_id = filter_input(INPUT_GET, 'print-cache-id', FILTER_VALIDATE_INT);
  if(isset($_SESSION['print-cache'][$print_id])) {
    echo $_SESSION['print-cache'][$print_id];
  }