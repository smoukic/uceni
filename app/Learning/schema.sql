CREATE TABLE IF NOT EXISTS achievement_events (
    player INTEGER NOT NULL REFERENCES players(id),
    source TEXT NOT NULL,
    xp INTEGER NOT NULL CHECK (xp > 0),
    day TEXT,
    PRIMARY KEY (player, source)
);
CREATE INDEX IF NOT EXISTS achievement_days ON achievement_events (player, day);
CREATE TABLE IF NOT EXISTS wallets (
    player INTEGER PRIMARY KEY REFERENCES players(id),
    balance INTEGER NOT NULL CHECK (balance >= 0),
    remainder INTEGER NOT NULL CHECK (remainder >= 0)
);
CREATE TABLE IF NOT EXISTS coin_ledger (
    id INTEGER PRIMARY KEY,
    player INTEGER NOT NULL REFERENCES players(id),
    source TEXT NOT NULL,
    label TEXT NOT NULL,
    amount INTEGER NOT NULL CHECK (amount <> 0),
    created TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (player, source)
);
CREATE TABLE IF NOT EXISTS course_results (
    player INTEGER NOT NULL REFERENCES players(id),
    course TEXT NOT NULL,
    task TEXT NOT NULL,
    score INTEGER NOT NULL CHECK (score BETWEEN 0 AND 100),
    attempts INTEGER NOT NULL,
    updated TEXT NOT NULL,
    PRIMARY KEY (player, course, task)
);
CREATE TABLE IF NOT EXISTS review_items (
    player INTEGER NOT NULL REFERENCES players(id),
    course TEXT NOT NULL,
    item TEXT NOT NULL,
    due TEXT NOT NULL,
    stage INTEGER NOT NULL CHECK (stage IN (0, 1)),
    PRIMARY KEY (player, course, item)
);
CREATE TABLE IF NOT EXISTS guardians (
    id INTEGER PRIMARY KEY,
    handle TEXT UNIQUE COLLATE NOCASE NOT NULL,
    password TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS guardian_invites (
    code_hash TEXT PRIMARY KEY,
    guardian INTEGER NOT NULL REFERENCES guardians(id),
    expires INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS guardian_links (
    guardian INTEGER NOT NULL REFERENCES guardians(id),
    child INTEGER NOT NULL UNIQUE REFERENCES players(id),
    PRIMARY KEY (guardian, child)
);
CREATE TABLE IF NOT EXISTS guardian_rewards (
    id INTEGER PRIMARY KEY,
    guardian INTEGER NOT NULL,
    child INTEGER NOT NULL,
    title TEXT NOT NULL,
    cost INTEGER NOT NULL CHECK (cost > 0),
    active INTEGER NOT NULL CHECK (active IN (0, 1)),
    FOREIGN KEY (guardian, child) REFERENCES guardian_links (guardian, child)
);
CREATE TABLE IF NOT EXISTS reward_claims (
    id INTEGER PRIMARY KEY,
    reward INTEGER NOT NULL REFERENCES guardian_rewards(id),
    request_key TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    cost INTEGER NOT NULL CHECK (cost > 0),
    status TEXT NOT NULL CHECK (status IN ('pending', 'approved', 'rejected', 'fulfilled')),
    created TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS one_open_claim ON reward_claims (reward) WHERE status IN ('pending', 'approved');
CREATE TABLE IF NOT EXISTS cosmetics (
    player INTEGER NOT NULL REFERENCES players(id),
    item TEXT NOT NULL,
    PRIMARY KEY (player, item)
);
CREATE TABLE IF NOT EXISTS equipped_cosmetics (
    player INTEGER NOT NULL,
    category TEXT NOT NULL,
    item TEXT NOT NULL,
    PRIMARY KEY (player, category),
    FOREIGN KEY (player, item) REFERENCES cosmetics (player, item)
);
CREATE TABLE IF NOT EXISTS guardian_activation (
    code_hash TEXT PRIMARY KEY,
    expires INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS course_attempts (
    id TEXT PRIMARY KEY,
    player INTEGER NOT NULL REFERENCES players(id),
    course TEXT NOT NULL,
    task TEXT NOT NULL,
    score INTEGER NOT NULL CHECK (score BETWEEN 0 AND 100),
    day TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS course_attempt_player ON course_attempts (player);

CREATE TABLE IF NOT EXISTS learner_settings (
    player INTEGER PRIMARY KEY REFERENCES players(id),
    grade INTEGER NOT NULL CHECK (grade BETWEEN 1 AND 9),
    ceiling INTEGER NOT NULL CHECK (ceiling BETWEEN grade AND 9)
);
CREATE TABLE IF NOT EXISTS remembered_logins (
    selector TEXT PRIMARY KEY,
    player INTEGER NOT NULL REFERENCES players(id),
    digest TEXT NOT NULL,
    expires INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS remembered_logins_player ON remembered_logins (player);
CREATE TABLE IF NOT EXISTS password_epochs (
    player INTEGER PRIMARY KEY REFERENCES players(id),
    epoch INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS operators (
    guardian INTEGER PRIMARY KEY REFERENCES guardians(id)
);
CREATE TABLE IF NOT EXISTS catalog_overrides (
    item TEXT PRIMARY KEY,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    value TEXT NOT NULL,
    cost INTEGER NOT NULL CHECK (cost > 0),
    active INTEGER NOT NULL CHECK (active IN (0, 1))
);
CREATE TABLE IF NOT EXISTS study_sessions (
    attempt TEXT PRIMARY KEY,
    player INTEGER NOT NULL REFERENCES players(id),
    course TEXT NOT NULL,
    task TEXT NOT NULL,
    day TEXT NOT NULL,
    seconds INTEGER NOT NULL CHECK (seconds BETWEEN 0 AND 7200),
    score INTEGER NOT NULL CHECK (score BETWEEN 0 AND 100),
    answered INTEGER NOT NULL CHECK (answered > 0),
    completed INTEGER NOT NULL CHECK (completed IN (0,1))
);
CREATE INDEX IF NOT EXISTS study_sessions_player ON study_sessions (player, day);
CREATE TABLE IF NOT EXISTS suspended_xp (
    player INTEGER NOT NULL,
    source TEXT NOT NULL,
    PRIMARY KEY (player, source),
    FOREIGN KEY (player, source) REFERENCES achievement_events(player, source)
);

CREATE TABLE IF NOT EXISTS guardian_password_epochs (
    guardian INTEGER PRIMARY KEY REFERENCES guardians(id),
    epoch INTEGER NOT NULL
);
