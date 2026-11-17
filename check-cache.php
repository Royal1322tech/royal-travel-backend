<?php
$path = __DIR__ . '/bootstrap/cache';

var_dump([
    'path' => $path,
    'exists' => is_dir($path),
    'writable' => is_writable($path),
]);
