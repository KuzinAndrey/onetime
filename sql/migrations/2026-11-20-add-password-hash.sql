-- Одноразовая миграция: опциональный пароль для одноразовых ссылок.
--
-- Применять к уже созданным базам — новые установки уже содержат колонку
-- в sql/schema.sql. Выполнить ровно один раз.

ALTER TABLE one_time_link
    ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'NULL = без пароля; иначе bcrypt-хеш';