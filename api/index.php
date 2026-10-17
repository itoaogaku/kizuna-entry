<?php

/*
 * Vercel（テスト環境）用の入口。
 * Vercel で書き込めるのは /tmp だけなので、storage と各種キャッシュを /tmp に置いてから
 * いつもの public/index.php に渡す。Xserver では使わない。
 */
$storage = '/tmp/storage';
foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
    if (! is_dir("$storage/$dir")) {
        mkdir("$storage/$dir", 0777, true);
    }
}

$paths = [
    'LARAVEL_STORAGE_PATH' => $storage,
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
];
foreach ($paths as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv("$key=$value");
}

// テスト環境の既定値（Vercel の環境変数で上書きできる）
foreach (['LOG_CHANNEL' => 'stderr', 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database'] as $key => $value) {
    if (getenv($key) === false) {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("$key=$value");
    }
}

require __DIR__.'/../public/index.php';
