<?php

include_once './config-error.php';
require_once './filesystem.php';

function clearCache() {
  deletePath($_SERVER['DOCUMENT_ROOT'] . '/cache');
  echo 'clear';
}
clearCache();

