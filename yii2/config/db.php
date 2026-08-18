<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=' . (getenv('DB_HOST') ?: 'mysql')
        . ';port=' . (getenv('DB_PORT') ?: '3306')
        . ';dbname=' . (getenv('DB_NAME') ?: 'tfbench'),
    'username' => getenv('DB_USER') ?: 'tfbench',
    'password' => getenv('DB_PASSWORD') ?: 'tfbench',
    'charset' => 'utf8mb4',
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 3600,
];
