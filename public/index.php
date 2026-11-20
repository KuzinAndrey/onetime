<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app.php';
require dirname(__DIR__) . '/src/views.php';

$uuidPattern = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

try {
    $app = App::fromEnv();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path   = rtrim($path, '/') ?: '/';

    if ($path === '/') {
        if ($method === 'POST') {
            $app->createLink();
        } else {
            $app->renderForm();
        }
        return;
    }

    if (preg_match('#^/done/(' . $uuidPattern . ')$#', $path, $m)) {
        $app->renderDone($m[1]);
        return;
    }

    if (preg_match('#^/(' . $uuidPattern . ')$#', $path, $m)) {
        $app->handleView($m[1]);
        return;
    }

    if (preg_match('#^/(' . $uuidPattern . ')/f/(\d+)$#', $path, $m)) {
        $app->downloadAttachment($m[1], (int)$m[2]);
        return;
    }

    render_404('Страница не найдена');
} catch (AppError $e) {
    if ($e->status() === 404) {
        render_404($e->getMessage());
    } else {
        render_error($e->status(), $e->getMessage());
    }
}
