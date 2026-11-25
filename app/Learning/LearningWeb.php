<?php
declare(strict_types=1);

final class LearningWeb
{
    private const PASS = 80;
    private const STATUS = ['pending' => 'Čeká na schválení', 'approved' => 'Schváleno', 'rejected' => 'Zamítnuto', 'fulfilled' => 'Splněno'];

    public function __construct(private LearningStore $store, private Accounts $accounts, private Curriculum $curriculum)
    {
    }

    public function page(string $path, string $method, ?array $player): ?array
    {
        if (preg_match('~^/predmet/([a-z0-9-]+)/([0-9]+)(?:/(.*))?$~D', $path, $parts)) {
            if ($player && (int) $parts[2] > $this->accounts->grade((int) $player['id'])['ceiling']) {
                $this->redirect('/predmety', 'Tento ročník může odemknout rodič nebo správce.');
            }
            $course = $this->curriculum->course($parts[1], $parts[2]);
            return $course ? $this->course($course, $parts[3] ?? '', $method, $player) : $this->missing();
        }
        return match ($path) {
            '/predmety' => $this->subjects($method, $player),
            '/historie' => $this->history($player, $method),
            '/sprava' => $this->admin($method),
            '/restart-cestiny' => $this->restartCzech($player, $method),
            '/odmeny' => $this->rewards($method, $player),
            '/rodic' => $this->guardian($method),
            default => null,
        };
    }

    public function profile(int $player, bool $showSummary): array
    {
        $items = $this->store->cosmetics();
        $style = [];
        foreach ($this->store->equipped($player) as $category => $item) {
            if (isset($items[$item])) {
                $style[$category] = $items[$item][2];
            }
        }
        return ['style' => $style, 'summary' => $showSummary ? $this->store->summary($player) : null,
            'grade' => $showSummary ? $this->accounts->grade($player) : null];
    }

    private function field(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    private function redirect(string $path, string $message = ''): never
    {
        if ($message !== '') {
            $_SESSION['message'] = $message;
        }
        header('Location: ' . $path, true, 303);
        exit;
    }

    private function signIn(string $return): never
    {
        $_SESSION['learning_return'] = $return;
        $this->redirect('/prihlaseni', 'Přihlas se nebo si založ hráče, abychom uložili tvůj postup.');
    }

    private function missing(): array
    {
        http_response_code(404);
        return ['404', []];
    }

    private function subjects(string $method, ?array $player): array
    {
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $this->missing();
        }
        $settings = $player ? $this->accounts->grade((int) $player['id']) : ['grade' => null, 'ceiling' => 9];
        $requested = $_GET['trida'] ?? $settings['grade'] ?? 3;
        $grade = is_scalar($requested) ? max(1, min(9, (int) $requested)) : 3;
        return ['subjects', ['subjects' => $this->curriculum->catalog, 'ceiling' => $settings['ceiling'], 'grade' => $grade,
            'signedIn' => $player !== null, 'needsGrade' => $player && $settings['grade'] === null]];
    }

    private function course(array $course, string $path, string $method, ?array $player): array
    {
        $base = $course['url'];
        $id = $course['id'];
        $playerId = $player ? (int) $player['id'] : null;
        if ($path === 'cviceni') {
            if (!$playerId) {
                $this->signIn($base);
            }
            return $this->exercise($course, $method, $playerId);
        }

        $results = $playerId ? $this->store->results($playerId, $id) : [];
        $due = $playerId ? array_values(array_intersect($this->store->due($playerId, $id), array_keys($course['items']))) : [];
        $access = [];
        $allPassed = true;
        foreach ($course['missions'] as $key => $mission) {
            $access[$key] = $key === 'diktat' || $allPassed;
            $allPassed = $allPassed && ($key === 'diktat' || ($results['mission:' . $key] ?? 0) >= self::PASS);
        }

        if ($path === '') {
            return $method === 'POST' ? $this->missing() : ['course', compact('course', 'results', 'due', 'access', 'allPassed')];
        }

        if ($path === 'opakovani' || $path === 'zaverecny-test') {
            $review = $path === 'opakovani';
            if (!$playerId) {
                $this->signIn($base . '/' . $path);
            }
            if (!$review && !$allPassed) {
                $this->redirect($base, 'Nejdřív dokonči testy všech misí.');
            }
            $items = $review ? array_slice($due, 0, 15) : $this->sample($course, 3);
            $heading = $review ? 'Opakování chyb' : 'Závěrečný test ročníku';
            if ($method === 'POST') {
                $this->start($course, $items, $review ? 'review' : 'exam', $heading, $base . '/' . $path, $playerId);
            }
            return ['assessment', compact('course', 'items', 'heading', 'review')];
        }

        $parts = explode('/', $path);
        $key = $parts[0];
        $mission = $course['missions'][$key] ?? null;
        if ($mission === null || count($parts) > 2) {
            return $this->missing();
        }
        if (!$access[$key]) {
            $this->redirect($base, 'Tato mise se odemkne po zvládnutí předchozího testu.');
        }

        $missionUrl = $base . '/' . $key;
        if (!isset($parts[1])) {
            $required = array_filter($mission['lessons'], static fn(array $lesson): bool => $lesson['category'] !== 'Rozšíření');
            $reset = $playerId ? $this->store->missionResetPreview($playerId, $course, $key) : null;
            if ($method === 'POST' && $this->field('action') === 'reset') {
                if (!$playerId || $this->field('confirm') !== '1' || !$reset || !$this->store->resetMission($playerId, $course, $key, $this->field('xp'))) {
                    $this->redirect($missionUrl, 'Restart se neprovedl. Zkontroluj aktuální stav a zkus to znovu.');
                }
                unset($_SESSION['curriculum'][$course['id']]);
                $this->redirect($missionUrl, 'Mise je připravená na nový průchod. Předchozí pokusy najdeš v historii.');
            }
            $ready = count(array_filter($required, static fn(array $lesson): bool => ($results['lesson:' . $lesson['id']] ?? 0) >= self::PASS)) === count($required);
            if ($method === 'POST') {
                if (!$playerId) {
                    $this->signIn($missionUrl);
                }
                if (!$ready) {
                    $this->redirect($missionUrl, 'Nejdřív zvládni ověření povinných lekcí.');
                }
                $items = [];
                foreach ($required as $lesson) {
                    $items = [...$items, ...$this->pick($lesson['items'], 3)];
                }
                shuffle($items);
                $this->start($course, $items, "mission:$key", 'Test mise: ' . $mission['title'], $missionUrl, $playerId);
            }
            return ['course-mission', compact('course', 'mission', 'missionUrl', 'results', 'ready', 'reset')];
        }

        $lesson = $mission['lessons'][$parts[1]] ?? null;
        if ($lesson === null) {
            return $this->missing();
        }
        $lessonUrl = $missionUrl . '/' . $lesson['id'];
        if ($method === 'POST') {
            if (!$playerId) {
                $this->signIn($lessonUrl);
            }
            $quiz = $this->field('action') === 'quiz';
            $items = $quiz ? array_slice($lesson['items'], -3) : array_slice($lesson['items'], 0, -3);
            $task = ($quiz ? 'lesson:' : 'practice:') . $lesson['id'];
            $this->start($course, $items, $task, $lesson['name'], $lessonUrl, $playerId);
        }
        return ['lesson', compact('course', 'lesson', 'lessonUrl', 'missionUrl', 'results')];
    }

    private function pick(array $items, int $count): array
    {
        shuffle($items);
        return array_slice($items, 0, $count);
    }

    private function sample(array $course, int $count): array
    {
        $items = [];
        foreach ($course['missions'] as $mission) {
            if ($mission['id'] === 'diktat') {
                continue;
            }
            $pool = [];
            foreach ($mission['lessons'] as $lesson) {
                if ($lesson['category'] !== 'Rozšíření') {
                    $pool = [...$pool, ...$lesson['items']];
                }
            }
            $items = [...$items, ...$this->pick($pool, $count)];
        }
        shuffle($items);
        return $items;
    }

    private function start(array $course, array $items, string $task, string $heading, string $back, int $player): never
    {
        $existing = $_SESSION['curriculum'][$course['id']] ?? null;
        if ($existing && !$existing['finished'] && $existing['player'] === $player && $this->field('replace') !== 'yes') {
            $this->redirect($course['url'] . '/cviceni', 'Nejdřív dokonči rozehrané cvičení, nebo se z něj vrať a začni nové.');
        }
        if ($items === []) {
            $this->redirect($back, 'Teď nemáš žádné otázky k opakování.');
        }
        $_SESSION['curriculum'][$course['id']] = compact('items', 'task', 'heading', 'back', 'player') + [
            'position' => 0, 'correct' => 0, 'total' => count($items), 'missed' => [], 'feedback' => null,
            'answered' => 0, 'firstAnswers' => 0, 'seconds' => 0, 'shownAt' => time(),
            'finished' => false, 'token' => bin2hex(random_bytes(16)), 'attempt' => bin2hex(random_bytes(16)),
        ];
        $this->redirect($course['url'] . '/cviceni');
    }

    private function exercise(array $course, string $method, int $player): array
    {
        $id = $course['id'];
        $path = $course['url'] . '/cviceni';
        $run = $_SESSION['curriculum'][$id] ?? null;
        if (!$run || $run['player'] !== $player) {
            $this->redirect($course['url']);
        }
        $itemId = $run['items'][$run['position']] ?? null;
        $item = $course['items'][$itemId] ?? null;
        if (!$run['finished'] && $item === null) {
            unset($_SESSION['curriculum'][$id]);
            $this->redirect($course['url'], 'Obsah cvičení se změnil. Spusť ho prosím znovu.');
        }
        if ($method === 'POST') {
            if (!hash_equals($run['token'], $this->field('token'))) {
                $this->redirect($path);
            }
            $action = $this->field('action');
            if ($action === 'exit') {
                $this->saveStudy($player, $course, $run, false);
                unset($_SESSION['curriculum'][$id]);
                $this->redirect($run['back']);
            }
            if ($run['finished']) {
                $this->redirect($path);
            }
            $review = $run['task'] === 'review';
            $practice = str_starts_with($run['task'], 'practice');
            if ($action === 'answer' && $run['feedback'] === null) {
                $answer = $_POST['answer'] ?? null;
                if ($answer === null || $answer === '') {
                    $this->redirect($path, 'Nejdřív vyber nebo napiš odpověď.');
                }
                $run['seconds'] = min(7200, ($run['seconds'] ?? 0) + max(0, min(90, time() - ($run['shownAt'] ?? time()))));
                $run['answered'] = ($run['answered'] ?? 0) + 1;
                if ($run['position'] < $run['total']) {
                    $run['firstAnswers'] = ($run['firstAnswers'] ?? 0) + 1;
                }
                $correct = Curriculum::check($item, $answer);
                $this->store->reviewAnswer($player, $id, $itemId, $correct, $review);
                if ($run['position'] < $run['total'] && $correct) {
                    $run['correct']++;
                }
                if (!$correct && ($practice || $review) && !isset($run['missed'][$itemId])) {
                    $run['missed'][$itemId] = true;
                    $run['items'][] = $itemId;
                }
                $run['feedback'] = $correct;
                $run['hint'] = $correct ? '' : Curriculum::writingHint($item, $answer);
                $this->saveStudy($player, $course, $run, false);
            } elseif ($action === 'next' && $run['feedback'] !== null) {
                $run['position']++;
                $run['feedback'] = null;
                $run['shownAt'] = time();
                if ($run['position'] >= count($run['items'])) {
                    $run['finished'] = true;
                    $run['score'] = (int) floor($run['correct'] * 100 / $run['total']);
                    $this->saveStudy($player, $course, $run, true);
                    if (!$practice && !$review) {
                        $run['xp'] = $this->store->recordResult($player, $course, $run['task'], $run['score'], $run['attempt']);
                    }
                }
            } else {
                $this->redirect($path);
            }
            $run['token'] = bin2hex(random_bytes(16));
            $_SESSION['curriculum'][$id] = $run;
            $this->redirect($path);
        }
        $passed = ($run['score'] ?? 0) >= self::PASS;
        return ['exercise', compact('course', 'run', 'item', 'itemId', 'path', 'passed')];
    }

    private function saveStudy(int $player, array $course, array $run, bool $completed): void
    {
        if (!empty($run['answered'])) {
            $score = (int) floor($run['correct'] * 100 / max(1, $run['firstAnswers']));
            $this->store->study($run['attempt'], $player, $course['id'], $run['task'], $run['seconds'], $score, $run['answered'], $completed);
        }
    }

    private function admin(string $method): array
    {
        $guardian = $this->currentGuardian();
        if (!$guardian || !$this->store->operator($guardian)) {
            http_response_code(403);
            return ['forbidden', []];
        }
        if ($method === 'POST') {
            if ($this->field('action') === 'grade') {
                $player = (int) $this->field('player');
                $known = $this->store->learnerExists($player);
                $saved = $known && $this->accounts->setGrade($player, (int) $this->field('grade'), (int) $this->field('ceiling'));
            } else {
                $id = $this->field('item');
                $category = $this->field('category');
                $title = trim($this->field('title'));
                $value = trim($this->field('value'));
                $cost = filter_var($this->field('cost'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
                $validValue = match ($category) {
                    'frame' => in_array($value, ['mint', 'gold', 'rose', 'sky'], true),
                    'theme' => in_array($value, ['forest', 'night', 'berry', 'ocean'], true),
                    'avatar', 'pin', 'title' => $value !== '' && mb_strlen($value) <= 32,
                    default => false,
                };
                $saved = preg_match('/^[a-z0-9-]{2,40}$/D', $id) && $validValue && $title !== '' && mb_strlen($title) <= 80 && $cost !== false
                    && $this->store->saveCosmetic($id, [$category, $title, $value, $cost, (int) ($this->field('active') === '1')]);
            }
            $this->redirect('/sprava', $saved ? 'Nastavení je uložené.' : 'Zkontrolujte hodnoty a zkuste znovu.');
        }
        $search = is_string($_GET['hledat'] ?? null) ? trim($_GET['hledat']) : '';
        $learners = $search !== '' ? $this->store->learners($search) : [];
        $settings = [];
        foreach ($learners as $player => $name) {
            $settings[$player] = $this->accounts->grade((int) $player);
        }
        return ['admin', compact('learners', 'settings', 'search') + ['products' => $this->store->cosmetics()]];
    }

    private function history(?array $player, string $method): array
    {
        if ($method === 'POST') {
            return $this->missing();
        }
        if (!$player) {
            $this->signIn('/historie');
        }
        return ['history', ['activity' => $this->store->activity((int) $player['id']), 'labels' => $this->resultLabels()]];
    }

    private function restartCzech(?array $player, string $method): array
    {
        if (!$player) {
            $this->signIn('/');
        }
        if ($this->accounts->grade((int) $player['id'])['ceiling'] < 3) {
            $this->redirect('/predmety');
        }
        $words = ($_GET['vyzva'] ?? null) === 'slova';
        $level = filter_var($_POST['level'] ?? null, FILTER_VALIDATE_INT);
        $url = $words ? '/?vyzva=slova' : '/';
        if ($method === 'POST' && $level !== false && $level >= 0 && $level < 7 && $this->field('confirm') === '1'
            && $this->store->resetCzech((int) $player['id'], $level, $words, $this->field('xp'))) {
            unset($_SESSION[$words ? 'wordRound' : 'round'], $_SESSION[$words ? 'wordResult' : 'result']);
            $this->redirect('/mise/' . $level . ($words ? '?vyzva=slova' : ''), 'Misi můžeš projít znovu.');
        }
        $this->redirect($url, 'Restart se neprovedl. Aktualizuj stránku nebo potvrď ztrátu XP.');
    }

    private function rewards(string $method, ?array $player): array
    {
        if (!$player) {
            $this->signIn('/odmeny');
        }
        $id = (int) $player['id'];
        if ($method === 'POST') {
            $item = $this->field('item');
            $request = $this->field('request');
            $success = match ($this->field('action')) {
                'buy' => $this->store->buy($id, $item),
                'equip' => $this->store->equip($id, $item, false),
                'remove' => $this->store->equip($id, $item, true),
                'claim' => preg_match('/^[a-f0-9]{32}$/D', $request) && $this->store->claim($id, (int) $item, $request),
                'link' => $this->store->linkChild($id, $this->field('code')),
                default => false,
            };
            $this->redirect('/odmeny', $success ? 'Hotovo.' : 'Akci se nepodařilo provést. Zkontroluj zůstatek, stav odměny nebo platnost kódu.');
        }
        return ['rewards', [
            'summary' => $this->store->summary($id),
            'cosmetics' => $this->store->cosmetics(),
            'owned' => $this->store->owned($id),
            'equipped' => $this->store->equipped($id),
            'rewards' => $this->store->childRewards($id),
            'coins' => $this->store->coinHistory($id),
            'claims' => $this->store->claims($id),
            'linked' => $this->store->linked($id),
            'statuses' => self::STATUS,
        ]];
    }

    private function currentGuardian(): ?int
    {
        $id = (int) ($_SESSION['guardian'] ?? 0);
        if ($id && $this->store->guardianEpoch($id) !== ($_SESSION['guardian_epoch'] ?? 0)) {
            unset($_SESSION['guardian'], $_SESSION['guardian_epoch'], $_SESSION['guardian_invite']);
            session_regenerate_id(true);
            return null;
        }
        return $id ?: null;
    }

    private function guardian(string $method): array
    {
        $guardian = $this->currentGuardian();
        if ($method === 'POST') {
            $action = $this->field('action');
            if (!$guardian && in_array($action, ['login', 'activate'], true)) {
                $handle = trim($this->field('handle'));
                $password = $this->field('password');
                $register = $action === 'activate';
                $id = null;
                $code = trim($this->field('code'));
                if (preg_match('/^[A-Za-z0-9_-]{3,30}$/D', $handle) && Accounts::validPassword($password)) {
                    if ($register && preg_match('/^[a-f0-9]{32}$/Di', $code)) {
                        $id = $this->store->registerGuardian($handle, $password, $code);
                    } elseif (!$register) {
                        $id = $this->store->loginGuardian($handle, $password);
                    }
                }
                if (!$id) {
                    $this->redirect('/rodic', 'Přihlášení nebo aktivace se nezdařily. Zkontrolujte údaje a platnost kódu.');
                }
                $this->accounts->forget();
                session_regenerate_id(true);
                $_SESSION = ['guardian' => $id, 'guardian_epoch' => $this->store->guardianEpoch($id), 'csrf' => bin2hex(random_bytes(32))];
                $this->redirect('/rodic');
            }
            if (!$guardian) {
                http_response_code(403);
                return ['guardian-auth', []];
            }
            if ($action === 'logout') {
                session_regenerate_id(true);
                $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
                $this->redirect('/rodic');
            }
            if ($action === 'password') {
                $password = $this->field('next_password');
                $saved = $password === $this->field('confirm_password')
                    && $this->store->changeGuardianPassword($guardian, $this->field('current_password'), $password);
                if ($saved) {
                    session_regenerate_id(true);
                    $_SESSION['guardian_epoch'] = $this->store->guardianEpoch($guardian);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                }
                $this->redirect('/rodic', $saved ? 'Heslo bylo změněno.' : 'Zkontrolujte současné heslo a zopakování nového hesla.');
            }
            if ($action === 'invite') {
                $_SESSION['guardian_invite'] = $this->store->guardianInvite($guardian);
                $this->redirect('/rodic');
            }

            $child = (int) $this->field('child');
            $success = false;
            if (isset($this->store->children($guardian)[$child])) {
                if ($action === 'grade') {
                    $success = $this->accounts->setGrade($child, (int) $this->field('grade'), (int) $this->field('ceiling'));
                } elseif ($action === 'save') {
                    $title = trim($this->field('title'));
                    $cost = filter_var($this->field('cost'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
                    if ($title !== '' && mb_strlen($title) <= 100 && $cost !== false) {
                        $success = $this->store->saveReward($guardian, $child, (int) $this->field('item'), $title, $cost, $this->field('active') === '1');
                    }
                } else {
                    $success = $this->store->changeClaim($guardian, (int) $this->field('item'), $action);
                }
            }
            $this->redirect('/rodic?dite=' . $child, $success ? 'Uloženo.' : 'Změnu se nepodařilo uložit.');
        }
        if (!$guardian) {
            return ['guardian-auth', []];
        }
        $children = $this->store->children($guardian);
        $requested = $_GET['dite'] ?? null;
        $child = is_string($requested) && isset($children[(int) $requested]) ? (int) $requested : array_key_first($children);
        $invite = $_SESSION['guardian_invite'] ?? null;
        unset($_SESSION['guardian_invite']);
        return ['guardian', compact('children', 'child', 'invite') + [
            'operator' => $this->store->operator((int) $guardian),
            'gradeSetting' => $child ? $this->accounts->grade($child) : null,
            'activity' => $child ? $this->store->activity($child) : null,
            'summary' => $child ? $this->store->summary($child) : null,
            'history' => $child ? $this->store->earnedHistory($child) : [],
            'results' => $child ? $this->store->courseHistory($child) : [],
            'attempts' => $child ? $this->store->recentAttempts($child) : [],
            'rewards' => $child ? $this->store->guardianRewards($guardian, $child) : [],
            'claims' => $child ? $this->store->claims($child, $guardian) : [],
            'coins' => $child ? $this->store->coinHistory($child) : [],
            'statuses' => self::STATUS,
            'labels' => $this->resultLabels(),
        ]];
    }

    private function resultLabels(): array
    {
        $labels = [];
        foreach (['B', 'L', 'M', 'P', 'S', 'V', 'Z'] as $index => $letter) {
            $labels['cestina/3']["legacy:order:$index"] = "Vyjmenovaná slova po $letter · Správné pořadí";
            $labels['cestina/3']["legacy:words:$index"] = "Vyjmenovaná slova po $letter · Slova v řadě";
        }
        foreach ($this->curriculum->catalog as $subject => $entry) {
            foreach (array_keys($entry['grades'] ?? []) as $grade) {
                $course = $this->curriculum->course($subject, (string) $grade);
                $key = $course['id'];
                $labels[$key]['review'] = 'Opakování chyb';
                $labels[$key]['exam'] = 'Závěrečný test ročníku';
                foreach ($course['missions'] as $mission) {
                    $labels[$key]['mission:' . $mission['id']] = $mission['title'];
                    foreach ($mission['lessons'] as $lesson) {
                        $labels[$key]['lesson:' . $lesson['id']] = $lesson['name'];
                        $labels[$key]['practice:' . $lesson['id']] = $lesson['name'] . ' · Procvičování';
                    }
                }
            }
        }
        return $labels;
    }
}
