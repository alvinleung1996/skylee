<?php

namespace skylee;

include_once __DIR__ . '/config-error.php';
require_once __DIR__ . '/RequestHandlerInterface.php';

class RequestHandlerPool implements RequestHandlerInterface {

  public function handleRequest() {
    $handlers = $this->getRequestHandlers();
    foreach ($handlers as $handler) {
      if ($handler->handleRequest()) {
        return true;
      }
    }
    return false;
  }

  protected function getRequestHandlers() {
    $classes = $this->getRequestHandlerClasses();
    foreach ($classes as $file => $className) {
      $result = (include_once $file);
      if ($result && \class_exists($className)) {
        yield (new $className());
      }
    }
  }

  protected function getRequestHandlerClasses() {
    return [
      // __DIR__ . '/request-handlers/AJAXRequestHandlerPool.php' => '\skylee\AJAXRequestHandlerPool',
      __DIR__ . '/request-handlers/ForbiddenRequestHandler.php' => '\skylee\ForbiddenRequestHandler',
      __DIR__ . '/request-handlers/RouteRequestHandler.php' => '\skylee\RouteRequestHandler',
      __DIR__ . '/request-handlers/ImageRequestHandler.php' => '\skylee\ImageRequestHandler',
      __DIR__ . '/request-handlers/FileRequestHandler.php' => '\skylee\FileRequestHandler'
    ];
  }

}
