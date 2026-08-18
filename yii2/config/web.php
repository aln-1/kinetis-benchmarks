<?php

$db = require __DIR__ . '/db.php';

return [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'components' => [
        'request' => [
            'cookieValidationKey' => 'benchmark-secret-key-not-for-production',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'json' => 'site/json',
                'plaintext' => 'site/plaintext',
                'db' => 'site/db',
                'queries' => 'site/queries',
                'updates' => 'site/updates',
                'fortunes' => 'site/fortunes',
            ],
        ],
    ],
];
