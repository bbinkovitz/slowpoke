<?php

require_once __DIR__.'/../vendor/autoload.php';

ini_set('debug_level', E_ALL | E_STRICT);

$app = new \Slowpoke\Application();

$app->run();
