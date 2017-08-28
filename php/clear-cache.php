<?php

namespace skylee;

include_once __DIR__ . '/config-error.php';
require_once __DIR__ . '/FileSystem.php';

function clearCache() {
  FileSystem::deletePath($_SERVER['DOCUMENT_ROOT'] . '/cache');
  echo 'clear';
}
clearCache();
