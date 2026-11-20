<?php
declare(strict_types=1);

// Удаление устаревших ссылок и их данных.
//
// Запускается из cron (пример в README):
//   17 3 * * * /usr/bin/php /var/www/onetime/tools/purge_old.php >> /var/log/onetime-purge.log 2>&1
//
// Удаляются (каскадно — вместе с вложениями и журналом скачиваний, по
// FK ON DELETE CASCADE):
//   - просмотренные ссылки старше purge.viewed_after_days (с момента просмотра);
//   - не открывавшиеся ссылки старше purge.unviewed_after_days (с момента создания) —
//     они могли не быть скачаны вовсе, и вложения не должны висеть вечно.
//
// Данные (текст и пароль) стираются сразу при просмотре; этот скрипт
// убирает вложения и саму строку ссылки, когда срок хранения истёк.

$file = dirname(__DIR__) . '/config/config.php';
if (!is_file($file)) {
    fwrite(STDERR, "config/config.php не найден\n");
    exit(1);
}
$cfg = require $file;

$dsn = $cfg['db']['dsn'];
$u   = $cfg['db']['username'];
$p   = $cfg['db']['password'];
$db  = new PDO($dsn, $u, $p, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$viewedDays   = (int)($cfg['purge']['viewed_after_days'] ?? 30);
$unviewedDays = (int)($cfg['purge']['unviewed_after_days'] ?? 14);

$viewedCutoff   = date('Y-m-d H:i:s', strtotime("-{$viewedDays} days"));
$unviewedCutoff = date('Y-m-d H:i:s', strtotime("-{$unviewedDays} days"));

$stmt = $db->prepare(
    'DELETE FROM one_time_link
     WHERE (viewed_at IS NOT NULL AND viewed_at < :vc)
        OR (viewed_at IS NULL AND created_at < :uc)'
);
$stmt->execute([':vc' => $viewedCutoff, ':uc' => $unviewedCutoff]);

printf(
    "%s purged %d link(s) (viewed < %s, unviewed < %s)\n",
    date('c'),
    $stmt->rowCount(),
    $viewedCutoff,
    $unviewedCutoff
);