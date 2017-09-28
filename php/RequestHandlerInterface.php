<?php

namespace skylee;

include_once __DIR__ . '/config-error.php';

interface RequestHandlerInterface {

  public function handleRequest();

}
