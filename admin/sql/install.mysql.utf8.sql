CREATE TABLE IF NOT EXISTS `#__removesampledata_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `created` DATETIME NOT NULL,
    `user_id` INT NOT NULL DEFAULT 0,
    `run_id` VARCHAR(36) NOT NULL,
    `run_kind` VARCHAR(32) NOT NULL,
    `level` VARCHAR(16) NOT NULL DEFAULT 'info',
    `action` VARCHAR(64) NOT NULL,
    `item_type` VARCHAR(64) NOT NULL DEFAULT '',
    `item_id` VARCHAR(64) NOT NULL DEFAULT '',
    `item_label` VARCHAR(255) NOT NULL DEFAULT '',
    `detail` MEDIUMTEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_run` (`run_id`),
    KEY `idx_created` (`created`),
    KEY `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
