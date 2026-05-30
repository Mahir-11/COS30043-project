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
    createdAt  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    createdAt TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gameId) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Votes (likes on reviews) ────────────────────────────────────────────────
CREATE TABLE votes (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    reviewId  INT      NOT NULL,
    userId    INT      NOT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    createdAt TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewId) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (userId)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tier Lists ──────────────────────────────────────────────────────────────
CREATE TABLE tierlists (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    userId      INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         NOT NULL DEFAULT '',
    createdAt   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    createdAt  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (tierlistId, userId),
    FOREIGN KEY (tierlistId) REFERENCES tierlists(id) ON DELETE CASCADE,
    FOREIGN KEY (userId)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
--  SEED DATA (mirrors db.json)
-- ============================================================================

-- ── Users ───────────────────────────────────────────────────────────────────
INSERT INTO users (id, username, password, role, createdAt) VALUES
(1, 'admin',     'admin123', 'admin', '2026-01-01 00:00:00'),
(2, 'demo',      'demo1234', 'user',  '2026-01-02 00:00:00'),
(3, 'soulslord', 'darksoul1','user',  '2026-01-05 14:22:00'),
(4, 'pixelpaige','paige2024','user',  '2026-01-08 09:10:00'),
(5, 'nikora',    'nikora123','user',  '2026-01-12 18:45:00'),
(6, 'mahir',     'mahir123', 'user',  '2026-02-14 21:00:00'),
(7, 'jin',       'jinjin99', 'user',  '2026-03-02 11:11:11');

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
(17, 'Death Stranding',          'Action',       'Kojima Productions', 2019, 'PC,PS5',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co2gn8.jpg', 'Reconnect a fractured America in Hideo Kojima''s strangest world.', 0),
(18, 'The Legend of Zelda: Breath of the Wild', 'Action',     'Nintendo EPD',       2017, 'Switch,WiiU',        'https://images.igdb.com/igdb/image/upload/t_cover_big/co3p2d.jpg', 'A reinvention of Zelda — climb anything, cook anything, explore everywhere.', 1),
(19, 'Red Dead Redemption 2',    'Action',       'Rockstar Games',     2018, 'PC,PS4,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co1q1f.jpg', 'A sprawling Western epic following the Van der Linde gang''s last days.', 1),
(20, 'God of War Ragnarök',      'Action',       'Santa Monica Studio',2022, 'PS5,PS4,PC',          'https://images.igdb.com/igdb/image/upload/t_cover_big/co5s5v.jpg', 'Kratos and Atreus face Ragnarök across the Nine Realms.', 1),
(21, 'Horizon Zero Dawn',        'Action',       'Guerrilla Games',    2017, 'PC,PS4',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co2mvt.jpg', 'A post-apocalyptic Earth ruled by machine beasts.', 0),
(22, 'Persona 5 Royal',          'RPG',          'Atlus',              2019, 'PC,PS5,Switch,Xbox',  'https://images.igdb.com/igdb/image/upload/t_cover_big/co4xn3.jpg', 'A stylish JRPG about Tokyo teens leading double lives as Phantom Thieves.', 1),
(23, 'Final Fantasy XVI',        'RPG',          'Square Enix',        2023, 'PS5,PC',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co5s8j.jpg', 'A darker, action-heavy entry in the Final Fantasy saga.', 0),
(24, 'Resident Evil 4 Remake',   'Action',       'Capcom',             2023, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co6bnf.jpg', 'Leon S. Kennedy''s rescue mission, faithfully reborn.', 1),
(25, 'Doom Eternal',             'FPS',          'id Software',        2020, 'PC,PS5,Xbox,Switch',  'https://images.igdb.com/igdb/image/upload/t_cover_big/co2ko4.jpg', 'Rip and tear through Hell''s armies at breakneck speed.', 0),
(26, 'Apex Legends',             'FPS',          'Respawn Entertainment',2019,'PC,PS5,Xbox,Switch', 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1wkl.jpg', 'A free-to-play hero shooter battle royale.', 0),
(27, 'Valorant',                 'FPS',          'Riot Games',         2020, 'PC',                  'https://images.igdb.com/igdb/image/upload/t_cover_big/co2mvt.jpg', 'Tactical 5v5 shooter blending CS-style gunplay with hero abilities.', 0),
(28, 'League of Legends',        'Action',       'Riot Games',         2009, 'PC',                  'https://images.igdb.com/igdb/image/upload/t_cover_big/co49wj.jpg', 'The world''s most-played MOBA.', 0),
(29, 'Genshin Impact',           'RPG',          'HoYoverse',          2020, 'PC,PS5,Mobile',       'https://images.igdb.com/igdb/image/upload/t_cover_big/co2kqf.jpg', 'A gacha-driven open-world RPG set in Teyvat.', 0),
(30, 'Lies of P',                'RPG',          'Round8 Studio',      2023, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co74kw.jpg', 'A Pinocchio-inspired soulslike in a fallen Belle Époque city.', 0),
(31, 'Alan Wake 2',              'Action',       'Remedy Entertainment',2023,'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co6h5q.jpg', 'A survival-horror sequel thirteen years in the making.', 1),
(32, 'Marvel''s Spider-Man 2',   'Action',       'Insomniac Games',    2023, 'PS5',                 'https://images.igdb.com/igdb/image/upload/t_cover_big/co6kdz.jpg', 'Peter and Miles swing through a bigger New York together.', 1),
(33, 'Starfield',                'RPG',          'Bethesda Game Studios',2023,'PC,Xbox',            'https://images.igdb.com/igdb/image/upload/t_cover_big/co5ziw.jpg', 'Bethesda''s first new universe in 25 years — a galaxy of 1,000+ planets.', 0),
(34, 'Diablo IV',                'RPG',          'Blizzard Entertainment',2023,'PC,PS5,Xbox',       'https://images.igdb.com/igdb/image/upload/t_cover_big/co5s5v.jpg', 'Sanctuary returns, darker and more open than ever.', 0),
(35, 'Street Fighter 6',         'Action',       'Capcom',             2023, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co6cs7.jpg', 'A bold new era for the legendary fighting franchise.', 0),
(36, 'Tekken 8',                 'Action',       'Bandai Namco',       2024, 'PC,PS5,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co7437.jpg', 'The Mishima saga concludes in a flashy, mechanically deep brawler.', 0),
(37, 'Helldivers 2',             'FPS',          'Arrowhead Game Studios',2024,'PC,PS5',            'https://images.igdb.com/igdb/image/upload/t_cover_big/co7ddu.jpg', 'Spread Managed Democracy across the galaxy in 4-player co-op.', 1),
(38, 'Palworld',                 'Sandbox',      'Pocketpair',         2024, 'PC,Xbox',             'https://images.igdb.com/igdb/image/upload/t_cover_big/co7yj8.jpg', 'Catch, craft, and battle creatures in an open survival world.', 0),
(39, 'The Legend of Zelda: Tears of the Kingdom','Action','Nintendo EPD',2023,'Switch',             'https://images.igdb.com/igdb/image/upload/t_cover_big/co5vmg.jpg', 'Hyrule''s sequel — now with sky islands and limitless building.', 1),
(40, 'Animal Crossing: New Horizons','Simulation','Nintendo EPD',      2020, 'Switch',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co2kjv.jpg', 'Build your dream island life at your own pace.', 0),
(41, 'Mario Kart 8 Deluxe',      'Party',        'Nintendo EAD',       2017, 'Switch',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co1soa.jpg', 'The definitive Mario Kart — 96 tracks and counting.', 0),
(42, 'Super Smash Bros. Ultimate','Party',       'Bandai Namco/Sora',  2018, 'Switch',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co1pvr.jpg', 'Everyone is here. The biggest crossover fighter ever made.', 1),
(43, 'Splatoon 3',               'FPS',          'Nintendo EPD',       2022, 'Switch',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co54ru.jpg', 'Paint, splat, and rule the turf with ink-shooting kids and squids.', 0),
(44, 'Pokémon Scarlet',          'RPG',          'Game Freak',         2022, 'Switch',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co574z.jpg', 'An open-world Pokémon adventure in the Paldea region.', 0),
(45, 'Monster Hunter: World',    'Action',       'Capcom',             2018, 'PC,PS4,Xbox',         'https://images.igdb.com/igdb/image/upload/t_cover_big/co1rcp.jpg', 'Hunt towering monsters across living, breathing ecosystems.', 0),
(46, 'Resident Evil Village',    'Action',       'Capcom',             2021, 'PC,PS5,Xbox,Switch',  'https://images.igdb.com/igdb/image/upload/t_cover_big/co2dze.jpg', 'Ethan Winters returns to a snowy village of werewolves and vampires.', 0),
(47, 'The Last of Us Part II',   'Action',       'Naughty Dog',        2020, 'PS5,PS4,PC',          'https://images.igdb.com/igdb/image/upload/t_cover_big/co5ziy.jpg', 'A brutal, divisive sequel about cycles of revenge.', 1),
(48, 'Bloodborne',               'RPG',          'FromSoftware',       2015, 'PS4',                 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1rb6.jpg', 'A Gothic horror soulslike set in cursed Yharnam.', 1),
(49, 'Returnal',                 'Roguelike',    'Housemarque',        2021, 'PC,PS5',              'https://images.igdb.com/igdb/image/upload/t_cover_big/co2zlb.jpg', 'A bullet-hell roguelike on an alien planet that resets when you die.', 0),
(50, 'Outer Wilds',              'Puzzle',       'Mobius Digital',     2019, 'PC,PS5,Xbox,Switch',  'https://images.igdb.com/igdb/image/upload/t_cover_big/co1x77.jpg', 'A 22-minute solar-system mystery you uncover by exploring.', 1);

-- ── Reviews ─────────────────────────────────────────────────────────────────
INSERT INTO reviews (id, gameId, userId, rating, title, body, createdAt) VALUES
(1,  1,  2, 5, 'Masterpiece',         'An incredible open-world experience. Every direction hides something worth finding.', '2026-02-01 10:00:00'),
(2,  3,  2, 5, 'One more run',        'Best roguelike I have ever played — the writing makes losses feel like progress.',  '2026-02-03 12:00:00'),
(3,  8,  1, 5, 'Still holds up',      'Writing, pacing, and puzzles are perfect. A genuine classic.',                       '2026-02-05 15:00:00'),
(4,  2,  3, 5, 'Geralt forever',      'Hearts of Stone alone justifies the price of admission.',                            '2026-02-08 14:30:00'),
(5,  4,  4, 5, 'A hand-drawn dream',  'Hollow Knight respects your time and rewards your curiosity.',                       '2026-02-10 19:45:00'),
(6,  12, 5, 5, 'Tabletop magic',      'Larian nailed the feel of a real D&D session — chaotic, hilarious, and personal.',   '2026-02-12 22:10:00'),
(7,  18, 3, 5, 'Climb everything',    'BotW changed open-world design forever. Still feels fresh.',                         '2026-02-14 11:00:00'),
(8,  19, 6, 4, 'Slow but rewarding',  'The pacing is glacial in places, but the story payoff is worth it.',                 '2026-02-15 17:25:00'),
(9,  20, 3, 5, 'Bigger and bolder',   'Ragnarök iterates on 2018''s combat and lands every beat.',                          '2026-02-17 13:00:00'),
(10, 22, 4, 5, 'Style on style',      'Persona 5 Royal is the most stylish JRPG ever made. Full stop.',                     '2026-02-18 20:00:00'),
(11, 24, 7, 5, 'Remake done right',   'Faithful where it counts, brave where it needs to be.',                              '2026-02-20 09:15:00'),
(12, 25, 5, 5, 'Pure adrenaline',     'Doom Eternal is a violent dance — there''s nothing else like it.',                   '2026-02-22 16:40:00'),
(13, 31, 6, 5, 'Genre-bending',       'Alan Wake 2 jumps between mediums like nothing else this year.',                     '2026-02-25 21:30:00'),
(14, 32, 4, 4, 'Swinging is bliss',   'The traversal is unmatched but the story drags in the middle act.',                  '2026-02-27 18:00:00'),
(15, 37, 7, 5, 'For Super Earth!',    'Helldivers 2 is the most fun I''ve had in co-op in years.',                          '2026-03-01 12:00:00'),
(16, 39, 3, 5, 'Builder''s paradise', 'Ultrahand alone is worth the entry fee. Hyrule has never been more open.',           '2026-03-03 10:00:00'),
(17, 42, 5, 4, 'Roster of dreams',    'Smash Ultimate''s only flaw is online netcode. Otherwise perfection.',               '2026-03-05 19:00:00'),
(18, 48, 6, 5, 'Yharnam forever',     'Bloodborne is FromSoft''s most atmospheric work. Please give us a PC port.',         '2026-03-07 02:15:00'),
(19, 50, 4, 5, 'Talk to no one',      'Go in blind. Outer Wilds is one of the best games ever made.',                       '2026-03-09 23:45:00'),
(20, 15, 7, 5, 'Words as weapons',    'Disco Elysium proves an RPG can have zero combat and still be a masterpiece.',       '2026-03-11 14:00:00'),
(21, 6,  5, 5, 'Climb the mountain',  'Celeste''s assist mode is a model for accessibility. Soundtrack is god-tier.',       '2026-03-13 08:30:00'),
(22, 16, 3, 4, 'Goodbye, free time',  'Factorio is the cleanest Skinner box ever made. The factory must grow.',             '2026-03-15 03:00:00'),
(23, 9,  6, 3, 'Better now',          'Cyberpunk has come a long way since launch. 2.0 is the version it always should''ve been.','2026-03-17 16:00:00'),
(24, 11, 4, 5, 'Eternal sandbox',     'Minecraft is still the most generous sandbox ever shipped.',                         '2026-03-19 20:00:00'),
(25, 13, 5, 5, 'Hesitation is defeat','Sekiro''s combat clicks unlike anything else FromSoft has made.',                    '2026-03-21 22:00:00');

-- ── Votes ───────────────────────────────────────────────────────────────────
INSERT INTO votes (id, reviewId, userId, createdAt) VALUES
(1,  1,  1, '2026-05-27 03:45:59'),
(2,  1,  3, '2026-02-02 09:00:00'),
(3,  1,  4, '2026-02-02 11:30:00'),
(4,  1,  5, '2026-02-03 15:20:00'),
(5,  1,  6, '2026-02-04 19:00:00'),
(6,  2,  1, '2026-02-04 10:00:00'),
(7,  2,  3, '2026-02-04 12:00:00'),
(8,  2,  6, '2026-02-05 22:00:00'),
(9,  3,  2, '2026-02-06 11:00:00'),
(10, 3,  4, '2026-02-06 14:30:00'),
(11, 3,  7, '2026-02-07 09:15:00'),
(12, 4,  1, '2026-02-09 08:00:00'),
(13, 4,  2, '2026-02-09 12:45:00'),
(14, 4,  5, '2026-02-10 17:00:00'),
(15, 5,  1, '2026-02-11 10:30:00'),
(16, 5,  6, '2026-02-12 20:00:00'),
(17, 6,  1, '2026-02-13 14:00:00'),
(18, 6,  3, '2026-02-13 16:30:00'),
(19, 6,  4, '2026-02-13 19:00:00'),
(20, 6,  7, '2026-02-14 11:20:00'),
(21, 7,  2, '2026-02-15 09:00:00'),
(22, 7,  4, '2026-02-15 13:00:00'),
(23, 9,  5, '2026-02-18 14:00:00'),
(24, 10, 6, '2026-02-19 21:00:00'),
(25, 10, 7, '2026-02-20 08:00:00'),
(26, 11, 1, '2026-02-21 10:00:00'),
(27, 12, 3, '2026-02-23 17:00:00'),
(28, 13, 4, '2026-02-26 13:00:00'),
(29, 15, 2, '2026-03-02 09:30:00'),
(30, 15, 5, '2026-03-02 14:00:00'),
(31, 15, 6, '2026-03-02 16:00:00'),
(32, 16, 1, '2026-03-04 11:00:00'),
(33, 16, 5, '2026-03-04 15:00:00'),
(34, 18, 2, '2026-03-08 10:00:00'),
(35, 18, 4, '2026-03-08 14:00:00'),
(36, 18, 5, '2026-03-08 18:00:00'),
(37, 19, 1, '2026-03-10 12:00:00'),
(38, 19, 3, '2026-03-10 16:00:00'),
(39, 19, 6, '2026-03-11 09:00:00'),
(40, 20, 1, '2026-03-12 11:00:00'),
(41, 21, 2, '2026-03-14 14:00:00'),
(42, 25, 1, '2026-03-22 10:00:00'),
(43, 25, 2, '2026-03-22 13:00:00'),
(44, 25, 3, '2026-03-22 15:00:00'),
(45, 25, 4, '2026-03-22 18:00:00');

-- ── Reports ─────────────────────────────────────────────────────────────────
INSERT INTO reports (id, reviewId, userId, reason, note, status, createdAt) VALUES
(1, 1, 1, 'Spoilers',            'Gives away a late-game boss.',   'open', '2026-02-06 08:00:00'),
(2, 1, 2, 'Off-topic',           '',                               'open', '2026-02-06 09:30:00'),
(3, 2, 1, 'Spam or advertising', 'Looks like a copy-paste promo.', 'open', '2026-02-07 11:00:00');

-- ── Tier Lists ──────────────────────────────────────────────────────────────
INSERT INTO tierlists (id, userId, title, description, createdAt) VALUES
(1, 2, 'Best Soulslikes Ever',      'From''s greatest hits, ranked.',                        '2026-02-10 09:00:00'),
(3, 1, 'My Tier List',              'admin',                                                 '2026-05-30 11:34:54'),
(4, 2, 'My Tier List',              'demo2',                                                 '2026-05-30 11:54:05'),
(5, 3, 'Open World GOATs',          'The open-world games that actually respect your time.', '2026-03-01 10:00:00'),
(6, 4, '2023 Game of the Year',     'My personal ranking of last year''s biggest hits.',      '2026-03-05 18:30:00'),
(7, 5, 'Nintendo Switch Essentials','If you only own one Switch — start with these.',         '2026-03-10 12:00:00'),
(8, 6, 'Best Multiplayer Games',    'For nights with friends, ranked by chaos potential.',    '2026-03-15 21:00:00'),
(9, 7, 'Cozy Games to Unwind',      'Low stakes, high vibes.',                                '2026-03-20 14:00:00');

-- Tier list items
INSERT INTO tierlist_tiers (tierlistId, tierName, gameId, sortOrder) VALUES
-- Best Soulslikes Ever (1)
(1, 'S', 1,  0),
(1, 'S', 13, 1),
(1, 'S', 48, 2),
(1, 'A', 7,  0),
(1, 'A', 30, 1),
(1, 'B', 17, 0),
-- admin My Tier List (3)
(3, 'S', 13, 0),
(3, 'A', 1,  0),
(3, 'B', 8,  0),
-- demo2 My Tier List (4)
(4, 'S', 2,  0),
(4, 'A', 12, 0),
-- Open World GOATs (5)
(5, 'S', 18, 0),
(5, 'S', 19, 1),
(5, 'S', 39, 2),
(5, 'A', 1,  0),
(5, 'A', 2,  1),
(5, 'A', 21, 2),
(5, 'B', 9,  0),
(5, 'B', 33, 1),
-- 2023 GOTY (6)
(6, 'S', 12, 0),
(6, 'S', 39, 1),
(6, 'A', 24, 0),
(6, 'A', 32, 1),
(6, 'A', 31, 2),
(6, 'B', 20, 0),
(6, 'B', 30, 1),
(6, 'C', 33, 0),
-- Nintendo Switch Essentials (7)
(7, 'S', 18, 0),
(7, 'S', 39, 1),
(7, 'S', 42, 2),
(7, 'A', 41, 0),
(7, 'A', 4,  1),
(7, 'A', 6,  2),
(7, 'B', 40, 0),
(7, 'B', 43, 1),
-- Best Multiplayer (8)
(8, 'S', 37, 0),
(8, 'S', 42, 1),
(8, 'A', 41, 0),
(8, 'A', 14, 1),
(8, 'A', 26, 2),
(8, 'B', 10, 0),
(8, 'B', 27, 1),
(8, 'C', 28, 0),
-- Cozy Games (9)
(9, 'S', 5,  0),
(9, 'S', 40, 1),
(9, 'A', 50, 0),
(9, 'A', 6,  1),
(9, 'B', 11, 0);

-- ── Tier List Reactions ─────────────────────────────────────────────────────
INSERT INTO tierlist_reactions (id, tierlistId, userId, type, createdAt) VALUES
(3,  4, 2, 'like',    '2026-05-30 11:54:08'),
(5,  1, 2, 'like',    '2026-05-30 11:54:11'),
(6,  3, 2, 'like',    '2026-05-30 11:54:14'),
(8,  3, 1, 'dislike', '2026-05-30 11:54:30'),
(9,  1, 1, 'like',    '2026-05-30 11:54:31'),
(10, 4, 1, 'dislike', '2026-05-30 11:54:35'),
(11, 1, 3, 'like',    '2026-03-02 09:00:00'),
(12, 1, 4, 'like',    '2026-03-02 14:00:00'),
(13, 1, 5, 'like',    '2026-03-03 10:00:00'),
(14, 1, 6, 'like',    '2026-03-03 18:00:00'),
(15, 5, 1, 'like',    '2026-03-04 11:00:00'),
(16, 5, 2, 'like',    '2026-03-04 13:00:00'),
(17, 5, 4, 'like',    '2026-03-05 09:00:00'),
(18, 5, 6, 'like',    '2026-03-05 16:00:00'),
(19, 5, 7, 'like',    '2026-03-06 10:00:00'),
(20, 6, 1, 'like',    '2026-03-06 12:00:00'),
(21, 6, 2, 'like',    '2026-03-06 14:00:00'),
(22, 6, 3, 'like',    '2026-03-07 09:00:00'),
(23, 6, 5, 'dislike', '2026-03-07 11:00:00'),
(24, 6, 7, 'like',    '2026-03-07 15:00:00'),
(25, 7, 1, 'like',    '2026-03-11 10:00:00'),
(26, 7, 2, 'like',    '2026-03-11 12:00:00'),
(27, 7, 3, 'like',    '2026-03-11 14:00:00'),
(28, 7, 4, 'like',    '2026-03-12 09:00:00'),
(29, 7, 6, 'like',    '2026-03-12 11:00:00'),
(30, 8, 1, 'like',    '2026-03-16 18:00:00'),
(31, 8, 3, 'like',    '2026-03-16 20:00:00'),
(32, 8, 4, 'like',    '2026-03-17 10:00:00'),
(33, 8, 5, 'like',    '2026-03-17 14:00:00'),
(34, 8, 7, 'dislike', '2026-03-17 19:00:00'),
(35, 9, 1, 'like',    '2026-03-21 09:00:00'),
(36, 9, 2, 'like',    '2026-03-21 11:00:00'),
(37, 9, 5, 'like',    '2026-03-21 14:00:00'),
(38, 9, 6, 'like',    '2026-03-22 10:00:00');

SET FOREIGN_KEY_CHECKS = 1;
