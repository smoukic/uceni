<?php
declare(strict_types=1);

final class Web
{
    private ?array $player;
    private array $progress;
    private bool $wordsMode;
    private string $roundKey;
    private string $resultKey;

    public function __construct(private Store $store, private array $packs, private LearningWeb $learning)
    {
        $this->wordsMode = ($_GET['vyzva'] ?? null) === 'slova';
        $this->roundKey = $this->wordsMode ? 'wordsRound' : 'round';
        $this->resultKey = $this->wordsMode ? 'wordsResult' : 'result';
        $this->player = isset($_SESSION['player']) ? $store->player($_SESSION['player']) : null;
        $this->progress = $this->player ? $store->progress($this->player['id'], $this->wordsMode) : [];
    }

    public function run(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            if (!hash_equals($_SESSION['csrf'], $this->field('csrf'))) {
                http_response_code(403);
                exit('Platnost formuláře vypršela. Obnov stránku.');
            }
        }
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD, POST');
            return;
        }

        $player = $this->player;
        $progress = $this->progress;
        $packs = $this->packs;
        $wordsMode = $this->wordsMode;
        $url = $this->url(...);
        $round = $_SESSION[$this->roundKey] ?? null;
        $result = $_SESSION[$this->resultKey] ?? null;
        $message = $_SESSION['message'] ?? '';
        unset($_SESSION['message']);
        $rankings = [];
        $level = null;
        if (in_array($path, ['/obnova-hesla', '/nove-heslo', '/overit-email'], true)) {
            $view = 'recovery';
            if ($method === 'POST') {
                $recovery = $this->store->recovery();
                if ($path === '/obnova-hesla') {
                    $handle = trim($this->field('handle'));
                    $email = $this->field('email');
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $notice = 'Pokud údaje odpovídají ověřenému účtu, přijde ti e-mail s odkazem.';
                    if (function_exists('fastcgi_finish_request')) {
                        $_SESSION['message'] = $notice;
                        session_write_close();
                        header('Location: /obnova-hesla', true, 303);
                        fastcgi_finish_request();
                        $recovery->requestReset($handle, $email, $ip);
                        exit;
                    }
                    $recovery->requestReset($handle, $email, $ip);
                    $this->go($path, $notice);
                }
                $verify = $path === '/overit-email';
                $password = $this->field('password');
                $success = ($verify || $password === $this->field('confirm_password'))
                    && $recovery->complete($verify ? 'verify' : 'reset', $this->field('token'), $password);
                if ($success) {
                    $this->go($verify && $player ? '/profil' : '/prihlaseni', $verify ? 'E-mail je ověřený.' : 'Heslo je změněné. Přihlas se novým heslem.');
                }
                // Same document keeps the token in the hidden field for correcting a typo.
                $message = 'Odkaz už neplatí nebo zadané údaje nejsou správné.';
            }
            require __DIR__ . '/../Views/layout.php';
            return;
        }
        $newPage = $this->learning->page((string) $path, $method, $player);
        if ($player && $method === 'GET') {
            $this->store->learning->visit((int) $player['id']);
        }
        if ($newPage === null && $player && $this->store->accounts->grade((int) $player['id'])['ceiling'] < 3 &&
            ($path === '/' || str_starts_with((string) $path, '/mise') || str_starts_with((string) $path, '/hra'))) {
            header('Location: /predmety', true, 303);
            exit;
        }
        if ($newPage === null && $method === 'POST') {
            $this->submit($path);
        }
        $profile = $player ? $this->learning->profile((int) $player['id'], $path === '/profil') : null;
        if ($newPage !== null) {
            [$view, $page] = $newPage;
            require __DIR__ . '/../Views/layout.php';
            return;
        }
        $verifiedEmail = $path === '/profil' && $player ? $this->store->recovery()->email((int) $player['id']) : null;
        $view = match ($path) {
            '/' => 'map',
            '/prihlaseni', '/registrace' => 'auth',
            '/profil' => 'profile',
            '/parta' => 'team',
            '/hra' => 'game',
            default => '404',
        };
        if (preg_match('~^/mise/(\d{1,4})$~D', (string) $path, $match)) {
            $level = (int) $match[1];
            $view = 'mission';
            $this->ensureUnlocked($level);
        }
        if (in_array($view, ['profile', 'team', 'game'], true)) {
            $this->requirePlayer();
        }
        if ($view === 'team' && $player['team']) {
            $rankings = $this->store->rankings($player['team'], $wordsMode);
        }
        if ($view === 'game' && !$round) {
            $this->go('/');
        }
        if ($view === '404') {
            http_response_code(404);
        }
        require __DIR__ . '/../Views/layout.php';
    }

    private function field(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    private function go(string $path, string $message = ''): never
    {
        if ($message !== '') {
            $_SESSION['message'] = $message;
        }
        header('Location: ' . $this->url($path), true, 303);
        exit;
    }

    private function url(string $path): string
    {
        return $this->wordsMode ? $path . '?vyzva=slova' : $path;
    }

    private function requirePlayer(): int
    {
        return $this->player['id'] ?? $this->signInFirst();
    }

    private function signInFirst(): never
    {
        $this->go('/prihlaseni', 'Přihlas se a pokračuj ve své výpravě.');
    }

    private function ensureUnlocked(int $level): void
    {
        $required = $level > 0 ? range(0, $level - 1) : [];
        if (!isset($this->packs[$level]) || array_diff($required, array_keys($this->progress))) {
            $this->go('/', 'Tahle mise se odemkne po dokončení předchozí.');
        }
    }

    private function submit(string $path): never
    {
        if ($path === '/registrace' || $path === '/prihlaseni') {
            $this->authenticate($path);
        }
        if ($path === '/odhlaseni') {
            $this->store->accounts->forget();
            $_SESSION = [];
            session_regenerate_id(true);
            $this->go('/');
        }
        if (!in_array($path, ['/mise/start', '/hra/odpoved', '/hra/dal', '/profil', '/parta'], true)) {
            http_response_code(404);
            exit('Stránka tu není.');
        }
        $player = $this->requirePlayer();
        switch ($path) {
            case '/mise/start':
                $raw = $this->field('level');
                if (!preg_match('/^\d{1,4}$/D', $raw)) {
                    $this->go('/');
                }
                $level = (int) $raw;
                $this->ensureUnlocked($level);
                if (!isset($_SESSION[$this->roundKey]) || $_SESSION[$this->roundKey]['phase'] === 'won') {
                    unset($_SESSION[$this->resultKey]);
                    $_SESSION[$this->roundKey] = Round::begin($level);
                }
                $this->go('/hra');
            case '/hra/odpoved':
                $this->answer($player);
            case '/hra/dal':
                $this->advance();
            case '/profil':
                if ($this->field('action') === 'email') {
                    $sent = $this->store->recovery()->requestEmail($player, $this->field('email'), $this->field('current_password'));
                    $this->go('/profil', $sent ? 'Ověřovací odkaz jsme poslali na zadaný e-mail.' : 'Ověřovací odkaz se nepodařilo odeslat. Zkontroluj e-mail a heslo nebo zkus později.');
                }
                if ($this->field('action') === 'password') {
                    $next = $this->field('next_password');
                    $changed = $next === $this->field('confirm_password')
                        && $this->store->accounts->changePassword($player, $this->field('current_password'), $next);
                    $this->go('/profil', $changed ? 'Heslo je změněné. Ostatní zařízení jsou odhlášená.' : 'Zkontroluj současné heslo a zopakování nového hesla.');
                }
                if ($this->field('action') === 'grade') {
                    $saved = $this->store->accounts->initializeGrade($player, (int) $this->field('grade'));
                    $this->go('/profil', $saved ? 'Ročník uložen.' : 'Ročník může později upravit rodič nebo správce.');
                }
                $school = trim($this->field('school'));
                if (mb_strlen($school) > 100) {
                    $this->go('/profil', 'Název školy může mít nejvýš 100 znaků.');
                }
                $this->store->school($player, $school);
                $this->go('/profil', 'Profil uložen.');
            case '/parta':
                $this->updateTeam($player);
        }
        $this->go('/');
    }

    private function authenticate(string $path): never
    {
        if ($this->player) {
            $this->go('/');
        }
        $handle = trim($this->field('handle'));
        $password = $this->field('password');
        $register = $path === '/registrace';
        if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/D', $handle) || strlen($password) > 72
            || str_contains($password, "\0") || ($register && mb_strlen($password) < 8)) {
            $this->go($path, $register ? 'Přezdívka má mít 3–20 znaků (A–Z, číslice, _ nebo -) a heslo alespoň 8 znaků.' : 'Přezdívka nebo heslo nesouhlasí.');
        }
        $grade = (int) $this->field('grade');
        if ($register && ($grade < 1 || $grade > 9)) {
            $this->go($path, 'Vyber svůj ročník.');
        }
        $id = $register ? $this->store->register($handle, $password, $grade) : $this->store->login($handle, $password);
        if (!$id) {
            $this->go($path, $register ? 'Tahle přezdívka už je zabraná.' : 'Přihlášení se nepovedlo. Zkontroluj údaje nebo zkus za pár minut.');
        }
        $return = $_SESSION['learning_return'] ?? null;
        $this->store->accounts->signIn($id, $this->field('remember') === '1');
        if ($return) {
            header('Location: ' . $return, true, 303);
            exit;
        }
        $this->go('/');
    }

    private function currentRound(): array
    {
        $round = $_SESSION[$this->roundKey] ?? null;
        if (!$round || !hash_equals($round['token'], $this->field('token'))) {
            $this->go('/hra');
        }
        $this->ensureUnlocked($round['level']);
        return $round;
    }

    private function advance(): never
    {
        $round = $this->currentRound();
        $phase = match ($round['phase']) {
            'study' => 'practice',
            'fragments' => 'study',
            'ready', 'retry' => 'final',
            default => null,
        };
        if ($phase !== null) {
            Round::move($round, $phase);
            $_SESSION[$this->roundKey] = $round;
            unset($_SESSION[$this->resultKey]);
        }
        $this->go('/hra');
    }

    private function answer(int $player): never
    {
        $round = $this->currentRound();
        if (!in_array($round['phase'], ['practice', 'gap', 'fragments', 'final'], true)) {
            $this->go('/hra');
        }

        $level = $round['level'];
        $words = $this->packs[$level]['words'];
        $final = $round['phase'] === 'final';
        $expected = match ($round['phase']) {
            'final' => $words,
            'gap' => [$words[$round['gap']]],
            default => Round::block($round, $words),
        };
        $answers = $_POST['words'] ?? null;
        $fragments = $round['phase'] === 'fragments';
        if ($fragments) {
            $answers = Round::completeFragments($expected, $answers);
        }
        $mistakes = Round::check($expected, $answers, $this->wordsMode && !$fragments);
        if ($mistakes === null) {
            $this->go('/hra', 'Vyplň všechny řádky a napiš do každého jedno slovo nebo ustálené spojení.');
        }

        $round['attempt'] ??= bin2hex(random_bytes(16));
        $round['seconds'] = min(7200, ($round['seconds'] ?? 0) + max(0, min(90, time() - ($round['shownAt'] ?? time()))));
        $round['answered'] = ($round['answered'] ?? 0) + count($expected);
        $round['correct'] = ($round['correct'] ?? 0) + count($expected) - count($mistakes);
        $mode = $this->wordsMode ? 'words' : 'order';
        $score = (int) floor($round['correct'] * 100 / max(1, $round['answered']));
        $this->store->learning->study($round['attempt'], $player, 'cestina/3', "legacy:$mode:$level",
            $round['seconds'], $score, $round['answered'], $final);

        if ($final) {
            if ($mistakes === []) {
                $this->store->award($player, $level, Round::REWARD, $this->wordsMode);
            }
            $_SESSION[$this->resultKey] = $mistakes;
            Round::move($round, $mistakes === [] ? 'won' : 'retry');
            if ($mistakes !== []) {
                $round['attempt'] = bin2hex(random_bytes(16));
                $round['seconds'] = $round['answered'] = $round['correct'] = 0;
            }
        } elseif ($round['phase'] === 'practice' && $mistakes === []) {
            if (!$this->wordsMode) {
                $round['gap'] = array_rand(Round::trail($round, $words));
            }
            Round::move($round, $this->wordsMode ? 'fragments' : 'gap');
            unset($_SESSION[$this->resultKey]);
        } else {
            $cleared = $mistakes === [];
            $round['missedLink'] = !$cleared && $round['phase'] === 'gap' ? $round['gap'] : null;
            $round['offset'] += $cleared ? count(Round::block($round, $words)) : 0;
            $nextPhase = $round['offset'] >= count($words) ? 'ready' : 'study';
            Round::move($round, $fragments && !$cleared ? 'fragments' : $nextPhase);
            $_SESSION[$this->resultKey] = $mistakes;
        }
        $_SESSION[$this->roundKey] = $round;
        $this->go('/hra');
    }

    private function updateTeam(int $player): never
    {
        switch ($this->field('action')) {
            case 'create':
                if (!$this->player['team']) {
                    $this->store->createTeam($player);
                }
                break;
            case 'join':
                $code = strtoupper(trim($this->field('code')));
                if (!preg_match('/^[0-9A-F]{10}$/D', $code) || !$this->store->team($player, $code)) {
                    $this->go('/parta', 'Partu s tímto kódem jsme nenašli.');
                }
                break;
            case 'leave':
                $this->store->team($player, null);
                break;
        }
        $this->go('/parta');
    }
}
