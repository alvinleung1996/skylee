<?php

namespace skylee;

include_once './config-error.php';
require_once './RequestHandler.php';

class RequestController {

  private static $requestHandlers = NULL;

  public static function importRequestHandlers() {
    self::$requestHandlers = new \SplPriorityQueue();
    $handlerPhp = glob('./request-handlers/*.php');
    if ($handlerPhp !== false) {
      foreach ($handlerPhp as $file) {
        include_once $file;
      }
    }
  }
  
  public static function addRequestHandler(RequestHandler $handler, $priority = 0) {
    self::$requestHandlers->insert($handler, $priority);
  }

  public static function handleRequest() {
    if (self::$requestHandlers !== NULL) {
      foreach (self::$requestHandlers as $handler) {
        if ($handler->acceptRequest() && $handler->handleRequest()) {
          break;
        }
      }
    }
  }

}

RequestController::importRequestHandlers();
RequestController::handleRequest();
exit();
