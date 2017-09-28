<?php

namespace skylee;

include_once __DIR__ . '/../config-error.php';
require_once __DIR__ . '/../RequestHandlerInterface.php';

class FileRequestHandler implements RequestHandlerInterface {

  public function handleRequest() {
    if (!isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_URI'])) return false;

    $path = $_SERVER['DOCUMENT_ROOT'] . \explode('?', $_SERVER['REQUEST_URI'])[0];
    return $this->outputResponse($this->composeResponse([
      'filePath' => $path
    ]));
  }

  protected function composeResponse($param) {
    if (!isset($param['statusCode']) && isset($param['filePath'])) {
      if (\is_file($param['filePath']) && \is_readable($param['filePath'])) {
        $clientCacheETag = $this->getClientCacheETag();
        $fileETag = $this->getFileEtag($param['filePath']);
        $modified = $clientCacheETag === false || $clientCacheETag === NULL ||
                    $fileETag === false || $clientCacheETag !== $fileETag;
        
        if ($modified) {
          $param['statusCode'] = 200;
          $param['eTag'] = $fileETag;
        } else {
          $param['statusCode'] = 304;
          unset($param['filePath']);
        }
        
        if (!isset($param['cacheControl'])) $param['cacheControl'] = 'max-age=60';

      } else {
        $param['statusCode'] = 404;
        $param['filePath'] = $_SERVER['DOCUMENT_ROOT'] . '/index.html';
      }
    }

    if (isset($param['filePath'])) {
      $param['contentType'] = \mime_content_type($param['filePath']);
      $param['contentLength'] = \filesize($param['filePath']);
    }

    return $param;
  }

  private function getClientCacheETag() {
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
      return \str_replace(['"', '-gzip'], '', $_SERVER['HTTP_IF_NONE_MATCH']);
    } else {
      return NULL;
    }
  }

  private function getFileETag($path) {
    \clearstatcache();

    $size = \filesize($path);
    if ($size === false) return false;

    $mTime = \filemtime($path);
    if ($mTime === false) return false;

    return \md5("{$path}:{$size}:{$mTime}");
  }

  protected function outputResponse($param) {
    if (isset($param['statusCode'])) \http_response_code($param['statusCode']);

    if (isset($param['location'])) header('Location: ' . $param['location']);

    if (isset($param['contentType'])) header('Content-Type: ' . $param['contentType']);

    if (isset($param['cacheControl'])) header('Cache-Control: ' . $param['cacheControl']);

    if (isset($param['eTag'])) header('ETag: "' . $param['eTag'] . '"');

    if (isset($param['contentLength'])) header('Content-Length: ' . $param['contentLength']);

    if (isset($param['filePath'])) {
      $fileHandler = \fopen($param['filePath'], 'rb');
      if ($fileHandler !== false) \flock($fileHandler, \LOCK_SH);
      \readfile($param['filePath']);
      if ($fileHandler !== false) { \flock($fileHandler, \LOCK_UN); \fclose($fileHandler); }
    }

    return true;
  }

}
