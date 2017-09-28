<?php

namespace skylee;

include_once __DIR__ . '/config-error.php';
require_once __DIR__ . '/RequestHandlerPool.php';

function main() {
  $requestHandlerPool = new RequestHandlerPool();
  $requestHandlerPool->handleRequest();
}
main();
exit();
