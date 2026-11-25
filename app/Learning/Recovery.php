<?php
declare(strict_types=1);

final class Recovery
{
    public function __construct(private PDO $db, private LearningStore $store, private Closure $send)
    {
    }

    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function email(int $player): ?string
    {
        $email = $this->query('SELECT email FROM verified_player_emails WHERE player = ?', [$player])->fetchColumn();
        return $email === false ? null : $email;
    }

    private static function address(string $email): ?string
    {
        $email = strtolower(trim($email));
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function limit(string $key, int $maximum): bool
    {
        $now = time();
        $this->query('DELETE FROM recovery_limits WHERE expires <= ?', [$now]);
        $result = $this->query('INSERT INTO recovery_limits VALUES (?, ?, 1)
            ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1
            WHERE attempts < ? RETURNING attempts', [hash('sha256', $key), $now + 3600, $maximum]);
        $allowed = $result->fetchColumn() !== false;
        $result->closeCursor();
        return $allowed;
    }

    public function requestEmail(int $player, string $address, string $password): bool
    {
        $email = self::address($address);
        if (!$email || strlen($password) > 72 || str_contains($password, "\0") || !$this->limit("verify:$player", 4)) {
            return false;
        }
        $hash = $this->query('SELECT password FROM players WHERE id = ?', [$player])->fetchColumn();
        if (!$hash || !password_verify($password, $hash) || $email === $this->email($player)) {
            return false;
        }
        return $this->issue($player, 'verify', $email);
    }

    public function requestReset(string $handle, string $address, string $ip): void
    {
        $email = self::address($address);
        if (!$email || !preg_match('/^[a-zA-Z0-9_-]{3,20}$/D', $handle)
            || !$this->limit("reset-ip:$ip", 120) || !$this->limit("reset-email:$email", 4)) {
            return;
        }
        $player = $this->query('SELECT p.id FROM players p JOIN verified_player_emails e ON e.player = p.id
            WHERE p.handle = ? AND e.email = ?', [$handle, $email])->fetchColumn();
        if ($player) {
            $this->issue((int) $player, 'reset', $email);
        }
    }

    private function issue(int $player, string $purpose, string $email): bool
    {
        $token = bin2hex(random_bytes(32));
        $digest = hash('sha256', $token);
        $this->query('INSERT INTO recovery_tokens VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(player, purpose) DO UPDATE SET digest = excluded.digest, email = excluded.email, expires = excluded.expires',
            [$digest, $player, $purpose, $email, time() + ($purpose === 'reset' ? 1800 : 3600)]);
        try {
            $sent = ($this->send)($email, $purpose, $token);
        } catch (Throwable) {
            $sent = false;
        }
        if (!$sent) {
            $this->query('DELETE FROM recovery_tokens WHERE digest = ?', [$digest]);
            error_log('Uceni: recovery mail delivery failed.');
        }
        return $sent;
    }

    public function complete(string $purpose, string $token, string $password = ''): bool
    {
        if (!in_array($purpose, ['verify', 'reset'], true) || !preg_match('/^[a-f0-9]{64}$/D', $token)
            || ($purpose === 'reset' && !Accounts::validPassword($password))) {
            return false;
        }
        return $this->store->transaction(function () use ($token, $purpose, $password): bool {
            $digest = hash('sha256', $token);
            $row = $this->query('SELECT player, email FROM recovery_tokens WHERE digest = ? AND purpose = ? AND expires > ?',
                [$digest, $purpose, time()])->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            $player = (int) $row['player'];
            $email = $row['email'];
            if ($purpose === 'verify') {
                $owner = $this->query('SELECT player FROM verified_player_emails WHERE email = ?', [$email])->fetchColumn();
                if ($owner !== false && (int) $owner !== $player) {
                    return false;
                }
                $this->query('INSERT INTO verified_player_emails VALUES (?, ?)
                    ON CONFLICT(player) DO UPDATE SET email = excluded.email', [$player, $email]);
            } else {
                if ($this->email($player) !== $email) {
                    return false;
                }
                $this->query('UPDATE players SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $player]);
                $this->query('DELETE FROM remembered_logins WHERE player = ?', [$player]);
                $this->query('INSERT INTO password_epochs VALUES (?, 1)
                    ON CONFLICT(player) DO UPDATE SET epoch = epoch + 1', [$player]);
            }
            $this->query('DELETE FROM recovery_tokens WHERE player = ?', [$player]);
            return true;
        });
    }
}
