<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';

$catalog = isset($argv[1]) ? sc_slug((string) $argv[1]) : (string) sc_config('catalog_default', 'main');

try {
    $files = sc_archive_create($catalog);
    fwrite(STDOUT, 'Arhiva je izrađena: ' . $files['xml'] . ', ' . $files['csv'] . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Greška: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
