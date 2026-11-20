<?php
declare(strict_types=1);

final class AppError extends RuntimeException
{
    private int $status;

    public function __construct(string $message, int $status = 404)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function status(): int
    {
        return $this->status;
    }
}

final class App
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 128;

    private PDO $db;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $db = $config['db'];
        $this->db = new PDO($db['dsn'], $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function fromEnv(): self
    {
        $file = dirname(__DIR__) . '/config/config.php';
        if (!is_file($file)) {
            throw new AppError(
                'Приложение не настроено: создайте config/config.php (см. config/config.example.php)',
                500
            );
        }
        return new self(require $file);
    }

    public function renderForm(array $errors = [], string $oldMessage = ''): void
    {
        render_form($errors, $oldMessage);
    }

    /**
     * POST / — создать ссылку и перенаправить отправителя на /done/<uuid>.
     */
    public function createLink(): void
    {
        $message  = trim((string)($_POST['message'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $files    = $this->readUploads();

        $errors = [];
        if ($message === '' && $files === []) {
            $errors[] = 'Введите сообщение или прикрепите хотя бы одно вложение';
        }
        if ($password !== '') {
            $len = mb_strlen($password);
            if ($len < self::MIN_PASSWORD_LENGTH) {
                $errors[] = sprintf('Пароль должен быть не короче %d символов', self::MIN_PASSWORD_LENGTH);
            } elseif ($len > self::MAX_PASSWORD_LENGTH) {
                $errors[] = sprintf('Пароль должен быть не длиннее %d символов', self::MAX_PASSWORD_LENGTH);
            }
        }
        if ($errors !== []) {
            $this->renderForm($errors, (string)($_POST['message'] ?? ''));
            return;
        }

        $uuid = self::uuidV4();
        $stmt = $this->db->prepare(
            'INSERT INTO one_time_link (uuid, message, password_hash)
             VALUES (:uuid, :message, :password_hash)'
        );
        $stmt->execute([
            ':uuid'          => $uuid,
            ':message'       => $message,
            ':password_hash' => $password === '' ? null : password_hash($password, PASSWORD_BCRYPT),
        ]);
        $linkId = (int)$this->db->lastInsertId();

        if ($files !== []) {
            $insert = $this->db->prepare(
                'INSERT INTO attachment (link_id, file_name, content_type, file_size, data)
                 VALUES (:link_id, :name, :type, :size, :data)'
            );
            foreach ($files as $file) {
                $insert->execute([
                    ':link_id' => $linkId,
                    ':name'    => $file['name'],
                    ':type'    => $file['type'],
                    ':size'    => $file['size'],
                    ':data'    => $file['data'],
                ]);
            }
        }

        header('Location: /done/' . $uuid, true, 303);
    }

    /**
     * GET /done/<uuid> — страница отправителя: готовая ссылка и кнопка «скопировать».
     * Не затрагивает состояние ссылки.
     */
    public function renderDone(string $uuid): void
    {
        $link = $this->findLink($uuid);
        render_done($this->shareUrl($link['uuid']), self::hasPassword($link));
    }

    public function shareUrl(string $uuid): string
    {
        $base = trim((string)($this->config['base_url'] ?? ''));
        if ($base === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $base  = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return rtrim($base, '/') . '/' . $uuid;
    }

    /**
     * /<uuid> — страница получателя.
     * GET  до просмотра: подтверждение (чекбокс + «просмотреть»; поле пароля,
     *      если ссылка защищена).
     * POST с чекбоксом (и правильным паролем, если он задан): показ данных,
     *      фиксация просмотра.
     * После просмотра: предупреждение «уже просмотрено» + кто смотрел.
     */
    public function handleView(string $uuid): void
    {
        $link = $this->findLink($uuid);

        if ($link['viewed_at'] !== null) {
            render_viewed($link);
            return;
        }

        $hasPassword = self::hasPassword($link);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            render_confirm($link['uuid'], null, $hasPassword);
            return;
        }

        // Пароль проверяется до подтверждения: неверный пароль не расходует
        // однократный просмотр и не меняет состояние ссылки.
        if ($hasPassword && !password_verify((string)($_POST['password'] ?? ''), (string)$link['password_hash'])) {
            render_confirm($link['uuid'], 'Неверный пароль', $hasPassword);
            return;
        }

        if (($_POST['confirm'] ?? '') !== '1') {
            render_confirm($link['uuid'], 'Отметьте чекбокс, чтобы подтвердить просмотр', $hasPassword);
            return;
        }

        $this->markViewed((int)$link['id'], $link['uuid']);
        render_revealed($link, $this->attachmentsFor((int)$link['id']));
    }

    /**
     * GET /<uuid>/f/<id> — скачивание вложения.
     * Доступно только после того, как однократный просмотр уже потреблён:
     * до этого файл по ссылке не раскрывается, а состояние ссылки не меняется.
     */
    public function downloadAttachment(string $uuid, int $fileId): void
    {
        $link = $this->findLink($uuid);

        if ($link['viewed_at'] === null) {
            throw new AppError('Вложение не найдено');
        }

        $stmt = $this->db->prepare('SELECT * FROM attachment WHERE id = :id AND link_id = :link_id');
        $stmt->execute([':id' => $fileId, ':link_id' => $link['id']]);
        $file = $stmt->fetch();
        if ($file === false) {
            throw new AppError('Вложение не найдено');
        }

        header('Content-Type: ' . $file['content_type']);
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($file['file_name']));
        header('Content-Length: ' . (int)$file['file_size']);
        header('Cache-Control: no-store');
        echo $file['data'];
    }

    private function markViewed(int $id, string $uuid): void
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 512);

        $stmt = $this->db->prepare(
            'UPDATE one_time_link
             SET viewed_at = NOW(), viewer_ip = :ip, viewer_ua = :ua
             WHERE id = :id AND uuid = :uuid AND viewed_at IS NULL'
        );
        $stmt->execute([':ip' => $ip, ':ua' => $ua, ':id' => $id, ':uuid' => $uuid]);
    }

    /**
     * @return list<array{id: string, file_name: string, content_type: string, file_size: string}>
     */
    private function attachmentsFor(int $linkId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, file_name, content_type, file_size
             FROM attachment WHERE link_id = :link_id ORDER BY id'
        );
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll();
    }

    private function findLink(string $uuid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM one_time_link WHERE uuid = :uuid');
        $stmt->execute([':uuid' => $uuid]);
        $link = $stmt->fetch();
        if ($link === false) {
            throw new AppError('Ссылка не найдена. Проверьте, что она введена полностью.');
        }
        return $link;
    }

    private static function hasPassword(array $link): bool
    {
        $hash = $link['password_hash'] ?? null;
        return is_string($hash) && $hash !== '';
    }

    /**
     * @return list<array{name: string, type: string, size: int, data: string}>
     */
    private function readUploads(): array
    {
        $raw = $_FILES['files'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $errors = $raw['error'] ?? [];
        $count  = count($errors);
        if ($count === 0) {
            return [];
        }

        $maxFiles = (int)($this->config['limits']['max_files'] ?? 10);
        $maxBytes = (int)($this->config['limits']['max_file_bytes'] ?? 16 * 1024 * 1024);
        if ($count > $maxFiles) {
            throw new AppError(sprintf('Слишком много вложений: не более %d файлов', $maxFiles), 400);
        }

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new AppError('Не удалось загрузить одно из вложений (код ошибки ' . $error . ')', 400);
            }

            $name = $this->safeName((string)($raw['name'][$i] ?? ''));
            $size = (int)($raw['size'][$i] ?? 0);
            if ($size > $maxBytes) {
                throw new AppError(
                    sprintf('Файл «%s» слишком большой: не более %d МБ', $name, intdiv($maxBytes, 1024 * 1024)),
                    400
                );
            }

            $data = file_get_contents((string)($raw['tmp_name'][$i] ?? ''));
            if ($data === false) {
                throw new AppError('Не удалось прочитать загруженный файл', 400);
            }

            $result[] = [
                'name' => $name,
                'type' => $this->safeType((string)($raw['type'][$i] ?? '')),
                'size' => $size,
                'data' => $data,
            ];
        }
        return $result;
    }

    private function safeName(string $name): string
    {
        $name = str_replace("\0", '', basename($name));
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }
        return mb_substr($name, 0, 255);
    }

    private function safeType(string $type): string
    {
        $type = trim($type);
        if ($type === '' || !preg_match('/^[A-Za-z0-9.+-]+\/[A-Za-z0-9.+-]+$/', $type)) {
            return 'application/octet-stream';
        }
        return $type;
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
