<?php

namespace skylee;

include_once __DIR__ . '/../../config-error.php';
require_once __DIR__ . '/../../RequestHandlerInterface.php';
require_once __DIR__ . '/../../FacebookAppInfo.php'; // Not include in git!!!

require_once __DIR__ . '/../../../vendor/autoload.php';

class AlbumsWithKeywordsAJAXRequestHandler implements RequestHandlerInterface {

  public function handleRequest() {
    if (!isset($_SERVER['REQUEST_URI'])) return false;

    $uri = \explode('?', $_SERVER['REQUEST_URI'])[0];
    if (\preg_match('/^\/ajax\/albums-with-keywords$/', $uri)) {

      $keywords = $this->getKeywords();
      if ($keywords === NULL) {
        $this->outputError('param "keywords" cannot be empty and must be an non-empty json array containing non-empty string!');
        return true;
      }

      $pageCursor = $this->getPageCursor();

      try {
        $albumsWithKeywords = $this->getAlbumsWithKeywords($this->getFacebook(), 'skylee.hku', $keywords, $pageCursor);
        $this->output($albumsWithKeywords);
      } catch (\Exception $e) {
        \error_log($e->getMessage());
        $this->outputError('Internal error occurred!');
      }
      
      return true;


    } elseif (\preg_match('/^\/ajax\/album-photos$/', $uri)) {

      $albumId = $this->getAlbumId();
      if ($albumId === NULL) {
        $this->outputError('param "album-id" cannot be empty!');
        return true;
      }

      $pageCursor = $this->getPageCursor();

      try {
        $albumPhotos = $this->getAlbumPhotos($this->getFacebook(), $albumId, $pageCursor);
        $this->output($albumPhotos);
      } catch (\Exception $e) {
        \error_log($e->getMessage());
        $this->outputError('Internal error occurred!');
      }

      return true;


    } else {
      return false;
    }
  }

  private function getKeywords() {
    $keywords = [];
    if (isset($_GET['keywords'])) {
      foreach (\explode(',', $_GET['keywords']) as $word) {
        if (($w = \trim($word)) !== '') {
          \array_push($keywords, $w);
        }
      }
    }
    return \count($keywords) > 0 ? $keywords : NULL;
  }

  private function getAlbumId() {
    return (isset($_GET['album-id']) && ($ai = \trim($_GET['album-id'])) !== '') ? $ai : NULL;
  }

  private function getPageCursor() {
    return (isset($_GET['page-cursor']) && ($pc = \trim($_GET['page-cursor'])) !== '') ? $pc : NULL;
  }

  private function getFacebook() {
    return new \Facebook\Facebook([
      'app_id' => FacebookAppInfo::ID,
      'app_secret' => FacebookAppInfo::SECRET,
      'default_access_token' => FacebookAppInfo::ID . '|' . FacebookAppInfo::SECRET
    ]);
  }


  private function outputError($message) {
    $this->output([
      'error' => [
        'message' => $message
      ]
    ]);
  }

  private function output($value) {
    echo \json_encode($value);
  }

  /**
   * @param array $albumsState array(
   *                            'pageCursor' => string,
   *                            'itemId' => string,
   *                            'albumPhotosPaging' => array(
   *                               'pageCursor' => string|null,
   *                               'itemId' => string|null
   *                             )
   *                           )
   */
  /*private function getAlbumsWithKeywords(\FaceBook\FaceBook $fb, $pageID, array $keywords, $photoLimit, array $paging = NULL) {
    $albumsWithKeywords = [
      'keywords' => [],
      'albums' => [
        'items' => [],
        'paging' => [
          'pageCursor' => NULL,
          'itemId' => NULL,
          'hasNext' => true
        ]
      ],
      'hasNextPhoto' => true
    ];
    $albumsWithKeywords['keywords'] = $keywords;
    $albumsWithKeywords['albums']['paging']['pageCursor'] = isset($paging['pageCursor']) ? $paging['pageCursor'] : NULL;
    $albumsWithKeywords['albums']['paging']['itemId'] = isset($paging['itemId']) ? $paging['itemId'] : NULL;

    $photoSpace = $photoLimit;

    if (isset($albumsWithKeywords['albums']['paging']['itemId'])) {
      $album = $this->getAlbum($fb, $albumsWithKeywords['albums']['paging']['itemId'], $photoSpace, isset($paging['albumPhotosPaging']) ? $paging['albumPhotosPaging'] : NULL);
      $albumsWithKeywords['albums']['items'][] = $album;
      $photoSpace -= \count($album['photos']['items']);
    }

    try {
      $pageCursor = isset($albumsWithKeywords['albums']['paging']['pageCursor']) ? $albumsWithKeywords['albums']['paging']['pageCursor'] : NULL; // false means last page, NULL means not specified
      $extractAlbums = !isset($albumsWithKeywords['albums']['paging']['itemId']);
      $hasNextPage = true;
      while ($photoSpace > 0 && $hasNextPage) {
        $params = [
          'limit' => 50,
          'fields' => 'id,name',
        ];
        if ($pageCursor !== NULL) $params['after'] = $pageCursor;
        $respond = $fb->sendRequest('GET', "{$pageID}/albums", $params);

        $albumsEdge = $respond->getGraphEdge(); // photos is always set even if photos is empty
        $albumsEdgeIterator = $albumsEdge->getIterator();
        $albumsEdgeIterator->rewind();

        while (!$extractAlbums && $albumsEdgeIterator->valid()) {
          $albumNode = $albumsEdgeIterator->current();
          $extractAlbums = $albumNode->getField('id') === $albumsWithKeywords['albums']['paging']['itemId'];
          $albumsEdgeIterator->next();
        }

        while ($extractAlbums && $albumsEdgeIterator->valid() && $photoSpace > 0) {
          $albumNode = $albumsEdgeIterator->current();
          if ($this->containKeyword($albumNode->getField('name'), $albumsWithKeywords['keywords'])) {
            $albumsWithKeywords['albums']['paging']['pageCursor'] = $pageCursor;
            $albumsWithKeywords['albums']['paging']['itemId'] = $albumNode->getField('id');
            $album = $this->getAlbum($fb, $albumNode->getField('id'), $photoSpace, NULL);
            $albumsWithKeywords['albums']['items'][] = $album;
            $photoSpace -= \count($album['photos']['items']);
          }
          $albumsEdgeIterator->next();
        }
        
        $hasNextPage = $albumsEdge->getPaginationUrl('next') !== NULL;
        if ($hasNextPage) $pageCursor = $albumsEdge->getNextCursor();

        $albumsWithKeywords['albums']['paging']['hasNext'] = $albumsEdgeIterator->valid() || $hasNextPage;

      }

      $albumsWithKeywords['hasNextPhoto'] = (\count($albumsWithKeywords['albums']['items']) > 0 && $albumsWithKeywords['albums']['items'][\count($albumsWithKeywords['albums']['items']) - 1]['photos']['paging']['hasNext']) ||
                                     $albumsWithKeywords['albums']['paging']['hasNext'];

    } catch (\Facebook\Exceptions\FacebookSDKException $e) {
      throw $e;
    }

    return $albumsWithKeywords;
  }

  private function getAlbum(\FaceBook\FaceBook $fb, $albumID, $photoLimit, array $paging = NULL) {
    $album = [
      'id' => NULL,
      'name' => NULL,
      'description' => NULL,
      'photos' => [
        'items' => [],
        'paging' => [
          'pageCursor' => NULL,
          'itemId' => NULL,
          'hasNext' => true
        ]
      ]
    ];
    $album['photos']['paging']['pageCursor'] = isset($paging['pageCursor']) ? $paging['pageCursor'] : NULL;
    $album['photos']['paging']['itemId'] = isset($paging['itemId']) ? $paging['itemId'] : NULL;

    try {
      $pageCursor = isset($album['photos']['paging']['pageCursor']) ? $album['photos']['paging']['pageCursor'] : NULL;
      $extractPhotos = !isset($album['photos']['paging']['itemId']);
      do {
        $fields = "id,name,description,link,photos.limit({$photoLimit})";
        //$fields = "id,name,description,link,photos.limit(10)";
        if ($pageCursor !== NULL) $fields .= ".after({$pageCursor})";
        $fields .= "{id,name,link}";
        $params = [
          'fields' => $fields,
        ];
        $respond = $fb->sendRequest('GET', $albumID, $params);

        $albumNode = $respond->getGraphAlbum();
        $album['id'] = $albumNode->getId();
        $album['name'] = $albumNode->getName();
        $album['description'] = $albumNode->getDescription();

        $photosEdge = $albumNode->getField('photos'); // photos is unset if photos is empty
        if ($photosEdge !== NULL) {
          $photosEdgeIterator = $photosEdge->getIterator();
          $photosEdgeIterator->rewind();

          while (!$extractPhotos && $photosEdgeIterator->valid()) {
            $node = $photosEdgeIterator->current();
            $extractPhotos = $node->getField('id') === $album['photos']['paging']['itemId'];
            $photosEdgeIterator->next();
          }

          while ($extractPhotos && $photosEdgeIterator->valid() && \count($album['photos']['items']) < $photoLimit) {
            $node = $photosEdgeIterator->current();
            $photo = [
              'id' => $node->getField('id'),
              'name' => $node->getField('name'),
              'link' => $node->getField('link')
            ];
            $album['photos']['items'][] = $photo;
            $album['photos']['paging']['pageCursor'] = $pageCursor;
            $album['photos']['paging']['itemId'] = $photo['id'];
            $photosEdgeIterator->next();
          }

          $hasNextPage = $photosEdge->getPaginationUrl('next') !== NULL;
          if ($hasNextPage) $pageCursor = $photosEdge->getNextCursor();

          $album['photos']['paging']['hasNext'] = $photosEdgeIterator->valid() || $hasNextPage;

        } else {
          $hasNextPage = false;
          $album['photos']['paging']['hasNext'] = false; // TODO: photosEdge can be null if limit=0 or cursor is invalid or there is really no more photos
        }

      } while (\count($album['photos']['items']) < $photoLimit && $hasNextPage);

    } catch (\Facebook\Exceptions\FacebookSDKException $e) {
      throw $e;
    }

    return $album;
  }*/

  private function getAlbumsWithKeywords(\Facebook\Facebook $fb, $pageId, array $keywords, $pageCursor = NULL) {
    try {
      $albumsWithKeywords = [
        'keywords' => $keywords,
        'albums' => [
          'items' => [],
          'paging' => [
            'cursor' => NULL,
            'hasNext' => false
          ]
        ]
      ];
      
      $params = [
        'limit' => 50,
        'fields' => 'id,name,description,link'
      ];
      if ($pageCursor !== NULL) $params['after'] = $pageCursor;

      $response = $fb->sendRequest('GET', "{$pageId}/albums", $params);

      $albumsEdge = $response->getGraphEdge();
      foreach ($albumsEdge as $albumNode) {
        if ($this->containKeyword($albumNode->getField('name'), $albumsWithKeywords['keywords'])) {
          $albumsWithKeywords['albums']['items'][] = [
            'id' => $albumNode->getField('id'),
            'name' => $albumNode->getField('name'),
            'description' => $albumNode->getField('description'),
            'link' => $albumNode->getField('link')
          ];
        }
      }

      $albumsWithKeywords['albums']['paging']['cursor'] = $albumsEdge->getNextCursor();
      $albumsWithKeywords['albums']['paging']['hasNext'] = $albumsEdge->getPaginationUrl('next') !== NULL;

      return $albumsWithKeywords;

    } catch (\Exception $e) {
      throw $e;
    }
  }

  private function containKeyword($str, array $keywords) {
    foreach ($keywords as $keyword) {
      if (\stripos($str, $keyword) !== false) {
        return true;
      }
    }
    return false;
  }
  
  private function getAlbumPhotos(\Facebook\Facebook $fb, $albumId, $pageCursor = NULL) {
    try {
      $albumPhotos = [
        'albumId' => $albumId,
        'photos' => [
          'items' => [],
          'paging' => [
            'cursor' => NULL,
            'hasNext' => false
          ]
        ]
      ];
      
      $params = [
        'limit' => 50,
        'fields' => 'id,name,description,link,images'
      ];
      if ($pageCursor !== NULL) $params['after'] = $pageCursor;
      $response = $fb->sendRequest('GET', "{$albumPhotos['albumId']}/photos", $params);

      $photosEdge = $response->getGraphEdge();
      foreach ($photosEdge as $photoNode) {
        $albumPhotos['photos']['items'][] = $this->nodeToPhoto($photoNode);
      }

      $albumPhotos['photos']['paging']['cursor'] = $photosEdge->getNextCursor();
      $albumPhotos['photos']['paging']['hasNext'] = $photosEdge->getPaginationUrl('next') !== NULL;

      return $albumPhotos;

    } catch (\Exception $e) {
      throw $e;
    }
  }

  private function nodeToPhoto(\Facebook\GraphNodes\GraphNode $node) {
    $images = $node->getField('images')->asArray();
    \usort($images, function ($a, $b) {
      return $a['width'] - $b['width'];
    });
    $thumbnail = $images[0];
    for ($i = 1; $i < \count($images) && !($thumbnail['width'] >= 150 && $thumbnail['height'] >= 150); ++$i) {
      $thumbnail = $images[$i];
    }
    return [
      'id' => $node->getField('id'),
      'name' => $node->getField('name'),
      'description' => $node->getField('description'),
      'link' => $node->getField('link'),
      'thumbnail' => $thumbnail,
      'image' => $images[\count($images) - 1]
    ];
  }
  
}
