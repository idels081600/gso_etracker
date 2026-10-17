CREATE TABLE IF NOT EXISTS `pickleball_tournament_state` (
    `state_key` VARCHAR(32) NOT NULL,
    `state_json` LONGTEXT NOT NULL,
    `state_updated_at` VARCHAR(40) NOT NULL,
    `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pickleball_single_match_state` (
    `state_key` VARCHAR(32) NOT NULL,
    `state_json` LONGTEXT NOT NULL,
    `state_updated_at` VARCHAR(40) NOT NULL,
    `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pickleball_tournament_teams` (
    `team_id` VARCHAR(100) NOT NULL,
    `division_id` VARCHAR(20) NOT NULL,
    `team_name` VARCHAR(160) NOT NULL,
    `games_played` INT UNSIGNED NOT NULL DEFAULT 0,
    `wins` INT UNSIGNED NOT NULL DEFAULT 0,
    `losses` INT UNSIGNED NOT NULL DEFAULT 0,
    `points_for` INT UNSIGNED NOT NULL DEFAULT 0,
    `points_against` INT UNSIGNED NOT NULL DEFAULT 0,
    `point_difference` INT NOT NULL DEFAULT 0,
    `updated_at` VARCHAR(40) NOT NULL,
    PRIMARY KEY (`team_id`),
    KEY `division_standings` (`division_id`, `wins`, `point_difference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pickleball_scorer_rate_limit` (
    `client_hash` CHAR(64) NOT NULL,
    `window_started` BIGINT UNSIGNED NOT NULL,
    `failures` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`client_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pickleball_tournament_actions` (
    `action_id` VARCHAR(64) NOT NULL,
    `role_id` VARCHAR(24) NOT NULL,
    `action_name` VARCHAR(40) NOT NULL,
    `payload_json` TEXT NOT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`action_id`),
    KEY `actions_by_role` (`role_id`, `applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

