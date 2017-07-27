<?php

namespace skylee;

include_once './config-error.php';
require_once './request-handlers/FileRequestHandler.php';
require_once './RequestController.php';

class ForbiddenRequestHandler extends FileRequestHandler {

  public function acceptRequest() {
    if (!isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_URI'])) return false;
    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    $accept = (boolean)\preg_match('/^\/(?:cache|php)/', $uri);
    return $accept;
  }

  public function handleRequest() {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/index.html';
    return $this->outputResponse($this->composeResponse([
      'statusCode' => 404,
      'filePath' => $filePath
    ]));
  }

}
RequestController::addRequestHandler(new ForbiddenRequestHandler(), 100);
