#!/usr/bin/env php
<?php

use App\Services\FillInRotationService;

chdir(dirname(__DIR__));
require_once 'vendor/autoload.php';

if (!file_exists('config-private.php')) {
    fwrite(STDERR, "config-private.php is required.\n");
    exit(2);
}

$app = new stdClass();
require 'config-private.php';
if (!isset($app->db) || !$app->db instanceof PDO) {
    fwrite(STDERR, "config-private.php did not configure a PDO connection.\n");
    exit(2);
}
if (getenv('ENABLE_FILL_IN_ROTATION') === false && ($app->ENABLE_FILL_IN_ROTATION ?? false)) {
    putenv('ENABLE_FILL_IN_ROTATION=true');
}

date_default_timezone_set('America/New_York');
$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$yearID = null;
$languageID = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--year=')) {
        $yearID = max(1, (int)substr($argument, 7));
    } elseif (str_starts_with($argument, '--language=')) {
        $languageID = max(1, (int)substr($argument, 11));
    }
}

try {
    $results = (new FillInRotationService())->rotate($app->db, $dryRun, $force, $yearID, $languageID);
    if ($results === []) {
        fwrite(STDOUT, "No eligible rotation state was found.\n");
        exit(0);
    }
    $failed = false;
    foreach ($results as $result) {
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $failed = $failed || !($result['didSucceed'] ?? false);
    }
    exit($failed ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
