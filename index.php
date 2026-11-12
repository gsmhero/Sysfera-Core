<?php
declare(strict_types=1);
/* Sysfera entry point. All logic lives in core/ so this file never needs to change between versions. */
if (PHP_VERSION_ID < 80100) { http_response_code(500); exit('Sysfera needs PHP 8.1 or newer.'); }
require __DIR__ . '/core/app/bootstrap.php';
\Sysfera\App::get()->run();
