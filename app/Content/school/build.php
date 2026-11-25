<?php
declare(strict_types=1);

return static function (string $subject, int $grade): array {
    $core = in_array($subject, ['cestina', 'matematika', 'anglictina'], true);
    $additions = $core ? ((require __DIR__ . "/$subject-navic.php")[$grade] ?? []) : [];
    $rows = $core ? (require __DIR__ . "/$subject.php")[$grade] + $additions : (require __DIR__ . '/obory.php')[$subject][$grade];
    $topics = $missions = $lessons = [];
    $icons = ['🧭', '✏️', '🔎', '🧩', '🎧'];

    foreach ($rows as $id => $entry) {
        [$title, $area, $rule, $tokens] = $entry;
        $context = $entry[4] ?? '';
        $items = $examples = [];
        foreach (explode('|', $tokens) as $token) {
            [$prompt, $raw] = explode('~', $token, 2);
            $answers = $subject === 'matematika' && preg_match('~^-?\d+/\d+$~D', $raw)
                ? [$raw] : array_map('trim', explode('/', $raw));
            $kind = $subject === 'matematika' ? 'math' : ($context !== '' ? 'read' : 'fill');
            if ($subject === 'cestina' && (preg_match('/^(Oprav|Zapiš správně|Doplň čárk|Napiš velké)/u', $prompt) || in_array($raw, ['.', '?'], true))) {
                $kind = 'spelling';
            }
            $items[] = [$kind, $prompt, $answers, [], $rule, $context];
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
            'Literatura' => 'literatura', 'Základy' => 'zaklady', 'Souvislosti' => 'souvislosti', 'Dovednosti' => 'dovednosti',
        ][$area];
        $topics[$areaId] ??= [$area, []];
        $topics[$areaId][1][] = $id;
        $missions[$id] = [$icons[count($missions) % count($icons)], $title, $rule, [$id], false, isset($additions[$id])];
        $lessons[$id] = [$title, 'Základ', $rule, $examples, $items];
    }

    if ($subject === 'cestina' && $grade === 3) {
        $blocks = require __DIR__ . '/pady.php';
        $lessons += $blocks;
        $lessons['pad'][0] = 'Pády ve větách a číslo';
        $missions['pad'][1] = 'Pádová výprava';
        $missions['pad'][2] = 'Prohlédni si otázky, poskládej je z kartiček a napiš je zpaměti. Nakonec si pády vyzkoušej ve větách.';
        $missions['pad'][3] = [...array_keys($blocks), 'pad'];
        $missions['pad'][4] = true;
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
