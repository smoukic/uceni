<?php
declare(strict_types=1);

final class Curriculum
{
    public readonly array $catalog;

    public function __construct(private string $directory)
    {
        $this->catalog = require $directory . '/catalog.php';
    }

    public function course(string $subject, string $grade): ?array
    {
        $entry = $this->catalog[$subject]['grades'][$grade] ?? null;
        if ($entry === null) {
            return null;
        }
        $folder = $this->directory . '/' . $entry['directory'];
        if ($entry['school']) {
            $build = require $this->directory . '/school/build.php';
            [$topics, $missions, $lessons] = $build($subject, (int) $grade);
            $speaking = [];
        } else {
            $topics = require $folder . '/topics.php';
            $missions = require $folder . '/missions.php';
            $lessons = require $folder . '/lessons.php';
            $speaking = is_file($folder . '/speaking.php') ? require $folder . '/speaking.php' : [];
        }
        $course = $entry + [
            'id' => "$subject/$grade",
            'url' => "/predmet/$subject/$grade",
            'subject' => $this->catalog[$subject]['title'],
            'topics' => $topics,
            'missions' => [],
            'items' => [],
        ];
        foreach ($topics as $topic => [$topicTitle, $missionIds]) {
            foreach ($missionIds as $missionId) {
                [$icon, $title, $intro, $ids] = $missions[$missionId];
                $mission = compact('icon', 'title', 'intro', 'topic') + ['id' => $missionId, 'lessons' => []];
                foreach ($ids as $id) {
                    [$name, $category, $explanation, $examples, $questions] = $lessons[$id];
                    $lesson = compact('id', 'name', 'category', 'explanation', 'examples') + [
                        'items' => [], 'speaking' => $speaking[$id] ?? null,
                    ];
                    foreach ($questions as $key => [$type, $prompt, $answer, $options, $why, $context]) {
                        $item = "$id-$key";
                        $course['items'][$item] = compact('type', 'prompt', 'answer', 'options', 'why', 'context');
                        $lesson['items'][] = $item;
                    }
                    $mission['lessons'][$id] = $lesson;
                }
                $course['missions'][$missionId] = $mission;
            }
        }
        return $course;
    }

    public static function check(array $item, mixed $answer): bool
    {
        if ($item['type'] === 'match') {
            if (!is_array($answer) || count($answer) !== count($item['answer'])) {
                return false;
            }
            foreach (array_values($item['answer']) as $index => $expected) {
                if (($answer[$index] ?? null) !== $expected) {
                    return false;
                }
            }
            return true;
        }

        if ($item['type'] === 'order') {
            if (!is_array($answer) || count($answer) !== count($item['options'])) {
                return false;
            }
            $words = [];
            foreach ($answer as $key) {
                if (!is_string($key) || !ctype_digit($key) || !isset($item['options'][(int) $key])) {
                    return false;
                }
                $words[] = $item['options'][(int) $key];
            }
            if (count(array_unique($answer)) !== count($answer)) {
                return false;
            }
            $answer = implode(' ', $words);
        }

        if (!is_string($answer) || strlen($answer) > 400) {
            return false;
        }
        if (in_array($item['type'], ['spelling', 'dictation'], true)) {
            return in_array(self::spelling($answer), array_map(self::spelling(...), (array) $item['answer']), true);
        }
        if ($item['type'] === 'math') {
            $value = self::math($answer);
            foreach ((array) $item['answer'] as $candidate) {
                $expected = self::math($candidate);
                if ($value === $expected) {
                    return true;
                }
                if (preg_match('~^(-?\d{1,8})/(\d{1,8})$~D', $value, $entered)
                    && preg_match('~^(-?\d{1,8})/(\d{1,8})$~D', $expected, $fraction)
                    && (int) $entered[2] > 0 && (int) $fraction[2] > 0
                    && (int) $entered[1] * (int) $fraction[2] === (int) $fraction[1] * (int) $entered[2]) {
                    return true;
                }
                if (is_numeric($value) && is_numeric($expected) && (float) $value === (float) $expected) {
                    return true;
                }
            }
            return false;
        }
        foreach ((array) $item['answer'] as $accepted) {
            if (self::normalize($accepted) === self::normalize($answer)) {
                return true;
            }
        }
        return false;
    }

    public static function writingHint(array $item, mixed $answer): string
    {
        if (!is_string($answer) || strlen($answer) > 400 || !in_array($item['type'], ['spelling', 'dictation'], true)) {
            return '';
        }
        $expected = self::spelling(((array) $item['answer'])[0]);
        $answer = self::spelling($answer);
        if (mb_strtolower($expected) === mb_strtolower($answer)) {
            return 'Slova máš správně. Zkontroluj velká a malá písmena.';
        }
        $unaccent = static fn(string $text): string => preg_replace('/\p{Mn}+/u', '', Normalizer::normalize($text, Normalizer::FORM_D) ?: '') ?? '';
        if ($unaccent($expected) === $unaccent($answer)) {
            return 'Zkontroluj háčky, čárky nad samohláskami a kroužek nad ů.';
        }
        $noPunctuation = static fn(string $text): string => preg_replace('/[,.!?;:]/u', '', $text) ?? '';
        if ($noPunctuation($expected) === $noPunctuation($answer)) {
            return 'Slova máš správně. Podívej se, kde ve větě patří čárka nebo znaménko na konci.';
        }
        return $item['type'] === 'dictation' ? 'Přehraj si větu znovu a porovnej zápis slovo po slovu.' : '';
    }

    private static function spelling(string $text): string
    {
        $text = Normalizer::normalize(trim($text)) ?: '';
        return preg_replace('/\s+/u', ' ', $text) ?? '';
    }

    private static function math(string $text): string
    {
        $text = str_replace(['−', ','], ['-', '.'], self::normalize($text));
        // Preserve the decimal point in written values and accept either decimal separator.
        return preg_replace('/\s+/u', '', $text) ?? '';
    }

    private static function normalize(string $text): string
    {
        $text = Normalizer::normalize(str_replace(['’', '‘', 'ʼ'], "'", $text)) ?: '';
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        $text = preg_replace('/\s*([,;:])\s*/u', '$1 ', $text) ?? '';
        return mb_strtolower(rtrim($text, " .!?"), 'UTF-8');
    }
}
