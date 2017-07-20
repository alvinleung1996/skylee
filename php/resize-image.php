<?php

include_once './config-error.php';
require_once './filesystem.php';

function getRequestPathInfo() {
  $root = $_SERVER['DOCUMENT_ROOT'];
  $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
  return array(
    'root' => $root,
    'uri' => $uri,
    'path' => $root . $uri
  );
}

function getRequestParam() {
  if (!isset($_GET['w'], $_GET['h'])) return false;
  
  if (!ctype_digit($_GET['w']) || !ctype_digit($_GET['h'])) return false;

  $w = intval($_GET['w']);
  $h = intval($_GET['h']);

  return array(
    'width' => $w,
    'height' => $h
  );
}

function isSupportedImage($path) {
  clearstatcache();
  if (!is_readable($path) || !is_file($path)) return false;

  $fileHandler = fopen($path, 'rb');
  flock($fileHandler, LOCK_SH);
  $imageInfo = getimagesize($path);
  flock($fileHandler, LOCK_UN);
  fclose($fileHandler);

  if ($imageInfo === false) return false;

  if ($imageInfo[2] === IMAGETYPE_GIF || $imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_JPEG) {
    return array(
      'width' => $imageInfo[0],
      'height' => $imageInfo[1],
      'type' => $imageInfo[2],
      'mime' => $imageInfo['mime']
    );
  } else {
    return false;
  }
}

/**
 * Assume the input image path is a valid image file
 */
function getOutputPath($pathInfo, $imageInfo, $param) {
  $uriInfo = pathinfo($pathInfo['uri']);
  $cachePath = $pathInfo['root'] . '/cache' . $uriInfo['dirname'] . '/' .
               $uriInfo['filename'] . ($param ? ".{$param['width']}x{$param['height']}" : ".{$imageInfo['width']}x{$imageInfo['height']}") .
               '.' . $uriInfo['extension'];

  clearstatcache();
  if (isSupportedImage($cachePath) && filemtime($cachePath) > filemtime($pathInfo['path'])) {
    return $cachePath;
  } elseif (createCache($pathInfo, $imageInfo, $param, $cachePath)) {
    return $cachePath;
  }
  return $pathInfo['path'];
}

/**
 * Assume the input image path is a valid image file
 */
function createCache($pathInfo, $imageInfo, $param, $cachePath) {
  $cacheDirPath = dirname($cachePath);
  clearstatcache();
  if (!is_dir($cacheDirPath) && !createDirectory($cacheDirPath, 0775)) return false;

  $fileHandler = fopen($pathInfo['path'], 'rb');
  flock($fileHandler, LOCK_SH);
  switch ($imageInfo['type']) {
    case IMAGETYPE_GIF: $image = imagecreatefromgif($pathInfo['path']); break;
    case IMAGETYPE_PNG: $image = imagecreatefrompng($pathInfo['path']); break;
    case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($pathInfo['path']); break;
  }
  flock($fileHandler, LOCK_UN);
  fclose($fileHandler);

  if ($param) {
    $scale = max(($param['width'] / $imageInfo['width']), ($param['height'] / $imageInfo['height']));
    $dstW = round($imageInfo['width'] * $scale);
    $dstH = round($imageInfo['height'] * $scale);
    $dstX = round(($param['width'] - $dstW) / 2);
    $dstY = round(($param['height'] - $dstH) / 2);
    $cacheImage = imagecreatetruecolor($param['width'], $param['height']);
    imagealphablending($cacheImage, false);
    imagecopyresampled($cacheImage, $image, $dstX, $dstY, 0, 0, $dstW, $dstH, $imageInfo['width'], $imageInfo['height']);
  } else {
    $cacheImage = $image;
  }
  
  imagealphablending($cacheImage, false);
  imagesavealpha($cacheImage, true);
  imageinterlace($cacheImage, 1);

  $cacheHandler = fopen($cachePath, 'cb');
  flock($cacheHandler, LOCK_EX);
  ftruncate($cacheHandler, 0);
  switch ($imageInfo['type']) {
    case IMAGETYPE_GIF: imagegif($cacheImage, $cachePath); break;
    case IMAGETYPE_PNG: imagepng($cacheImage, $cachePath); break;
    case IMAGETYPE_JPEG: imagejpeg($cacheImage, $cachePath); break;
  }
  chmod($cachePath, 0664);
  flock($cacheHandler, LOCK_UN);
  fclose($cacheHandler);

  imagedestroy($image);
  if ($image !== $cacheImage) { // $image === $cacheImage if $param === false, so do not destroy twice
    imagedestroy($cacheImage);
  }

  return true;
}

function handleRequest() {
  $pathInfo = getRequestPathInfo();
  if (!($imageInfo = isSupportedImage($pathInfo['path']))) return false;

  $param = getRequestParam();

  $outputPath = getOutputPath($pathInfo, $imageInfo, $param);

  if ($outputPath) {
    header('Content-Type: ' . $imageInfo['mime']);
    header('Content-Length: ' . filesize($outputPath));
    $outputFileHandler = fopen($outputPath, 'c+b');
    flock($outputFileHandler, LOCK_SH);
    readfile($outputPath);
    flock($outputFileHandler, LOCK_UN);
    fclose($outputFileHandler);
    return true;
  } 
  return false;
}
return handleRequest();
