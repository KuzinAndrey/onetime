<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function human_size(int $bytes): string
{
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $value = (float)$bytes;
    $unit  = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return (round($value, $unit > 0 ? 1 : 0)) . ' ' . $units[$unit];
}

function plural_files(int $n): string
{
    $tens = abs($n) % 100;
    $ones = $tens % 10;
    if ($tens > 10 && $tens < 20) {
        return 'файлов';
    }
    if ($ones === 1) {
        return 'файл';
    }
    if ($ones >= 2 && $ones <= 4) {
        return 'файла';
    }
    return 'файлов';
}

function layout(string $title, string $body): string
{
    return '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e($title) . ' — одноразовая ссылка</title>
<link rel="stylesheet" href="/assets/style.css">
<script>
/* Применяем сохранённую тему до отрисовки, чтобы не было «вспышки»
   светлой/тёмной темы. Тёмная — по умолчанию. */
try { if (localStorage.getItem("onetime-theme") === "light") { document.documentElement.setAttribute("data-theme", "light"); } } catch (e) {}
</script>
</head>
<body>
<main class="card">
' . $body . '
</main>
<footer class="foot">одноразовая ссылка · просмотр один раз
<button type="button" class="theme-toggle" id="theme-toggle" aria-label="Сменить тему">Сменить тему</button>
</footer>
<script src="/assets/app.js"></script>
</body>
</html>';
}

function render_form(array $errors = [], string $oldMessage = '', array $limits = []): void
{
    $maxFiles = (int)($limits['max_files'] ?? 10);
    $maxBytes = (int)($limits['max_file_bytes'] ?? 16 * 1024 * 1024);

    $alert = $errors !== []
        ? '<div class="alert">' . implode('<br>', array_map('e', $errors)) . '</div>'
        : '';

    $body =
        '<h1>Одноразовое сообщение</h1>'
        . '<p class="hint">Получатель увидит данные <b>ровно один раз</b> — после этого '
        . 'по ссылке будет показано только то, кто их просматривал.</p>'
        . $alert
        . '<form method="post" action="/" enctype="multipart/form-data">'
        . '<label for="message">Сообщение</label>'
        . '<textarea id="message" name="message" rows="8" placeholder="Секретные данные: пароль, заметка, …">'
        . e($oldMessage) . '</textarea>'
        . '<label for="files">Вложения (необязательно) — до ' . $maxFiles . ' ' . plural_files($maxFiles) . ', до ' . human_size($maxBytes) . ' каждый</label>'
        . '<input type="file" id="files" name="files[]" multiple>'
        . '<ul id="file-list" class="file-list"></ul>'
        . '<label for="password">Пароль (необязательно)</label>'
        . '<input type="password" id="password" name="password" maxlength="128" '
        . 'autocomplete="new-password" placeholder="от 8 символов">'
        . '<p class="hint">Если пароль указан, получателю понадобится он, чтобы открыть ссылку. '
        . 'Передайте пароль отдельным каналом — не вместе со ссылкой.</p>'
        . '<button type="submit" class="btn">Отправить</button>'
        . '</form>';

    http_response_code(200);
    echo layout('Одноразовое сообщение', $body);
}

function render_done(string $url, bool $protected = false): void
{
    $body =
        '<h1>Ссылка готова</h1>'
        . '<p class="hint">Перешлите её получателю. Данные будут показаны <b>только один раз</b>, '
        . 'поэтому не открывайте её сами.</p>'
        . ($protected
            ? '<p class="hint">Ссылка защищена паролем — передайте его получателю отдельным '
            . 'сообщением, а не вместе со ссылкой.</p>'
            : '')
        . '<div class="link-box">'
        . '<code id="share-url">' . e($url) . '</code>'
        . '<button type="button" class="btn" data-copy="#share-url">Скопировать</button>'
        . '</div>';

    http_response_code(200);
    echo layout('Ссылка готова', $body);
}

function render_confirm(string $uuid, ?string $error, bool $password = false): void
{
    $alert = $error !== null ? '<div class="alert">' . e($error) . '</div>' : '';

    $passwordField = $password
        ? '<label for="password">Пароль</label>'
        . '<input type="password" id="password" name="password" required autocomplete="current-password">'
        : '';

    $body =
        '<h1>Одноразовое сообщение</h1>'
        . '<p class="hint">По этой ссылке можно посмотреть данные <b>только один раз</b>. '
        . 'После просмотра они станут недоступны для всех.</p>'
        . $alert
        . '<form method="post" action="/' . e($uuid) . '">'
        . $passwordField
        . '<label class="check">'
        . '<input type="checkbox" name="confirm" value="1">'
        . '<span>Я понимаю: данные будут показаны один раз, после чего по ссылке '
        . 'будет доступно только то, кто их просматривал</span>'
        . '</label>'
        . '<button type="submit" class="btn">Просмотреть</button>'
        . '</form>';

    http_response_code(200);
    echo layout('Одноразовое сообщение', $body);
}

function render_revealed(array $link, array $files): void
{
    $body = '<h1>Сообщение</h1>';

    if ($link['message'] !== '') {
        $body .= '<pre class="message">' . e($link['message']) . '</pre>';
    }

    if ($files !== []) {
        $items = '';
        foreach ($files as $file) {
            $href  = '/' . e($link['uuid']) . '/f/' . (int)$file['id'];
            $items .= '<li>'
                . e($file['file_name'])
                . ' <span class="muted">(' . human_size((int)$file['file_size']) . ')</span> '
                . '<a class="btn small" href="' . $href . '">Скачать</a>'
                . '</li>';
        }
        $body .= '<h2>Вложения</h2><ul class="file-list">' . $items . '</ul>';
    }

    $body .= '<p class="muted">После ухода с этой страницы по ссылке данные больше не отобразятся.</p>';

    http_response_code(200);
    echo layout('Сообщение', $body);
}

function render_viewed(array $link): void
{
    $body =
        '<h1>Ссылка уже была просмотрена</h1>'
        . '<div class="alert warn">Данные по этой ссылке уже просмотрели. Если это были не вы, '
        . 'ваша ссылка, возможно, скомпрометирована: кто-то другой посмотрел данные. '
        . 'Свяжитесь с тем, кто передал вам ссылку, и сообщите, что данные могли быть перехвачены.</div>'
        . '<h2>Кто просматривал</h2>'
        . '<table class="info">'
        . '<tr><td>Дата и время</td><td>' . e((string)$link['viewed_at']) . '</td></tr>'
        . '<tr><td>IP-адрес</td><td>' . e((string)$link['viewer_ip']) . '</td></tr>'
        . '<tr><td>User-Agent</td><td class="wrap">' . e((string)$link['viewer_ua']) . '</td></tr>'
        . '</table>';

    http_response_code(200);
    echo layout('Ссылка просмотрена', $body);
}

function render_404(string $message): void
{
    http_response_code(404);
    echo layout('Не найдено', '<h1>Не найдено</h1><p class="muted">' . e($message) . '</p>');
}

function render_error(int $status, string $message): void
{
    http_response_code($status);
    echo layout('Ошибка', '<h1>Ошибка</h1><div class="alert">' . e($message) . '</div>');
}
