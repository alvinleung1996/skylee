<?php

namespace skylee;

include_once __DIR__ . '/../config-error.php';
require_once __DIR__ . '/../FileSystem.php';
require_once __DIR__ . '/FileRequestHandler.php';

class ImageRequestHandler extends FileRequestHandler {

  public function handleRequest() {
    if (!isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_URI'])) return false;

    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    $path = $_SERVER['DOCUMENT_ROOT'] . $uri;

    \clearstatcache();
    if (!\preg_match('/\.(?:gif|png|jpe?g)$/', $uri) ||
        !\is_file($path) ||
        !\is_readable($path)) return false;

    $handler = \fopen($path, 'rb');
    if ($handler !== false) { \flock($handler, \LOCK_SH); }
    $type = \exif_imagetype($path);
    $supported = $type !== false &&
                 ($type === \IMAGETYPE_GIF || $type === \IMAGETYPE_PNG ||
                  $type === \IMAGETYPE_JPEG);
    if ($handler !== false) { \flock($handler, \LOCK_UN); \fclose($handler); }
    if (!$supported) return false;

    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    $redirect = $referer === false ||
                (!\preg_match('/^http:\/\/(?:www\.)?skylee\.hku\.hk/', $referer) &&
                 !\preg_match('/^http:\/\/localhost/', $referer) &&
                 !\preg_match('/^http:\/\/127\.0\.0\.1/', $referer) &&
                 !\preg_match('/^http:\/\/192\.168\.\d{1,3}\.\d{1,3}/', $referer));
    if ($redirect) {
      return $this->outputResponse($this->composeResponse([
        'statusCode' => 301,
        'location' => 'http://' . $_SERVER['HTTP_HOST'] . '/image-viewer/' . $uri
      ]));
      
    } else {
      $outputImagePath = $this->getOutputImagePath();
      if ($outputImagePath === false) return false;

      return $this->outputResponse($this->composeResponse([
        'filePath' => $outputImagePath
      ]));
    }
  }

  private function getOutputImagePath() {
    $root = $_SERVER['DOCUMENT_ROOT'];
    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    $uriInfo = \pathinfo($uri);
    $sourcePath = $root . $uri;

    $paramInfo = $this->getRequestParamInfo();

    $sourceHandler = \fopen($sourcePath, 'rb');
    if ($sourceHandler !== false) { \flock($sourceHandler, \LOCK_SH); }
    $sourceImageInfo = \getimagesize($sourcePath);
    if ($sourceHandler !== false) { \flock($sourceHandler, \LOCK_UN); \fclose($sourceHandler); }
    if ($sourceImageInfo === false) return false;

    $cachePath = $root . '/cache' . $uriInfo['dirname'] . '/' .
                 $uriInfo['filename'] . ($paramInfo ? ".{$paramInfo['width']}x{$paramInfo['height']}" : ".{$sourceImageInfo[0]}x{$sourceImageInfo[1]}") .
                 '.' . $uriInfo['extension'];

    \clearstatcache();
    if (\is_file($cachePath)) {
      $sourceMTime = \filemtime($sourcePath);
      $cacheMTime = \filemtime($cachePath);
      if ($sourceMTime !== false && $cacheMTime !== false && $cacheMTime >= $sourceMTime) {
        return $cachePath;
      }
    }

    if ($this->createImageCache($sourcePath, $cachePath, $paramInfo)) {
      return $cachePath;
    }

    return $sourcePath;
  }

  private function getRequestParamInfo() {
    if (!isset($_GET['w'], $_GET['h'])) return NULL;

    $width = \intval($_GET['w']);
    $height = \intval($_GET['h']);
    if ($width <= 0 || $height <= 0) return NULL;

    return [
      'width' => $width,
      'height' => $height
    ];
  }

  /**
   * return true if image cache created successfully, false otherwise
   */
  private function createImageCache($sourcePath, $cachePath, $paramInfo) {
    $cacheDirPath = \dirname($cachePath);
    \clearstatcache();
    if (!\is_dir($cacheDirPath) && !FileSystem::createDirectory($cacheDirPath, 0775)) return false;

    $sourceHandler = \fopen($sourcePath, 'rb');
    if ($sourceHandler !== false) { \flock($sourceHandler, \LOCK_SH); }
    $imageType = \exif_imagetype($sourcePath);
    switch ($imageType) {
      case \IMAGETYPE_GIF: $image = \imagecreatefromgif($sourcePath); break;
      case \IMAGETYPE_PNG: $image = \imagecreatefrompng($sourcePath); break;
      case \IMAGETYPE_JPEG: $image = \imagecreatefromjpeg($sourcePath); break;
      default: $image = false;
    }
    if ($sourceHandler !== false) { \flock($sourceHandler, \LOCK_UN); \fclose($sourceHandler); }

    if ($image === false) return false;

    $imageWidth = \imagesx($image);
    $imageHeight = \imagesy($image);

    if ($paramInfo) {
      $scale = \max(($paramInfo['width'] / $imageWidth), ($paramInfo['height'] / $imageHeight));
      $dstW = \round($imageWidth * $scale);
      $dstH = \round($imageHeight * $scale);
      $dstX = \round(($paramInfo['width'] - $dstW) / 2);
      $dstY = \round(($paramInfo['height'] - $dstH) / 2);
      $cacheImage = \imagecreatetruecolor($paramInfo['width'], $paramInfo['height']);
      \imagealphablending($cacheImage, false);
      \imagecopyresampled($cacheImage, $image, $dstX, $dstY, 0, 0, $dstW, $dstH, $imageWidth, $imageHeight);
    } else {
      $cacheImage = $image;
    }
    
    \imagealphablending($cacheImage, false);
    \imagesavealpha($cacheImage, true);
    \imageinterlace($cacheImage, 1);

    $cacheHandler = \fopen($cachePath, 'cb');
    if ($cacheHandler !== false) { \flock($cacheHandler, \LOCK_EX); \ftruncate($cacheHandler, 0); }
    switch ($imageType) {
      case IMAGETYPE_GIF: $result = \imagegif($cacheImage, $cachePath); break;
      case IMAGETYPE_PNG: $result = \imagepng($cacheImage, $cachePath); break;
      case IMAGETYPE_JPEG: $result = \imagejpeg($cacheImage, $cachePath); break;
    }
    \chmod($cachePath, 0664);
    if ($cacheHandler !== false) { \flock($cacheHandler, \LOCK_UN); \fclose($cacheHandler); }

    \imagedestroy($image);
    if ($image !== $cacheImage) { // $image === $cacheImage if $param === false, so do not destroy twice
      \imagedestroy($cacheImage);
    }

    return $result;
  }

}
