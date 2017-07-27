<?php

namespace skylee;

include_once './config-error.php';
require_once './FileSystem.php';

function clearCache() {
  FileSystem::deletePath($_SERVER['DOCUMENT_ROOT'] . '/cache');
  echo 'clear';
}
clearCache();
