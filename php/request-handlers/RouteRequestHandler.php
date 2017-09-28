<?php

namespace skylee;

include_once __DIR__ . '/../config-error.php';
require_once __DIR__ . '/FileRequestHandler.php';

class RouteRequestHandler extends FileRequestHandler {
  
  public function handleRequest() {
    if (!isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_URI'])) return false;
    
    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    $accept = \preg_match('/^\/(?:event|team|about)?$/', $uri) ||
              \preg_match('/^\/team\/(?:badminton|basketball|handball|hockey|lacrosse|soccer|softball|squash|table\-tennis|volleyball|band|bridge|choir|dancing|debating|drama)$/', $uri) ||
              \preg_match('/^\/image-viewer/', $uri);
    if (!$accept) return false;
    
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/index.html';
    return $this->outputResponse($this->composeResponse([
      'filePath' => $filePath
    ]));
  }

}
