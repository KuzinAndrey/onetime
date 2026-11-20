<?php
declare(strict_types=1);

/**
 * Пример конфигурации.
 *
 * Скопируйте в config/config.php и заполните реальными значениями.
 * config/config.php находится в .gitignore и никогда не должен попадать в git.
 */
return [
    // Базовый URL для генерации ссылок, например 'https://onetime.example.com'.
    // Пустое значение — брать из текущего запроса (схема + заголовок Host),
    // что корректно для развёртывания на собственном домене.
    'base_url' => '',

    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=onetime;charset=utf8mb4',
        'username' => 'onetime',
        'password' => 'change-me',
    ],

    'limits' => [
        'max_files'      => 10,
        'max_file_bytes' => 16 * 1024 * 1024,
    ],
];
