-- onetime: одноразовые ссылки для передачи секретных данных.
-- Идиотентная схема: можно применять повторно.

CREATE TABLE IF NOT EXISTS one_time_link (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    uuid        CHAR(36)      NOT NULL,
    message     MEDIUMTEXT    NOT NULL,
    -- NULL = без пароля; иначе bcrypt-хеш (password_hash()), пароль в открытом
    -- виде нигде не хранится
    password_hash VARCHAR(255) NULL DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- заполняются ровно один раз, при однократном просмотре
    viewed_at   DATETIME      NULL DEFAULT NULL,
    viewer_ip   VARCHAR(45)   NULL DEFAULT NULL,
    viewer_ua   VARCHAR(512)  NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_one_time_link_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachment (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    link_id       INT UNSIGNED  NOT NULL,
    file_name     VARCHAR(255)  NOT NULL,
    content_type  VARCHAR(255)  NOT NULL DEFAULT 'application/octet-stream',
    file_size     INT UNSIGNED  NOT NULL DEFAULT 0,
    data          LONGBLOB      NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attachment_link (link_id),
    CONSTRAINT fk_attachment_link FOREIGN KEY (link_id)
        REFERENCES one_time_link (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
