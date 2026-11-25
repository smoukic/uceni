<?php
declare(strict_types=1);

final class Store
{
    private PDO $db;
    public readonly LearningStore $learning;
    public readonly Accounts $accounts;

    public function __construct(string $filename)
    {
        $this->db = new PDO('sqlite:' . $filename);
        $this->db->exec('PRAGMA busy_timeout = 5000; PRAGMA foreign_keys = ON');
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS players (
                id INTEGER PRIMARY KEY,
                handle TEXT COLLATE NOCASE UNIQUE NOT NULL,
                password TEXT NOT NULL,
                school TEXT NOT NULL DEFAULT '',
                team TEXT REFERENCES teams(code)
            );
            CREATE TABLE IF NOT EXISTS teams (code TEXT PRIMARY KEY);
            CREATE TABLE IF NOT EXISTS medals (
                player INTEGER NOT NULL REFERENCES players(id),
                level INTEGER NOT NULL,
                xp INTEGER NOT NULL,
                PRIMARY KEY (player, level)
            );
            CREATE TABLE IF NOT EXISTS word_medals (
                player INTEGER NOT NULL REFERENCES players(id),
                level INTEGER NOT NULL,
                xp INTEGER NOT NULL,
                PRIMARY KEY (player, level)
            );
            CREATE TABLE IF NOT EXISTS login_limits (
                handle TEXT PRIMARY KEY COLLATE NOCASE,
                attempts INTEGER NOT NULL,
                until_at INTEGER NOT NULL
            );
        ");
        $this->learning = new LearningStore($this->db);
        $this->accounts = new Accounts($this->db);
    }

    public function recovery(): Recovery
    {
        return new Recovery($this->db, $this->learning, RecoveryMail::send(...));
    }

    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function player(int $id): ?array
    {
        return $this->query('SELECT id, handle, school, team FROM players WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function register(string $handle, string $password, int $grade): ?int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->learning->transaction(function () use ($handle, $hash, $grade): ?int {
            $statement = $this->query('INSERT INTO players (handle, password) VALUES (?, ?) ON CONFLICT(handle) DO NOTHING',
                [$handle, $hash]);
            if (!$statement->rowCount()) {
                return null;
            }
            $id = (int) $this->db->lastInsertId();
            if (!$this->accounts->initializeGrade($id, $grade)) {
                throw new LogicException('Invalid grade during registration');
            }
            return $id;
        });
    }

    public function login(string $handle, string $password): ?int
    {
        // Reserve an attempt before doing the expensive hash check.
        $now = time();
        $attempt = $this->query('INSERT INTO login_limits VALUES (?, 1, ?)
            ON CONFLICT(handle) DO UPDATE SET
                attempts = CASE WHEN until_at <= ? THEN 1 ELSE attempts + 1 END,
                until_at = CASE WHEN until_at <= ? THEN excluded.until_at ELSE until_at END
            WHERE until_at <= ? OR attempts < 8 RETURNING attempts',
            [$handle, $now + 300, $now, $now, $now]);
        $allowed = $attempt->fetchColumn();
        $attempt->closeCursor();
        if ($allowed === false) {
            return null;
        }
        $row = $this->query('SELECT id, password FROM players WHERE handle = ?', [$handle])->fetch(PDO::FETCH_ASSOC);
        $valid = password_verify($password, $row['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
        if (!$valid || !$row) {
            return null;
        }
        $this->query('DELETE FROM login_limits WHERE handle = ?', [$handle]);
        return (int) $row['id'];
    }

    public function progress(int $player, bool $wordsMode = false): array
    {
        $table = $wordsMode ? 'word_medals' : 'medals';
        return $this->query("SELECT level, xp FROM $table WHERE player = ? ORDER BY level", [$player])
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function award(int $player, int $level, int $xp, bool $wordsMode = false): void
    {
        $this->learning->awardCzech($player, $level, $xp, $wordsMode);
    }

    public function school(int $player, string $school): void
    {
        $this->query('UPDATE players SET school = ? WHERE id = ?', [$school, $player]);
    }

    public function team(int $player, ?string $code): bool
    {
        if ($code !== null && !$this->query('SELECT 1 FROM teams WHERE code = ?', [$code])->fetchColumn()) {
            return false;
        }
        $this->query('UPDATE players SET team = ? WHERE id = ?', [$code, $player]);
        return true;
    }

    public function createTeam(int $player): void
    {
        $this->db->beginTransaction();
        try {
            do {
                $code = strtoupper(bin2hex(random_bytes(5)));
                $insert = $this->query('INSERT INTO teams VALUES (?) ON CONFLICT(code) DO NOTHING', [$code]);
            } while (!$insert->rowCount());
            $this->team($player, $code);
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function rankings(string $code, bool $wordsMode = false): array
    {
        $table = $wordsMode ? 'word_medals' : 'medals';
        return $this->query("SELECT handle, COALESCE(SUM(xp), 0) AS score
            FROM players LEFT JOIN $table ON player = players.id
            WHERE team = ? GROUP BY players.id ORDER BY score DESC, handle LIMIT 50", [$code])->fetchAll(PDO::FETCH_ASSOC);
    }
}
