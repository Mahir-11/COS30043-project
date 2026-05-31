-- ============================================================================
-- TierGG — MariaDB Schema for Swinburne Mercury (s104799137_db)
-- Run this via phpMyAdmin or mysql CLI to set up the database.
-- ============================================================================

USE s104799137_db;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in dependency order (child → parent)
DROP TABLE IF EXISTS tierlist_reactions;
DROP TABLE IF EXISTS tierlist_tiers;
DROP TABLE IF EXISTS tierlists;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS votes;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS genres;

-- ── Users ───────────────────────────────────────────────────────────────────
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       VARCHAR(20)  NOT NULL DEFAULT 'user',
    createdAt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Genres (lookup table) ───────────────────────────────────────────────────
CREATE TABLE genres (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Games ───────────────────────────────────────────────────────────────────
CREATE TABLE games (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    title     VARCHAR(200) NOT NULL,
    genre     VARCHAR(50)  NOT NULL,
    developer VARCHAR(100) NOT NULL,
    year      INT          NOT NULL,
    platforms VARCHAR(255) NOT NULL DEFAULT '',
    cover     VARCHAR(500) NOT NULL DEFAULT '',
    summary   TEXT         NOT NULL,
    featured  TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Reviews ─────────────────────────────────────────────────────────────────
CREATE TABLE reviews (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    gameId    INT          NOT NULL,
    userId    INT          NOT NULL,
    rating    INT          NOT NULL,
    title     VARCHAR(200) NOT NULL,
    body      TEXT         NOT NULL,
    createdAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gameId) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Votes (likes on reviews) ────────────────────────────────────────────────
CREATE TABLE votes (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    reviewId  INT      NOT NULL,
    userId    INT      NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (reviewId, userId),
    FOREIGN KEY (reviewId) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (userId)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Reports (flagged reviews) ───────────────────────────────────────────────
CREATE TABLE reports (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    reviewId  INT          NOT NULL,
    userId    INT          NOT NULL,
    reason    VARCHAR(100) NOT NULL,
    note      VARCHAR(280) NOT NULL DEFAULT '',
    status    VARCHAR(20)  NOT NULL DEFAULT 'open',
    createdAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewId) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (userId)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tier Lists ──────────────────────────────────────────────────────────────
CREATE TABLE tierlists (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    userId      INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         NOT NULL DEFAULT '',
    createdAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tier list items: which game is in which tier row (S, A, B, …)
CREATE TABLE tierlist_tiers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tierlistId INT         NOT NULL,
    tierName   VARCHAR(5)  NOT NULL,
    gameId     INT         NOT NULL,
    sortOrder  INT         NOT NULL DEFAULT 0,
    FOREIGN KEY (tierlistId) REFERENCES tierlists(id) ON DELETE CASCADE,
    FOREIGN KEY (gameId)     REFERENCES games(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tier List Reactions (like / dislike) ─────────────────────────────────────
CREATE TABLE tierlist_reactions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tierlistId INT         NOT NULL,
    userId     INT         NOT NULL,
    type       VARCHAR(10) NOT NULL DEFAULT 'like',
    createdAt  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (tierlistId, userId),
    FOREIGN KEY (tierlistId) REFERENCES tierlists(id) ON DELETE CASCADE,
    FOREIGN KEY (userId)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed Data ─────────────────────────────────────────────────────────────

-- ── Users ─────────────────────────────────────────────────────────────────── 
INSERT INTO users (id, username, password, role, createdAt) VALUES
(1, 'admin', 'admin123', 'admin', '2026-01-01 00:00:00'),
(2, 'demo',  'demo1234', 'user',  '2026-01-02 00:00:00');

-- ── Genres ──────────────────────────────────────────────────────────────────
INSERT INTO genres (id, name) VALUES
(1, 'RPG'), (2, 'FPS'), (3, 'Roguelike'), (4, 'Metroidvania'),
(5, 'Simulation'), (6, 'Platformer'), (7, 'Puzzle'), (8, 'Sandbox'),
(9, 'Action'), (10, 'Party');

-- ── Games ───────────────────────────────────────────────────────────────────
INSERT INTO games (id, title, genre, developer, year, platforms, cover, summary, featured) VALUES
(1,  'Elden Ring',               'RPG',          'FromSoftware',       2022, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co4jni.jpg', 'An open-world action RPG set in the Lands Between, crafted with George R. R. Martin.', 1),
(2,  'The Witcher 3: Wild Hunt', 'RPG',          'CD Projekt Red',     2015, 'PC,PS5,Switch',       'https://images.igdb.com/igdb/image/upload/t_cover_big/co1wyy.jpg', 'Geralt of Rivia hunts the Wild Hunt across a sprawling fantasy world.', 1),
(3,  'Hades',                    'Roguelike',    'Supergiant Games',   2020, 'PC,Switch,PS5',       'https://images.igdb.com/igdb/image/upload/t_cover_big/co39vc.jpg', 'Escape the Underworld in this fast, mythologically-charged roguelike.', 1),
(4,  'Hollow Knight',            'Metroidvania', 'Team Cherry',        2017, 'PC,Switch,PS4',       'https://images.igdb.com/igdb/image/upload/t_cover_big/co93cr.jpg', 'A hand-drawn metroidvania set in the haunting kingdom of Hallownest.', 1),
(5,  'Stardew Valley',           'Simulation',   'ConcernedApe',       2016, 'PC,Switch,Mobile',    'https://images.igdb.com/igdb/image/upload/t_cover_big/xrpmydnu9rpxvxfjkiu7.jpg', 'Inherit your grandfather''s old farm and start a new life.', 0),
(6,  'Celeste',                  'Platformer',   'Maddy Makes Games',  2018, 'PC,Switch',           'https://images.igdb.com/igdb/image/upload/t_cover_big/co2dto.jpg', 'Climb Celeste Mountain in this challenging, story-rich platformer.', 0),
(7,  'Dark Souls III',           'RPG',          'FromSoftware',       2016, 'PC,PS4,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co1vcf.jpg', 'The final chapter of the iconic Souls trilogy.', 0),
(8,  'Portal 2',                 'Puzzle',       'Valve',              2011, 'PC,PS3,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co1rbo.jpg', 'A mind-bending puzzle game starring Chell, GLaDOS, and Wheatley.', 1),
(9,  'Cyberpunk 2077',           'RPG',          'CD Projekt Red',     2020, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co7497.jpg', 'An open-world RPG in the neon-soaked Night City.', 0),
(10, 'Counter-Strike 2',         'FPS',          'Valve',              2023, 'PC',                  'https://images.igdb.com/igdb/image/upload/t_cover_big/co6phk.jpg', 'The legendary tactical shooter, rebuilt on Source 2.', 0),
(11, 'Minecraft',                'Sandbox',      'Mojang',             2011, 'PC,Switch,Mobile,Xbox,PS5', 'https://images.igdb.com/igdb/image/upload/t_cover_big/co49x5.jpg', 'Build, mine, and survive in an endlessly procedural world.', 0),
(12, 'Baldur''s Gate 3',         'RPG',          'Larian Studios',     2023, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co670h.jpg', 'Gather your party in a vast D&D-based fantasy adventure.', 1),
(13, 'Sekiro: Shadows Die Twice','Action',       'FromSoftware',       2019, 'PC,PS4,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co1rbb.jpg', 'A katana-driven action adventure set in Sengoku-era Japan.', 0),
(14, 'Among Us',                 'Party',        'Innersloth',         2018, 'PC,Mobile,Switch',    'https://images.igdb.com/igdb/image/upload/t_cover_big/co26f7.jpg', 'Find the impostor before they sabotage your crew.', 0),
(15, 'Disco Elysium',            'RPG',          'ZA/UM',              2019, 'PC,PS5,Switch',       'https://images.igdb.com/igdb/image/upload/t_cover_big/co1rb2.jpg', 'A groundbreaking detective RPG with no combat — only choices.', 0),
(16, 'Factorio',                 'Simulation',   'Wube Software',      2020, 'PC,Switch',           'https://images.igdb.com/igdb/image/upload/t_cover_big/co1tfj.jpg', 'Build and automate sprawling factories on an alien planet.', 0),
(17, 'Death Stranding',          'Action',       'Kojima Productions', 2019, 'PC,PS5',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co2gn8.jpg', 'Reconnect a fractured America in Hideo Kojima''s strangest world.', 0);

-- ── Reviews ─────────────────────────────────────────────────────────────────
INSERT INTO reviews (id, gameId, userId, rating, title, body, createdAt) VALUES
(1, 1, 2, 5, 'Masterpiece',   'An incredible open-world experience.',      '2026-02-01 10:00:00'),
(2, 3, 2, 5, 'One more run',  'Best roguelike I have ever played.',        '2026-02-03 12:00:00'),
(3, 8, 1, 5, 'Still holds up', 'Writing, pacing, and puzzles are perfect.', '2026-02-05 15:00:00');

-- ── Votes ───────────────────────────────────────────────────────────────────
INSERT INTO votes (id, reviewId, userId, createdAt) VALUES
(1, 1, 1, '2026-05-27 03:45:59');

-- ── Reports ─────────────────────────────────────────────────────────────────
INSERT INTO reports (id, reviewId, userId, reason, note, status, createdAt) VALUES
(1, 1, 1, 'Spoilers',            'Gives away a late-game boss.',   'open', '2026-02-06 08:00:00'),
(2, 1, 2, 'Off-topic',           '',                               'open', '2026-02-06 09:30:00'),
(3, 2, 1, 'Spam or advertising', 'Looks like a copy-paste promo.', 'open', '2026-02-07 11:00:00');

-- ── Tier Lists ──────────────────────────────────────────────────────────────
INSERT INTO tierlists (id, userId, title, description, createdAt) VALUES
(1, 2, 'Best Soulslikes Ever', 'From''s greatest hits, ranked.', '2026-02-10 09:00:00'),
(3, 1, 'My Tier List',         'admin',                          '2026-05-30 11:34:54'),
(4, 2, 'My Tier List',         'demo2',                          '2026-05-30 11:54:05');

-- Tier list items
INSERT INTO tierlist_tiers (tierlistId, tierName, gameId, sortOrder) VALUES
(1, 'S', 1,  0),
(1, 'S', 13, 1),
(1, 'A', 7,  0),
(3, 'S', 13, 0),
(4, 'S', 2,  0);

-- ── Tier List Reactions ─────────────────────────────────────────────────────
INSERT INTO tierlist_reactions (id, tierlistId, userId, type, createdAt) VALUES
(3,  4, 2, 'like',    '2026-05-30 11:54:08'),
(5,  1, 2, 'like',    '2026-05-30 11:54:11'),
(6,  3, 2, 'like',    '2026-05-30 11:54:14'),
(8,  3, 1, 'dislike', '2026-05-30 11:54:30'),
(9,  1, 1, 'like',    '2026-05-30 11:54:31'),
(10, 4, 1, 'dislike', '2026-05-30 11:54:35');

SET FOREIGN_KEY_CHECKS = 1;

