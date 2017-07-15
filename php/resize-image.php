<?php
error_reporting(E_ALL);

$imagePath = $_SERVER['DOCUMENT_ROOT'] . explode('?', $_SERVER['REQUEST_URI'])[0];

if (!file_exists($imagePath)) {
  die();
}

if ((list($imageWidth, $imageHeight, $imageType) = getimagesize($imagePath)) !== false) {

  $directOutput = false;

  if (isset($_GET['w'], $_GET['h']) && ctype_digit($_GET['w']) && ctype_digit($_GET['h'])) {

    $image = false;
    switch ($imageType) {
      case IMAGETYPE_GIF: $image = imagecreatefromgif($imagePath); break;
      case IMAGETYPE_PNG: $image = imagecreatefrompng($imagePath); break;
      case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($imagePath); break;
    }

    if ($image) {
      $requestWidth = intval($_GET['w']);
      $requestHeight = intval($_GET['h']);

      $scale = max(($requestWidth / $imageWidth), ($requestHeight / $imageHeight));

      $dstW = round($imageWidth * $scale);
      $dstH = round($imageHeight * $scale);
      $dstX = round(($requestWidth - $dstW) / 2);
      $dstY = round(($requestHeight - $dstH) / 2);

      $outputImage = imagecreatetruecolor($requestWidth, $requestHeight);
      imagecopyresampled($outputImage, $image, $dstX, $dstY, 0, 0, $dstW, $dstH, $imageWidth, $imageHeight);

      imageinterlace($outputImage);

      ob_start();
      switch ($imageType) {
        case IMAGETYPE_GIF: imagegif($outputImage); break;
        case IMAGETYPE_PNG: imagepng($outputImage); break;
        case IMAGETYPE_JPEG: imagejpeg($outputImage); break;
      }
      header('Content-Type: ' . image_type_to_mime_type($imageType));
      header('Content-Length: ' . ob_get_length());
      ob_end_flush();
      $directOutput = true;

      imagedestroy($image);
      imagedestroy($outputImage);
    }
  }

  if (!$directOutput) {
    header('Content-Type: ' . image_type_to_mime_type($imageType));
    header('Content-Length: ' . filesize($imagePath));
    readfile($imagePath);
  }
}
die();
