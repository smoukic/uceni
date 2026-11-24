<?php
declare(strict_types=1);

final class LearningStore
{
    private array $config;
    private const SCHEMA_VERSION = 2;

    public function __construct(private PDO $db)
    {
        $this->config = require __DIR__ . '/../Content/rewards.php';
        if ((int) $db->query('PRAGMA user_version')->fetchColumn() < self::SCHEMA_VERSION) {
            $this->transaction(function () use ($db): void {
                $db->exec(file_get_contents(__DIR__ . '/schema.sql'));
                $db->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            });
        }
    }

    private function query(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function transaction(callable $work): mixed
    {
        // Take SQLite's write lock before any balance or progress read.
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result = $work();
            $this->db->exec('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    }

    private function initialize(int $player): void
    {
        if ($this->query('SELECT 1 FROM wallets WHERE player = ?', [$player])->fetchColumn()) {
            return;
        }
        foreach (['medals' => 'order', 'word_medals' => 'words'] as $table => $mode) {
            $this->query("INSERT INTO achievement_events (player, source, xp)
                SELECT player, 'cestina:' || ? || ':' || level, xp FROM $table
                WHERE player = ? AND xp > 0 ON CONFLICT DO NOTHING", [$mode, $player]);
        }
        $xp = $this->xp($player);
        $rate = max(1, (int) $this->config['xpPerCoin']);
        $coins = intdiv($xp, $rate);
        $this->query('INSERT INTO wallets VALUES (?, ?, ?)', [$player, $coins, $xp % $rate]);
        if ($coins > 0) {
            $this->query('INSERT INTO coin_ledger (player, source, label, amount) VALUES (?, ?, ?, ?)',
                [$player, 'legacy', 'Mince za dosavadní pokrok', $coins]);
        }
    }

    private function xp(int $player): int
    {
        return (int) $this->query('SELECT COALESCE(SUM(xp), 0) FROM achievement_events a WHERE player = ?
            AND NOT EXISTS (SELECT 1 FROM suspended_xp s WHERE s.player = a.player AND s.source = a.source)', [$player])->fetchColumn();
    }

    private function postCoins(int $player, string $source, string $label, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }
        if ($this->query('SELECT 1 FROM coin_ledger WHERE player = ? AND source = ?', [$player, $source])->fetchColumn()) {
            return false;
        }
        $updated = $this->query('UPDATE wallets SET balance = balance + ? WHERE player = ? AND balance + ? >= 0',
            [$delta, $player, $delta]);
        if (!$updated->rowCount()) {
            return false;
        }
        $this->query('INSERT INTO coin_ledger (player, source, label, amount) VALUES (?, ?, ?, ?)',
            [$player, $source, $label, $delta]);
        return true;
    }

    private function grant(int $player, string $source, int $xp, string $label): bool
    {
        $day = $this->today()->format('Y-m-d');
        $added = $this->query('INSERT INTO achievement_events VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING',
            [$player, $source, $xp, $day]);
        if (!$added->rowCount()) {
            return $this->query('DELETE FROM suspended_xp WHERE player = ? AND source = ?', [$player, $source])->rowCount() > 0;
        }
        $rate = max(1, (int) $this->config['xpPerCoin']);
        $remainder = (int) $this->query('SELECT remainder FROM wallets WHERE player = ?', [$player])->fetchColumn();
        $available = $remainder + $xp;
        $this->query('UPDATE wallets SET remainder = ? WHERE player = ?', [$available % $rate, $player]);
        $this->postCoins($player, 'xp:' . $source, $label, intdiv($available, $rate));
        return true;
    }

    public function awardCzech(int $player, int $level, int $xp, bool $wordsMode): void
    {
        $this->transaction(function () use ($player, $level, $xp, $wordsMode): void {
            $this->initialize($player);
            $table = $wordsMode ? 'word_medals' : 'medals';
            $mode = $wordsMode ? 'words' : 'order';
            $added = $this->query("INSERT INTO $table VALUES (?, ?, ?) ON CONFLICT DO NOTHING", [$player, $level, $xp]);
            if ($added->rowCount()) {
                $this->grant($player, "cestina:$mode:$level", $xp, 'Dokončená česká mise');
                $done = (int) $this->query("SELECT COUNT(*) FROM $table WHERE player = ?", [$player])->fetchColumn();
                if ($done >= 7) {
                    $this->grant($player, "bonus:cestina:$mode", 100, 'Dokončená česká výzva');
                }
                $this->awardChallenges($player);
            }
        });
    }

    public function results(int $player, string $course): array
    {
        return $this->query('SELECT task, score FROM course_results WHERE player = ? AND course = ?',
            [$player, $course])->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function recordResult(int $player, array $course, string $task, int $score, string $attempt): int
    {
        return $this->transaction(function () use ($player, $course, $task, $score, $attempt): int {
            $id = $course['id'];
            $day = $this->today()->format('Y-m-d');
            $added = $this->query('INSERT INTO course_attempts VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING',
                [$attempt, $player, $id, $task, $score, $day]);
            if (!$added->rowCount()) {
                return 0;
            }
            $this->query('INSERT INTO course_results VALUES (?, ?, ?, ?, 1, ?)
                ON CONFLICT(player, course, task) DO UPDATE SET score = MAX(score, excluded.score),
                attempts = attempts + 1, updated = excluded.updated',
                [$player, $id, $task, $score, $day]);
            if ($score < 80) {
                return 0;
            }
            $this->initialize($player);
            $previousXp = $this->xp($player);
            $mission = str_starts_with($task, 'mission:');
            $earned = $this->grant($player, "course:$id:$task", $mission ? 100 : 30,
                $mission ? 'Zvládnutá mise' : 'Zvládnutá lekce nebo test');
            if ($mission) {
                if ($score === 100) {
                    $this->grant($player, "bonus:$id:$task", 30, 'Bezchybný test mise');
                }
                $results = $this->results($player, $id);
                $topic = $course['missions'][substr($task, strlen('mission:'))]['topic'];
                [$title, $missions] = $course['topics'][$topic];
                $mastered = array_filter($missions, static fn(string $name): bool => ($results['mission:' . $name] ?? 0) >= 80);
                if (count($mastered) === count($missions)) {
                    $this->grant($player, "bonus:$id:topic:$topic", 50, 'Dokončený tematický okruh: ' . $title);
                }
            }
            if ($task === 'exam') {
                $this->grant($player, "bonus:$id:grade", 150, 'Dokončený ročník');
            }
            if ($earned) {
                $this->awardChallenges($player);
            }
            return $this->xp($player) - $previousXp;
        });
    }

    public function recentAttempts(int $player): array
    {
        return $this->query('SELECT course, task, score, day FROM course_attempts WHERE player = ? ORDER BY rowid DESC LIMIT 30',
            [$player])->fetchAll(PDO::FETCH_ASSOC);
    }

    private function challengeCounts(int $player): array
    {
        $today = $this->today();
        $day = $today->format('Y-m-d');
        $weekStart = $today->modify('monday this week')->format('Y-m-d');
        $row = $this->query("SELECT COUNT(*) AS weekly, COUNT(DISTINCT day) AS days,
            COALESCE(SUM(day = ?), 0) AS daily FROM achievement_events
            WHERE player = ? AND day BETWEEN ? AND ? AND (source LIKE 'course:%' OR source LIKE 'cestina:%')",
            [$day, $player, $weekStart, $day])->fetch(PDO::FETCH_ASSOC);
        return $row + compact('day', 'weekStart');
    }

    private function awardChallenges(int $player): void
    {
        $counts = $this->challengeCounts($player);
        if ($counts['daily'] >= 1) {
            $this->grant($player, 'challenge:day:' . $counts['day'], 10, 'Denní výzva za učivo');
        }
        if ($counts['weekly'] >= 4 && $counts['days'] >= 3) {
            $this->grant($player, 'challenge:week:' . $counts['weekStart'], 40, 'Týdenní výzva');
        }
    }

    public function reviewAnswer(int $player, string $course, string $item, bool $correct, bool $review): void
    {
        $today = $this->today();
        $day = $today->format('Y-m-d');
        if (!$correct) {
            $this->query('INSERT INTO review_items VALUES (?, ?, ?, ?, 0)
                ON CONFLICT (player, course, item) DO UPDATE SET due = excluded.due, stage = 0',
                [$player, $course, $item, $day]);
        } elseif ($review) {
            $this->transaction(function () use ($player, $course, $item, $day, $today): void {
                $where = [$player, $course, $item, $day];
                $this->query('DELETE FROM review_items WHERE player = ? AND course = ? AND item = ? AND due <= ? AND stage = 1', $where);
                $this->query('UPDATE review_items SET stage = 1, due = ? WHERE player = ? AND course = ? AND item = ? AND due <= ? AND stage = 0',
                    [$today->modify('+1 day')->format('Y-m-d'), ...$where]);
            });
        }
    }

    public function due(int $player, string $course): array
    {
        return $this->query('SELECT item FROM review_items WHERE player = ? AND course = ? AND due <= ? ORDER BY due, item',
            [$player, $course, $this->today()->format('Y-m-d')])->fetchAll(PDO::FETCH_COLUMN);
    }

    public function summary(int $player): array
    {
        if (!$this->query('SELECT 1 FROM wallets WHERE player = ?', [$player])->fetchColumn()) {
            $this->transaction(fn() => $this->initialize($player));
        }
        $xp = $this->xp($player);
        $level = 1 + intdiv($xp, 200);
        $title = 'Průzkumník';
        foreach ($this->config['titles'] as $threshold => $name) {
            if ($level >= $threshold) {
                $title = $name;
            }
        }
        $balance = (int) $this->query('SELECT balance FROM wallets WHERE player = ?', [$player])->fetchColumn();
        $days = $this->query('SELECT DISTINCT day FROM achievement_events WHERE player = ? AND day IS NOT NULL ORDER BY day', [$player])
            ->fetchAll(PDO::FETCH_COLUMN);
        $current = $best = 0;
        $last = null;
        foreach ($days as $date) {
            $day = new DateTimeImmutable($date, $this->today()->getTimezone());
            $current = $last && $last->diff($day)->days <= 2 ? $current + 1 : 1;
            $best = max($best, $current);
            $last = $day;
        }
        if (!$last || $last->diff($this->today())->days > 2) {
            $current = 0;
        }
        $badges = [];
        foreach ([100 => 'První stovka', 500 => 'Pět set zkušeností', 1000 => 'Tisíc zkušeností'] as $threshold => $badge) {
            if ($xp >= $threshold) {
                $badges[] = $badge;
            }
        }
        if ($best >= 5) {
            $badges[] = 'Pět studijních dnů v sérii';
        }
        if ($this->query("SELECT 1 FROM achievement_events WHERE player = ? AND source LIKE 'bonus:%:grade' LIMIT 1", [$player])->fetchColumn()) {
            $badges[] = 'Dokončený ročník';
        }
        $record = (int) $this->query('SELECT COALESCE(MAX(total), 0) FROM
            (SELECT SUM(xp) AS total FROM achievement_events WHERE player = ? AND day IS NOT NULL GROUP BY day)',
            [$player])->fetchColumn();
        $challenges = $this->challengeCounts($player);
        return compact('xp', 'title', 'level', 'balance', 'current', 'best', 'badges', 'record', 'challenges');
    }

    public function earnedHistory(int $player): array
    {
        return $this->query('SELECT source, xp, day FROM achievement_events WHERE player = ?
            ORDER BY day DESC, source LIMIT 50', [$player])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function courseHistory(int $player): array
    {
        return $this->query('SELECT course, task, score, attempts, updated FROM course_results WHERE player = ?
            ORDER BY updated DESC, course, task', [$player])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function coinHistory(int $player): array
    {
        return $this->query('SELECT label, amount, created FROM coin_ledger WHERE player = ? ORDER BY id DESC LIMIT 50', [$player])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cosmetics(): array
    {
        $items = array_map(static fn(array $item): array => [...$item, true], $this->config['cosmetics']);
        foreach ($this->query('SELECT * FROM catalog_overrides ORDER BY item')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[$row['item']] = [$row['category'], $row['title'], $row['value'], (int) $row['cost'], (bool) $row['active']];
        }
        return $items;
    }

    public function owned(int $player): array
    {
        return $this->query('SELECT item FROM cosmetics WHERE player = ?', [$player])->fetchAll(PDO::FETCH_COLUMN);
    }

    public function equipped(int $player): array
    {
        return $this->query('SELECT category, item FROM equipped_cosmetics WHERE player = ?', [$player])->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function buy(int $player, string $item): bool
    {
        return $this->transaction(function () use ($player, $item): bool {
            $product = $this->cosmetics()[$item] ?? null;
            if (!$product || !$product[4]) {
                return false;
            }
            $this->initialize($player);
            if (!$this->postCoins($player, "cosmetic:$item", $product[1], -$product[3])) {
                return false;
            }
            $this->query('INSERT INTO cosmetics VALUES (?, ?)', [$player, $item]);
            return true;
        });
    }

    public function equip(int $player, string $item, bool $remove): bool
    {
        $category = $this->cosmetics()[$item][0] ?? null;
        if ($category === null || !in_array($item, $this->owned($player), true)) {
            return false;
        }
        if ($remove) {
            $this->query('DELETE FROM equipped_cosmetics WHERE player = ? AND category = ? AND item = ?', [$player, $category, $item]);
        } else {
            $this->query('INSERT INTO equipped_cosmetics VALUES (?, ?, ?)
                ON CONFLICT(player, category) DO UPDATE SET item = excluded.item', [$player, $category, $item]);
        }
        return true;
    }

    public function guardianActivation(): string
    {
        $code = bin2hex(random_bytes(16));
        $this->query('DELETE FROM guardian_activation WHERE expires < ?', [time()]);
        $this->query('INSERT INTO guardian_activation VALUES (?, ?)', [hash('sha256', $code), time() + 86400]);
        return $code;
    }

    public function registerGuardian(string $handle, string $password, string $code): ?int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->transaction(function () use ($handle, $hash, $code): ?int {
            $key = hash('sha256', strtolower(trim($code)));
            if (!$this->query('SELECT 1 FROM guardian_activation WHERE code_hash = ? AND expires >= ?', [$key, time()])->fetchColumn()) {
                return null;
            }
            $added = $this->query('INSERT INTO guardians (handle, password) VALUES (?, ?) ON CONFLICT DO NOTHING', [$handle, $hash]);
            if (!$added->rowCount()) {
                return null;
            }
            $id = (int) $this->db->lastInsertId();
            $this->query('DELETE FROM guardian_activation WHERE code_hash = ?', [$key]);
            return $id;
        });
    }

    public function loginGuardian(string $handle, string $password): ?int
    {
        $key = 'guardian:' . $handle;
        $now = time();
        $attempt = $this->query('INSERT INTO login_limits VALUES (?, 1, ?)
            ON CONFLICT(handle) DO UPDATE SET
                attempts = CASE WHEN until_at <= ? THEN 1 ELSE attempts + 1 END,
                until_at = CASE WHEN until_at <= ? THEN excluded.until_at ELSE until_at END
            WHERE until_at <= ? OR attempts < 8 RETURNING attempts',
            [$key, $now + 300, $now, $now, $now]);
        $allowed = $attempt->fetchColumn();
        $attempt->closeCursor();
        if ($allowed === false) {
            return null;
        }
        $row = $this->query('SELECT id, password FROM guardians WHERE handle = ?', [$handle])->fetch(PDO::FETCH_ASSOC);
        $valid = password_verify($password, $row['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oPtgecTsM.ZD3b7kF7yo8IbuiW/mUi');
        if (!$valid || !$row) {
            return null;
        }
        $this->query('DELETE FROM login_limits WHERE handle = ?', [$key]);
        return (int) $row['id'];
    }

    public function guardianEpoch(int $guardian): int
    {
        return (int) $this->query('SELECT epoch FROM guardian_password_epochs WHERE guardian = ?', [$guardian])->fetchColumn();
    }

    public function changeGuardianPassword(int $guardian, string $current, string $next): bool
    {
        if (strlen($next) < 12 || strlen($next) > 72 || str_contains($next, "\0")
            || strlen($current) > 72 || str_contains($current, "\0")) {
            return false;
        }
        $old = $this->query('SELECT password FROM guardians WHERE id = ?', [$guardian])->fetchColumn();
        if (!$old || !password_verify($current, $old)) {
            return false;
        }
        $hash = password_hash($next, PASSWORD_DEFAULT);
        return $this->transaction(function () use ($guardian, $hash, $old): bool {
            if (!$this->query('UPDATE guardians SET password = ? WHERE id = ? AND password = ?', [$hash, $guardian, $old])->rowCount()) {
                return false;
            }
            $this->query('INSERT INTO guardian_password_epochs VALUES (?, 1)
                ON CONFLICT(guardian) DO UPDATE SET epoch = epoch + 1', [$guardian]);
            return true;
        });
    }

    public function guardianInvite(int $guardian): string
    {
        $code = bin2hex(random_bytes(12));
        $this->transaction(function () use ($guardian, $code): void {
            $this->query('DELETE FROM guardian_invites WHERE guardian = ? OR expires < ?', [$guardian, time()]);
            $this->query('INSERT INTO guardian_invites VALUES (?, ?, ?)', [hash('sha256', $code), $guardian, time() + 900]);
        });
        return $code;
    }

    public function linkChild(int $child, string $code): bool
    {
        return $this->transaction(function () use ($child, $code): bool {
            $key = hash('sha256', strtolower(trim($code)));
            $guardian = $this->query('SELECT guardian FROM guardian_invites WHERE code_hash = ? AND expires >= ?', [$key, time()])->fetchColumn();
            if (!$guardian || !$this->query('INSERT INTO guardian_links VALUES (?, ?) ON CONFLICT DO NOTHING', [$guardian, $child])->rowCount()) {
                return false;
            }
            $this->query('DELETE FROM guardian_invites WHERE code_hash = ?', [$key]);
            return true;
        });
    }

    public function children(int $guardian): array
    {
        return $this->query('SELECT p.id, p.handle FROM guardian_links g JOIN players p ON p.id = g.child
            WHERE g.guardian = ? ORDER BY p.handle', [$guardian])->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function linked(int $player): bool
    {
        return (bool) $this->query('SELECT 1 FROM guardian_links WHERE child = ?', [$player])->fetchColumn();
    }

    public function childRewards(int $player): array
    {
        return $this->query("SELECT r.*, EXISTS (
            SELECT 1 FROM reward_claims c WHERE c.reward = r.id AND c.status IN ('pending', 'approved')) AS open
            FROM guardian_rewards r WHERE child = ? AND active = 1 ORDER BY id", [$player])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardianRewards(int $guardian, int $child): array
    {
        return $this->query('SELECT * FROM guardian_rewards WHERE guardian = ? AND child = ? ORDER BY id', [$guardian, $child])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveReward(int $guardian, int $child, int $id, string $title, int $cost, bool $active): bool
    {
        if (!isset($this->children($guardian)[$child])) {
            return false;
        }
        if ($id === 0) {
            $this->query('INSERT INTO guardian_rewards (guardian, child, title, cost, active) VALUES (?, ?, ?, ?, ?)',
                [$guardian, $child, $title, $cost, (int) $active]);
            return true;
        }
        return $this->query('UPDATE guardian_rewards SET title = ?, cost = ?, active = ? WHERE id = ? AND guardian = ? AND child = ?',
            [$title, $cost, (int) $active, $id, $guardian, $child])->rowCount() > 0;
    }

    public function claim(int $player, int $reward, string $request): bool
    {
        return $this->transaction(function () use ($player, $reward, $request): bool {
            $this->initialize($player);
            $product = $this->query('SELECT title, cost FROM guardian_rewards WHERE id = ? AND child = ? AND active = 1',
                [$reward, $player])->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                return false;
            }
            $added = $this->query("INSERT INTO reward_claims (reward, request_key, title, cost, status)
                SELECT ?, ?, ?, ?, 'pending' FROM wallets WHERE player = ? AND balance >= ? ON CONFLICT DO NOTHING",
                [$reward, $request, $product['title'], $product['cost'], $player, $product['cost']]);
            if (!$added->rowCount()) {
                return false;
            }
            $id = $this->db->lastInsertId();
            if (!$this->postCoins($player, "claim:$id", 'Rezervace: ' . $product['title'], -(int) $product['cost'])) {
                throw new LogicException('Could not reserve coins for reward claim');
            }
            return true;
        });
    }

    public function claims(int $child, ?int $guardian = null): array
    {
        $sql = 'SELECT c.* FROM reward_claims c JOIN guardian_rewards r ON r.id = c.reward WHERE r.child = ?';
        $parameters = [$child];
        if ($guardian !== null) {
            $sql .= ' AND r.guardian = ?';
            $parameters[] = $guardian;
        }
        return $this->query($sql . ' ORDER BY c.id DESC LIMIT 50', $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function changeClaim(int $guardian, int $id, string $action): bool
    {
        $from = ['approved' => 'pending', 'rejected' => 'pending', 'fulfilled' => 'approved'][$action] ?? null;
        if ($from === null) {
            return false;
        }
        return $this->transaction(function () use ($guardian, $id, $action, $from): bool {
            $claim = $this->query('SELECT c.status, c.cost, c.title, r.child FROM reward_claims c
                JOIN guardian_rewards r ON r.id = c.reward WHERE c.id = ? AND r.guardian = ?', [$id, $guardian])->fetch(PDO::FETCH_ASSOC);
            if (!$claim || ($claim['status'] !== $from && !($action === 'rejected' && $claim['status'] === 'approved'))) {
                return false;
            }
            $this->query('UPDATE reward_claims SET status = ?, updated = CURRENT_TIMESTAMP WHERE id = ?', [$action, $id]);
            if ($action === 'rejected' && !$this->postCoins((int) $claim['child'], "refund:$id", 'Vráceno: ' . $claim['title'], (int) $claim['cost'])) {
                throw new LogicException('Could not release reserved coins');
            }
            return true;
        });
    }
    public function visit(int $player): void
    {
        $day = $this->today()->format('Y-m-d');
        $source = 'visit:' . $day;
        if ($this->query('SELECT 1 FROM achievement_events WHERE player = ? AND source = ?', [$player, $source])->fetchColumn()) {
            return;
        }
        $this->transaction(function () use ($player, $source): void {
            $this->initialize($player);
            $this->grant($player, $source, 5, 'Denní návštěva');
        });
    }

    public function study(string $attempt, int $player, string $course, string $task, int $seconds, int $score, int $answered, bool $completed): void
    {
        $this->query('INSERT INTO study_sessions VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(attempt) DO UPDATE SET seconds = excluded.seconds, score = excluded.score,
            answered = excluded.answered, completed = excluded.completed
            WHERE excluded.answered >= study_sessions.answered AND study_sessions.completed = 0',
            [$attempt, $player, $course, $task, $this->today()->format('Y-m-d'), $seconds, $score, $answered, (int) $completed]);
    }

    public function activity(int $player): array
    {
        $start = $this->today()->modify('-13 days')->format('Y-m-d');
        $recent = $this->query('SELECT * FROM study_sessions WHERE player = ? ORDER BY day DESC, rowid DESC LIMIT 50',
            [$player])->fetchAll(PDO::FETCH_ASSOC);
        $days = $this->query('SELECT day, SUM(seconds) AS seconds, SUM(answered) AS answers,
            ROUND(AVG(score)) AS score FROM study_sessions WHERE player = ? AND day >= ? GROUP BY day ORDER BY day',
            [$player, $start])->fetchAll(PDO::FETCH_ASSOC);
        $totals = $this->query('SELECT COUNT(DISTINCT day) AS days, COALESCE(SUM(seconds), 0) AS seconds,
            COALESCE(SUM(answered), 0) AS answers FROM study_sessions WHERE player = ?', [$player])->fetch(PDO::FETCH_ASSOC);
        return compact('recent', 'days', 'totals');
    }

    public function missionResetPreview(int $player, array $course, string $mission): ?array
    {
        $id = $course['id'];
        $results = $this->results($player, $id);
        $entry = $course['missions'][$mission];
        $tasks = ['mission:' . $mission];
        foreach (array_keys($entry['lessons']) as $lesson) {
            $tasks[] = 'lesson:' . $lesson;
        }
        if (!array_intersect($tasks, array_keys($results))) {
            return null;
        }
        $exam = $mission !== 'diktat' && ($results['mission:' . $mission] ?? 0) >= 80 && isset($results['exam']);
        if ($exam) {
            $tasks[] = 'exam';
        }
        $sources = array_map(static fn(string $task): string => "course:$id:$task", $tasks);
        $sources[] = "bonus:$id:mission:$mission";
        $sources[] = "bonus:$id:topic:" . $entry['topic'];
        if ($exam) {
            $sources[] = "bonus:$id:grade";
        }
        $placeholders = implode(',', array_fill(0, count($sources), '?'));
        $parameter = [$player, ...$sources];
        $rows = $this->query("SELECT a.source, a.xp FROM achievement_events a WHERE a.player = ?
            AND a.source IN ($placeholders) AND NOT EXISTS
            (SELECT 1 FROM suspended_xp s WHERE s.player = a.player AND s.source = a.source) ORDER BY a.source",
            $parameter)->fetchAll(PDO::FETCH_KEY_PAIR);
        $xp = array_sum($rows);
        $revision = $this->query('SELECT COALESCE(MAX(rowid), 0) FROM course_attempts WHERE player = ? AND course = ?',
            [$player, $id])->fetchColumn();
        $key = hash('sha256', json_encode([$id, $mission, $revision, $rows], JSON_THROW_ON_ERROR));
        return compact('xp', 'exam', 'key', 'tasks') + ['sources' => array_keys($rows)];
    }

    public function resetMission(int $player, array $course, string $mission, string $expected): bool
    {
        return $this->transaction(function () use ($player, $course, $mission, $expected): bool {
            $preview = $this->missionResetPreview($player, $course, $mission);
            if (!$preview || !hash_equals($preview['key'], $expected)) {
                return false;
            }
            foreach ($preview['sources'] as $source) {
                $this->query('INSERT INTO suspended_xp VALUES (?, ?) ON CONFLICT DO NOTHING', [$player, $source]);
            }
            foreach ($preview['tasks'] as $task) {
                $this->query('DELETE FROM course_results WHERE player = ? AND course = ? AND task = ?', [$player, $course['id'], $task]);
            }
            return true;
        });
    }

    public function resetCzech(int $player, int $level, bool $words, string $expected): bool
    {
        return $this->transaction(function () use ($player, $level, $words, $expected): bool {
            $table = $words ? 'word_medals' : 'medals';
            $mode = $words ? 'words' : 'order';
            $this->initialize($player);
            $current = $this->query("SELECT xp FROM $table WHERE player = ? AND level = ?", [$player, $level])->fetchColumn();
            if ($current === false || (string) $current !== $expected) {
                return false;
            }
            $this->query('INSERT INTO suspended_xp VALUES (?, ?) ON CONFLICT DO NOTHING', [$player, "cestina:$mode:$level"]);
            $this->query("DELETE FROM $table WHERE player = ? AND level = ?", [$player, $level]);
            return true;
        });
    }

    public function operator(int $guardian): bool
    {
        return (bool) $this->query('SELECT 1 FROM operators WHERE guardian = ?', [$guardian])->fetchColumn();
    }

    public function grantOperator(string $handle): bool
    {
        return $this->query('INSERT INTO operators SELECT id FROM guardians WHERE handle = ? ON CONFLICT DO NOTHING', [$handle])->rowCount() > 0;
    }

    public function learnerExists(int $player): bool
    {
        return (bool) $this->query('SELECT 1 FROM players WHERE id = ?', [$player])->fetchColumn();
    }

    public function learners(string $search): array
    {
        return $this->query('SELECT id, handle FROM players WHERE instr(lower(handle), lower(?)) > 0 ORDER BY handle LIMIT 40',
            [$search])->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function saveCosmetic(string $id, array $product): bool
    {
        return $this->transaction(function () use ($id, $product): bool {
            $previous = $this->cosmetics()[$id] ?? null;
            if ($previous && $previous[0] !== $product[0]) {
                return false;
            }
            $this->query('INSERT INTO catalog_overrides VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(item) DO UPDATE SET
                title = excluded.title, value = excluded.value, cost = excluded.cost, active = excluded.active',
                [$id, ...$product]);
            return true;
        });
    }

}
