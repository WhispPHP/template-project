<?php
stream_set_blocking(STDIN, false);
$input = '';
$timeout = 1.0; // seconds
$start = microtime(true);

while ((microtime(true) - $start) < $timeout) {
    $read = fread(STDIN, 1024);
    if ($read !== false) {
        $input .= $read;
    }

    if (feof(STDIN) || strlen($input) >= 1024) {
        break;
    }

    // Small sleep to prevent CPU spinning
    usleep(10000); // 10ms
}

if (empty($input)) {
    fwrite(STDERR, "No input received within timeout period - pipe into this\n");
    exit(1);
}

echo strtoupper($input);
