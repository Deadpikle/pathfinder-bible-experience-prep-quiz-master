#!/usr/bin/env php
<?php

use App\Models\QuizAttempt;

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

$days = 30;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--days=')) {
        $days = max(1, (int)substr($argument, 7));
    }
}

$removed = QuizAttempt::purgeIncomplete($days, $app->db);
fwrite(STDOUT, "Removed {$removed} incomplete attempt(s) older than {$days} days.\n");
