DROP TABLE IF EXISTS fx_users;
CREATE TABLE fx_users (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(255) NOT NULL,
    name       VARCHAR(255) NULL,
    age        INT NULL,
    score      DECIMAL(10, 2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fx_users_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS fx_items;
CREATE TABLE fx_items (
    sku   VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    qty   INT NOT NULL DEFAULT 0,
    PRIMARY KEY (sku)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
