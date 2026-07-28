<?php

if (file_exists('config-private.php')) {
    require_once 'config-private.php';
}

$configuredHost = $host ?? getenv('DB_HOST') ?: '127.0.0.1';
$configuredDatabase = $db ?? getenv('DB_NAME') ?: 'testing_db';
$configuredUser = $user ?? getenv('DB_USER') ?: 'root';
$configuredPassword = $pass ?? getenv('DB_PASSWORD') ?: '';
$configuredCharset = $charset ?? getenv('DB_CHARSET') ?: 'utf8mb4';
$configuredPort = getenv('DB_PORT') ?: '3306';

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'production' => [
            'adapter' => 'mysql',
            'host' => $configuredHost,
            'name' => $configuredDatabase,
            'user' => $configuredUser,
            'pass' => $configuredPassword,
            'port' => $configuredPort,
            'charset' => $configuredCharset,
        ],
        'development' => [
            'adapter' => 'mysql',
            'host' => $configuredHost,
            'name' => $configuredDatabase,
            'user' => $configuredUser,
            'pass' => $configuredPassword,
            'port' => $configuredPort,
            'charset' => $configuredCharset,
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'name' => getenv('DB_NAME') ?: 'testing_db',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => getenv('DB_PASSWORD') ?: '',
            'port' => getenv('DB_PORT') ?: '3306',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        ]
    ],
    'version_order' => 'creation'
];
