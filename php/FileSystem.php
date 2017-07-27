<?php

namespace skylee;

include_once './config-error.php';

class FileSystem {

  private static function leftShiftPath(Path $base, Path $remain) {
    $comp = $remain->shift();
    if ($comp !== NULL) {
      $base->push($comp);
      return true;
    } else {
      return false;
    }
  }

  private static function rightShiftPath(Path $base, Path $remain) {
    $comp = $base->pop();
    if ($comp !== NULL) {
      $remain->unshift($comp);
      return true;
    } else {
      return false;
    }
  }

  public static function createDirectory($pathStr, $mode = 0777) {
    $base = new Path($pathStr);
    $remain = new Path();

    while (true) {
      \clearstatcache();
      if (\is_dir($base->toString())) {
        break;
      } else {
        if (!self::rightShiftPath($base, $remain)) {
          return false;
        }
      }
    }

    while (self::leftShiftPath($base, $remain)) {
      // https://stackoverflow.com/questions/19964287/mkdir-function-throw-exception-file-exists-even-after-checking-that-directory
      if (!@\mkdir($base->toString())) {
        \clearstatcache();
        if (!\is_dir($base->toString())) {
          \error_log("cannot make dir for \"{$base->toString()}\"");
          return false;
        }
      }
      if (!\chmod($base->toString(), $mode)) {
        \clearstatcache();
        if ((\fileperms($base->toString()) % 010000) !== $mode) {
          \error_log("cannot chmod for \"{$base->toString()}\"");
          return false;
        }
      }
    }

    return true;
  }



  public static function deletePath($pathStr) {
    return self::deletePathByPath(new Path($pathStr));
  }

  private static function deletePathByPath(Path $path) {
    \clearstatcache();
    if (\is_dir($path->toString())) {
      $files = \scandir($path->toString());
      if ($files === false) return false;
      $files = \array_diff($files, ['.', '..']);
      foreach ($files as $file) {
        $path->push($file);
        $success = self::deletePathByPath($path);
        $path->pop();
        if (!$success) return false;
      }
      return \rmdir($path->toString());

    } elseif (\is_file($path->toString())) {
      return \unlink($path->toString());

    } else {
      return false;
    }
  }
}

class Path {

  public function __construct($pathStr = '') {
    if (empty($pathStr) || $pathStr === '.') {
      $this->absolute = false;
      $this->comps = [];
    } elseif ($pathStr === '/') {
      $this->absolute = true;
      $this->comps = [];
    } else {
      $this->absolute = $pathStr[0] === '/';
      $this->comps = explode('/', $this->absolute ? substr($pathStr, 1) : $pathStr);
    }
  }
  
  private $comps;
  private $absolute;

  private $stringCache = NULL;

  public function isAbsolute() {
    return $this->absolute;
  }

  public function shift() {
    $this->clearCache();
    return \array_shift($this->comps);
  }

  public function unshift($comp) {
    $this->clearCache();
    \array_unshift($this->comps, $comp);
  }

  public function pop() {
    $this->clearCache();
    return \array_pop($this->comps);
  }

  public function push($comp) {
    $this->clearCache();
    \array_push($this->comps, $comp);
  }

  private function clearCache() {
    $this->stringCache = NULL;
  }

  public function toString() {
    if ($this->stringCache === NULL) {
      if (empty($this->comps)) {
        $this->stringCache = ($this->absolute ? '/' : '.');
      } else {
        $this->stringCache = ($this->absolute ? '/' : '') . \implode('/', $this->comps);
      }
    }
    return $this->stringCache;
  }

  public function __toString() {
    return $this->toString();
  }

}
