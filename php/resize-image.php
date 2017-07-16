<?php
error_reporting(E_ALL);

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

  $fileHandler = fopen($path, 'c+b');
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
  if (file_exists($cacheDirPath)) {
    if (!is_dir($cacheDirPath) || !is_readable($cacheDirPath) || !is_writable($cacheDirPath)) return false;
  } else {
    /* Concurrency problem:
     * file exist warning triggered when two thread trying to make the same dir.
     * There is no lock available for directory so this warning cannot be prevented.
     */
    if (!@mkdir($cacheDirPath, 775, true)) {
      // https://stackoverflow.com/questions/19964287/mkdir-function-throw-exception-file-exists-even-after-checking-that-directory
      if (!is_dir($cacheDirPath) || !is_readable($cacheDirPath) || !is_writable($cacheDirPath)) return false;
    }
  }

  $fileHandler = fopen($pathInfo['path'], 'c+b');
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
  switch ($imageInfo['type']) {
    case IMAGETYPE_GIF: imagegif($cacheImage, $cachePath); break;
    case IMAGETYPE_PNG: imagepng($cacheImage, $cachePath); break;
    case IMAGETYPE_JPEG: imagejpeg($cacheImage, $cachePath); break;
  }
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
