<?php

namespace skylee;

include_once './config-error.php';

interface RequestHandler {

  public function acceptRequest();

  public function handleRequest();

}
