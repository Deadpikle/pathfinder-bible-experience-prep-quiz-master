<?php

require_once 'config-private.php';

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
            'host' => $host,
            'name' => $db,
            'user' => $user,
            'pass' => $pass,
            'port' => '3306',
            'charset' => $charset,
        ],
        'development' => [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => $db,
            'user' => $user,
            'pass' => $pass,
            'port' => '3306',
            'charset' => $charset,
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'testing_db',
            'user' => 'root',
            'pass' => '',
            'port' => '3306',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
