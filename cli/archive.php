<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';

$args = array_values(array_slice($argv, 1));
$force = false;
$all = false;
$catalogArg = '';

foreach ($args as $arg) {
    $arg = (string) $arg;
    if ($arg === '--force' || $arg === '-f') {
        $force = true;
        continue;
    }
    if ($arg === '--all' || $arg === '-a') {
        $all = true;
        continue;
    }
    if ($arg !== '' && $arg[0] !== '-') {
        $catalogArg = $arg;
    }
}

try {
    if ($all || $catalogArg === '') {
        $results = sc_archive_sync_all($force);
    } else {
        $results = [sc_archive_sync(sc_slug($catalogArg), $force)];
    }

    foreach ($results as $result) {
        $id = (string) $result['catalog'];
        if (($result['status'] ?? '') === 'created') {
            fwrite(STDOUT, "[{$id}] Arhiva izrađena: {$result['xml']}, {$result['csv']}" . PHP_EOL);
        } else {
            fwrite(STDOUT, "[{$id}] Bez izmjene — samo čišćenje arhive." . PHP_EOL);
        }
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Greška: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
