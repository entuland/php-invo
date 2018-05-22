<?php

  define('CODE128A', [ 0 => ' ', 1 => '!', 2 => '"', 3 => '#', 4 => '$', 5 => '%', 6 => '&', 7 => '\'', 8 => '(', 9 => ')', 10 => '*', 11 => '+', 12 => ',', 13 => '-', 14 => '.', 15 => '/', 16 => '0', 17 => '1', 18 => '2', 19 => '3', 20 => '4', 21 => '5', 22 => '6', 23 => '7', 24 => '8', 25 => '9', 26 => ':', 27 => ';', 28 => '<', 29 => '=', 30 => '>', 31 => '?', 32 => '@', 33 => 'A', 34 => 'B', 35 => 'C', 36 => 'D', 37 => 'E', 38 => 'F', 39 => 'G', 40 => 'H', 41 => 'I', 42 => 'J', 43 => 'K', 44 => 'L', 45 => 'M', 46 => 'N', 47 => 'O', 48 => 'P', 49 => 'Q', 50 => 'R', 51 => 'S', 52 => 'T', 53 => 'U', 54 => 'V', 55 => 'W', 56 => 'X', 57 => 'Y', 58 => 'Z', 59 => '[', 60 => '\\', 61 => ']', 62 => '^', 63 => '_', 64 => 'NUL', 65 => 'SOH', 66 => 'STX', 67 => 'ETX', 68 => 'EOT', 69 => 'ENQ', 70 => 'ACK', 71 => 'BEL', 72 => 'BS', 73 => 'HT', 74 => 'LF', 75 => 'VT', 76 => 'FF', 77 => 'CR', 78 => 'SO', 79 => 'SI', 80 => 'DLE', 81 => 'DC1', 82 => 'DC2', 83 => 'DC3', 84 => 'DC4', 85 => 'NAK', 86 => 'SYN', 87 => 'ETB', 88 => 'CAN', 89 => 'EM', 90 => 'SUB', 91 => 'ESC', 92 => 'FS', 93 => 'GS', 94 => 'RS', 95 => 'US', 96 => 'FNC 3', 97 => 'FNC 2', 98 => 'SHIFT', 99 => 'CODE C', 100 => 'CODE B', 101 => 'FNC 4', 102 => 'FNC 1', 103 => 'Start A', 104 => 'Start B', 105 => 'Start C', 106 => 'Stop', ]);
  
  define('CODE128B', [ 0 => ' ', 1 => '!', 2 => '"', 3 => '#', 4 => '$', 5 => '%', 6 => '&', 7 => '\'', 8 => '(', 9 => ')', 10 => '*', 11 => '+', 12 => ',', 13 => '-', 14 => '.', 15 => '/', 16 => '0', 17 => '1', 18 => '2', 19 => '3', 20 => '4', 21 => '5', 22 => '6', 23 => '7', 24 => '8', 25 => '9', 26 => ':', 27 => ';', 28 => '<', 29 => '=', 30 => '>', 31 => '?', 32 => '@', 33 => 'A', 34 => 'B', 35 => 'C', 36 => 'D', 37 => 'E', 38 => 'F', 39 => 'G', 40 => 'H', 41 => 'I', 42 => 'J', 43 => 'K', 44 => 'L', 45 => 'M', 46 => 'N', 47 => 'O', 48 => 'P', 49 => 'Q', 50 => 'R', 51 => 'S', 52 => 'T', 53 => 'U', 54 => 'V', 55 => 'W', 56 => 'X', 57 => 'Y', 58 => 'Z', 59 => '[', 60 => '\\', 61 => ']', 62 => '^', 63 => '_', 64 => '`', 65 => 'a', 66 => 'b', 67 => 'c', 68 => 'd', 69 => 'e', 70 => 'f', 71 => 'g', 72 => 'h', 73 => 'i', 74 => 'j', 75 => 'k', 76 => 'l', 77 => 'm', 78 => 'n', 79 => 'o', 80 => 'p', 81 => 'q', 82 => 'r', 83 => 's', 84 => 't', 85 => 'u', 86 => 'v', 87 => 'w', 88 => 'x', 89 => 'y', 90 => 'z', 91 => '{', 92 => '|', 93 => '}', 94 => '~', 95 => 'DEL', 96 => 'FNC 3', 97 => 'FNC 2', 98 => 'SHIFT', 99 => 'CODE C', 100 => 'FNC 4', 101 => 'CODE A', 102 => 'FNC 1', 103 => 'Start A', 104 => 'Start B', 105 => 'Start C', 106 => 'Stop', ]);
  
  define('CODE128C', [ 0 => '00', 1 => '01', 2 => '02', 3 => '03', 4 => '04', 5 => '05', 6 => '06', 7 => '07', 8 => '08', 9 => '09', 10 => '10', 11 => '11', 12 => '12', 13 => '13', 14 => '14', 15 => '15', 16 => '16', 17 => '17', 18 => '18', 19 => '19', 20 => '20', 21 => '21', 22 => '22', 23 => '23', 24 => '24', 25 => '25', 26 => '26', 27 => '27', 28 => '28', 29 => '29', 30 => '30', 31 => '31', 32 => '32', 33 => '33', 34 => '34', 35 => '35', 36 => '36', 37 => '37', 38 => '38', 39 => '39', 40 => '40', 41 => '41', 42 => '42', 43 => '43', 44 => '44', 45 => '45', 46 => '46', 47 => '47', 48 => '48', 49 => '49', 50 => '50', 51 => '51', 52 => '52', 53 => '53', 54 => '54', 55 => '55', 56 => '56', 57 => '57', 58 => '58', 59 => '59', 60 => '60', 61 => '61', 62 => '62', 63 => '63', 64 => '64', 65 => '65', 66 => '66', 67 => '67', 68 => '68', 69 => '69', 70 => '70', 71 => '71', 72 => '72', 73 => '73', 74 => '74', 75 => '75', 76 => '76', 77 => '77', 78 => '78', 79 => '79', 80 => '80', 81 => '81', 82 => '82', 83 => '83', 84 => '84', 85 => '85', 86 => '86', 87 => '87', 88 => '88', 89 => '89', 90 => '90', 91 => '91', 92 => '92', 93 => '93', 94 => '94', 95 => '95', 96 => '96', 97 => '97', 98 => '98', 99 => '99', 100 => 'CODE B', 101 => 'CODE A', 102 => 'FNC 1', 103 => 'Start A', 104 => 'Start B', 105 => 'Start C', 106 => 'Stop', ]);
  
  define('CODE128BARS', [ 0 => '212222', 1 => '222122', 2 => '222221', 3 => '121223', 4 => '121322', 5 => '131222', 6 => '122213', 7 => '122312', 8 => '132212', 9 => '221213', 10 => '221312', 11 => '231212', 12 => '112232', 13 => '122132', 14 => '122231', 15 => '113222', 16 => '123122', 17 => '123221', 18 => '223211', 19 => '221132', 20 => '221231', 21 => '213212', 22 => '223112', 23 => '312131', 24 => '311222', 25 => '321122', 26 => '321221', 27 => '312212', 28 => '322112', 29 => '322211', 30 => '212123', 31 => '212321', 32 => '232121', 33 => '111323', 34 => '131123', 35 => '131321', 36 => '112313', 37 => '132113', 38 => '132311', 39 => '211313', 40 => '231113', 41 => '231311', 42 => '112133', 43 => '112331', 44 => '132131', 45 => '113123', 46 => '113321', 47 => '133121', 48 => '313121', 49 => '211331', 50 => '231131', 51 => '213113', 52 => '213311', 53 => '213131', 54 => '311123', 55 => '311321', 56 => '331121', 57 => '312113', 58 => '312311', 59 => '332111', 60 => '314111', 61 => '221411', 62 => '431111', 63 => '111224', 64 => '111422', 65 => '121124', 66 => '121421', 67 => '141122', 68 => '141221', 69 => '112214', 70 => '112412', 71 => '122114', 72 => '122411', 73 => '142112', 74 => '142211', 75 => '241211', 76 => '221114', 77 => '413111', 78 => '241112', 79 => '134111', 80 => '111242', 81 => '121142', 82 => '121241', 83 => '114212', 84 => '124112', 85 => '124211', 86 => '411212', 87 => '421112', 88 => '421211', 89 => '212141', 90 => '214121', 91 => '412121', 92 => '111143', 93 => '111341', 94 => '131141', 95 => '114113', 96 => '114311', 97 => '411113', 98 => '411311', 99 => '113141', 100 => '114131', 101 => '311141', 102 => '411131', 103 => '211412', 104 => '211214', 105 => '211232', 106 => '2331112', ]);