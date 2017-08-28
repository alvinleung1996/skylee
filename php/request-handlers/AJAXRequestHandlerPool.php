<?php

namespace skylee;

include_once __DIR__ . '/../config-error.php';
require_once __DIR__ . '/../RequestHandlerPool.php';

class AJAXRequestHandlerPool extends RequestHandlerPool {

  public function handleRequest() {
    if (!isset($_SERVER['REQUEST_URI'])) return false;

    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    if (!\preg_match('/^\/ajax\//', $uri)) return false;

    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    $ban = $referer === false ||
           (!\preg_match('/^http:\/\/(?:www\.)?skylee\.hku\.hk/', $referer) &&
            !\preg_match('/^http:\/\/localhost/', $referer) &&
            !\preg_match('/^http:\/\/127\.0\.0\.1/', $referer) &&
            !\preg_match('/^http:\/\/192\.168\.\d{1,3}\.\d{1,3}/', $referer));
    if ($ban) return false;

    if (!parent::handleRequest()) {
      echo \json_encode([
        'error' => [
          'message' => 'Unknown API call!'
        ]
      ]);
    }

    return true;
  }

  protected function getRequestHandlerClasses() {
    return [
      __DIR__ . '/ajax-request-handlers/AlbumsWithKeywordsAJAXRequestHandler.php' => '\skylee\AlbumsWithKeywordsAJAXRequestHandler'
    ];
  }

}
