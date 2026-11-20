-- Одноразовая миграция: журнал скачиваний вложений (download_log).
-- Применять к уже созданным базам — новые установки уже содержат таблицу
-- в sql/schema.sql. Идемпотентна (CREATE TABLE IF NOT EXISTS), повторное
-- применение безопасно.

CREATE TABLE IF NOT EXISTS download_log (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    link_id     INT UNSIGNED  NOT NULL,
    file_id     INT UNSIGNED  NOT NULL,
    ip          VARCHAR(45)   NOT NULL DEFAULT '',
    ua          VARCHAR(512)  NOT NULL DEFAULT '',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_download_log_link (link_id),
    KEY idx_download_log_file (file_id),
    CONSTRAINT fk_download_log_link FOREIGN KEY (link_id)
        REFERENCES one_time_link (id) ON DELETE CASCADE,
    CONSTRAINT fk_download_log_file FOREIGN KEY (file_id)
        REFERENCES attachment (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
