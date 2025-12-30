<?php
/**
 * Глобальная конфигурация приложения KUB
 * ВАЖНО: таймзона задаётся здесь ОДИН РАЗ
 */

date_default_timezone_set('Europe/Chisinau');

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'oizsopkxtv_kub',
        'user'    => 'oizsopkxtv_kub',
        'pass'    => 'Pcelintsev2',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'name' => 'KUB',
        'env'  => 'production', // production | dev
    ],
];