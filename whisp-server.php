#! env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Whisp\Server;
use Whisp\Loggers\FileLogger;

$server = new Server(port: 2020);

$server->setLogger(new FileLogger(__DIR__ . '/server.log'));

$server->run(apps: [
    'default' => __DIR__ . '/apps/prompts.php',
    'howdy' => __DIR__ . '/apps/howdy-world.php',
    'howdy-{name}' => __DIR__ . '/apps/howdy-world.php',
    'mouse' => __DIR__ . '/apps/mouse.php',
    'confetti' => __DIR__ . '/apps/confetti.php',
    'beep' => __DIR__ . '/apps/beep.php', // ghostty doesn't support BEL
    'clipboard-button' => __DIR__ . '/apps/clipboard-button.php',
    'clipboard' => __DIR__ . '/apps/clipboard-prompts.php',
]);
