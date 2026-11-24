<?php
declare(strict_types=1);

return static function (string $subject, int $grade): array {
    $rows = (require __DIR__ . "/$subject.php")[$grade];
    $topics = $missions = $lessons = [];
    $icons = ['🧭', '✏️', '🔎', '🧩', '🎧'];

    foreach ($rows as $id => [$title, $area, $rule, $tokens]) {
        $items = $examples = [];
        foreach (explode('|', $tokens) as $token) {
            [$prompt, $raw] = explode('~', $token, 2);
            $answers = $subject === 'matematika' && preg_match('~^-?\d+/\d+$~D', $raw)
                ? [$raw] : array_map('trim', explode('/', $raw));
            $kind = $subject === 'matematika' ? 'math' : 'fill';
            if ($subject === 'cestina' && (preg_match('/^(Oprav|Zapiš správně|Doplň čárk|Napiš velké)/u', $prompt) || in_array($raw, ['.', '?'], true))) {
                $kind = 'spelling';
            }
            $items[] = [$kind, $prompt, $answers, [], $rule, ''];
            if (count($examples) < 2) {
                $examples[] = "$prompt → " . $answers[0];
            }
        }
        // Short written answers and recognition alternate in early grades.
        if ($grade <= 2 && $subject === 'anglictina') {
            $pool = array_map(static fn(array $item): string => $item[2][0], $items);
            foreach ($items as $index => &$item) {
                $correct = $item[2][0];
                $distractors = array_values(array_diff($pool, $item[2]));
                $item[0] = 'choice';
                $item[3] = array_values(array_unique([$correct, ...array_slice($distractors, -3, 2)]));
            }
            unset($item);
        }
        $areaId = [
            'Jazyk' => 'jazyk', 'Psaní' => 'psani', 'Čtení' => 'cteni', 'Číslo a počet' => 'cisla',
            'Aplikace' => 'aplikace', 'Geometrie' => 'geometrie', 'Komunikace' => 'komunikace',
            'Slovní zásoba' => 'slovnik', 'Věty' => 'vety', 'Porozumění' => 'porozumeni',
        ][$area];
        $topics[$areaId] ??= [$area, []];
        $topics[$areaId][1][] = $id;
        $missions[$id] = [$icons[count($missions) % count($icons)], $title, $rule, [$id]];
        $lessons[$id] = [$title, 'Základ', $rule, $examples, $items];
    }

    if ($subject === 'cestina') {
        [$name, $rule, $lines] = (require __DIR__ . '/diktaty.php')[$grade];
        $items = [];
        foreach (explode('|', $lines) as $line) {
            $items[] = ['dictation', 'Poslechni větu a napiš ji včetně znamének.', $line, [], $rule, $line];
        }
        $topics['diktaty'] = ['Diktáty', ['diktat']];
        $missions['diktat'] = ['🎧', 'Diktát: ' . $name, $rule, ['diktat']];
        $lessons['diktat'] = [$name, 'Základ', $rule, ['Pusť si nahrávku víckrát. Po napsání zkontroluj začátek věty, háčky a čárky i znaménko na konci.'], $items];
    }
    return [$topics, $missions, $lessons];
};
