<?php
declare(strict_types=1);

final class Accounts
{
    private const COOKIE = 'UCENI_REMEMBER';
    private const LIFETIME = 2592000;

    public function __construct(private PDO $db)
    {
    }

    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function grade(int $player): array
    {
        return $this->query('SELECT grade, ceiling FROM learner_settings WHERE player = ?', [$player])->fetch(PDO::FETCH_ASSOC)
            ?: ['grade' => null, 'ceiling' => 6];
    }

    public function initializeGrade(int $player, int $grade): bool
    {
        return $grade >= 1 && $grade <= 9 && $this->query(
            'INSERT INTO learner_settings VALUES (?, ?, ?) ON CONFLICT DO NOTHING', [$player, $grade, $grade]
        )->rowCount() > 0;
    }

    public function setGrade(int $player, int $grade, int $ceiling): bool
    {
        if ($grade < 1 || $ceiling < $grade || $ceiling > 9) {
            return false;
        }
        $this->query('INSERT INTO learner_settings VALUES (?, ?, ?) ON CONFLICT(player) DO UPDATE SET grade = excluded.grade, ceiling = excluded.ceiling',
            [$player, $grade, $ceiling]);
        return true;
    }

    public function changePassword(int $player, string $current, string $next): bool
    {
        if (!self::validPassword($next) || strlen($current) > 72 || str_contains($current, "\0")) {
            return false;
        }
        $old = $this->query('SELECT password FROM players WHERE id = ?', [$player])->fetchColumn();
        if (!$old || !password_verify($current, $old)) {
            return false;
        }
        $this->db->beginTransaction();
        try {
            $changed = $this->query('UPDATE players SET password = ? WHERE id = ? AND password = ?',
                [password_hash($next, PASSWORD_DEFAULT), $player, $old])->rowCount() > 0;
            if ($changed) {
                $this->query('DELETE FROM remembered_logins WHERE player = ?', [$player]);
                $this->query('INSERT INTO password_epochs VALUES (?, 1) ON CONFLICT(player) DO UPDATE SET epoch = epoch + 1', [$player]);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        if ($changed) {
            $this->cookie('', time() - 3600);
            session_regenerate_id(true);
            $_SESSION['password_epoch'] = $this->epoch($player);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $changed;
    }

    public static function validPassword(string $password): bool
    {
        return strlen($password) <= 72 && !str_contains($password, "\0") && mb_strlen($password) >= 8;
    }

    private function epoch(int $player): int
    {
        return (int) $this->query('SELECT epoch FROM password_epochs WHERE player = ?', [$player])->fetchColumn();
    }

    private function cookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, [
            'expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    private function token(): ?array
    {
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($cookie) || !preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/D', $cookie, $parts)) {
            return null;
        }
        return [$parts[1], hash('sha256', $parts[2])];
    }

    private function issue(int $player): array
    {
        $this->query('DELETE FROM remembered_logins WHERE expires <= ?', [time()]);
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::LIFETIME;
        $this->query('INSERT INTO remembered_logins VALUES (?, ?, ?, ?)',
            [$selector, $player, hash('sha256', $validator), $expires]);
        return ["$selector.$validator", $expires];
    }

    private function session(int $player, int $epoch): void
    {
        session_regenerate_id(true);
        $_SESSION = ['player' => $player, 'password_epoch' => $epoch, 'csrf' => bin2hex(random_bytes(32))];
    }

    public function signIn(int $player, bool $remember): void
    {
        $this->forget();
        $this->session($player, $this->epoch($player));
        if ($remember) {
            $this->cookie(...$this->issue($player));
        }
    }

    public function restore(): void
    {
        if (isset($_SESSION['player'])) {
            if ($this->epoch((int) $_SESSION['player']) !== ($_SESSION['password_epoch'] ?? 0)) {
                $this->forget();
                session_regenerate_id(true);
                $_SESSION = [];
            }
            return;
        }
        if (isset($_SESSION['guardian']) || !($token = $this->token())) {
            return;
        }
        // Rotate under the same lock used to revoke tokens when a password changes.
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $statement = $this->query('DELETE FROM remembered_logins WHERE selector = ? AND digest = ? AND expires > ? RETURNING player',
                [...$token, time()]);
            $player = (int) $statement->fetchColumn();
            $statement->closeCursor();
            if ($player) {
                $epoch = $this->epoch($player);
                $replacement = $this->issue($player);
            }
            $this->db->exec('COMMIT');
        } catch (Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
        if ($player) {
            $this->session($player, $epoch);
            $this->cookie(...$replacement);
        } else {
            $this->cookie('', time() - 3600);
        }
    }

    public function forget(): void
    {
        if ($token = $this->token()) {
            $this->query('DELETE FROM remembered_logins WHERE selector = ? AND digest = ?', $token);
            $this->cookie('', time() - 3600);
        }
    }
}
