-- Pickleball Open Play schema
-- Import via phpMyAdmin or: mysql -u USER -p DATABASE < sql/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) NOT NULL PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_players_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    play_date DATE NOT NULL,
    courts TINYINT UNSIGNED NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    track_results TINYINT(1) NOT NULL DEFAULT 0,
    timer_enabled TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft','active','done') NOT NULL DEFAULT 'draft',
    partner_warning_ack TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_players (
    session_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (session_id, player_id),
    CONSTRAINT fk_sp_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    slot_index SMALLINT UNSIGNED NOT NULL,
    starts_offset_min SMALLINT UNSIGNED NOT NULL,
    status ENUM('pending','active','done') NOT NULL DEFAULT 'pending',
    UNIQUE KEY uq_slot_session_index (session_id, slot_index),
    CONSTRAINT fk_slots_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_id INT UNSIGNED NOT NULL,
    court_number TINYINT UNSIGNED NOT NULL,
    p1_id INT UNSIGNED NOT NULL,
    p2_id INT UNSIGNED NOT NULL,
    p3_id INT UNSIGNED NOT NULL,
    p4_id INT UNSIGNED NOT NULL,
    team1_score SMALLINT UNSIGNED NULL,
    team2_score SMALLINT UNSIGNED NULL,
    winner_team TINYINT UNSIGNED NULL,
    UNIQUE KEY uq_game_slot_court (slot_id, court_number),
    CONSTRAINT fk_games_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_games_p1 FOREIGN KEY (p1_id) REFERENCES players(id),
    CONSTRAINT fk_games_p2 FOREIGN KEY (p2_id) REFERENCES players(id),
    CONSTRAINT fk_games_p3 FOREIGN KEY (p3_id) REFERENCES players(id),
    CONSTRAINT fk_games_p4 FOREIGN KEY (p4_id) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS slot_sitouts (
    slot_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (slot_id, player_id),
    CONSTRAINT fk_sitouts_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_sitouts_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Admin password is created on first login bootstrap from config.php default_admin_password (changeme).
