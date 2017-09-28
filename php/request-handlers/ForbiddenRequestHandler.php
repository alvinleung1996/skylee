<?php

namespace skylee;

include_once __DIR__ . '/../config-error.php';
require_once __DIR__ . '/FileRequestHandler.php';

class ForbiddenRequestHandler extends FileRequestHandler {

  public function handleRequest() {
    if (!isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_URI'])) return false;

    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    $accept = (bool) \preg_match('/^\/(?:cache|php)/', $uri);
    if (!$accept) return false;

    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/index.html';
    return $this->outputResponse($this->composeResponse([
      'statusCode' => 404,
      'filePath' => $filePath
    ]));
  }

}
