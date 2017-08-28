<?php

namespace skylee;

include_once __DIR__ . '/../config-error.php';
require_once __DIR__ . '/../RequestHandlerInterface.php';

class PHPFileRequestHandler implements RequestHandlerInterface {

  public function handleRequest() {
    if (!isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_URI'])) return false;

    $path = $_SERVER['DOCUMENT_ROOT'] . \explode('?', $_SERVER['REQUEST_URI'])[0];

    \clearstatcache();
    $accept = \preg_match('/\.php$/', $path) && \is_file($path) && \is_readable($path);
    if (!$accept) return false;

    return (bool) include $path;
  }

}
