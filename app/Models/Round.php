<?php
declare(strict_types=1);

final class Round
{
    public const BLOCK_SIZE = 3;
    public const REWARD = 100;

    public static function begin(int $level): array
    {
        return [
            'level' => $level,
            'offset' => 0,
            'phase' => 'study',
            'token' => bin2hex(random_bytes(16)),
            'attempt' => bin2hex(random_bytes(16)),
            'shownAt' => time(),
            'seconds' => 0,
            'answered' => 0,
            'correct' => 0,
        ];
    }

    public static function block(array $round, array $words): array
    {
        return array_slice($words, $round['offset'], self::BLOCK_SIZE);
    }

    public static function trail(array $round, array $words): array
    {
        $start = max(0, $round['offset'] - self::BLOCK_SIZE);
        return array_slice($words, $start, $round['offset'] - $start + count(self::block($round, $words)), true);
    }

    public static function cards(array $correct, array $pool, int $decoys = 1): array
    {
        $extras = array_values(array_diff($pool, $correct));
        shuffle($extras);
        $cards = array_merge($correct, array_slice($extras, 0, $decoys));
        shuffle($cards);
        if (count($cards) > 1 && array_slice($cards, 0, count($correct)) === $correct) {
            $first = array_shift($cards);
            $cards[] = $first;
        }
        return $cards;
    }

    public static function move(array &$round, string $phase): void
    {
        $round['phase'] = $phase;
        $round['shownAt'] = time();
        $round['token'] = bin2hex(random_bytes(16));
    }

    private static function normalize(string $word): string
    {
        $word = preg_replace('/\s+/u', ' ', trim($word)) ?? '';
        return mb_strtolower(Normalizer::normalize($word) ?: '', 'UTF-8');
    }

    public static function prefix(string $word): string
    {
        return mb_substr($word, 0, intdiv(mb_strlen($word), 2));
    }

    public static function completeFragments(array $words, mixed $parts): mixed
    {
        if (!is_array($parts) || array_keys($words) !== array_keys($parts)) {
            return null;
        }
        foreach ($parts as $index => $part) {
            if (!is_string($part) || trim($part) === '') {
                return null;
            }
            $parts[$index] = self::prefix($words[$index]) . $part;
        }
        return $parts;
    }

    public static function check(array $words, mixed $answers, bool $anyOrder = false): ?array
    {
        if (!is_array($answers) || array_keys($words) !== array_keys($answers)) {
            return null;
        }
        foreach ($answers as $answer) {
            if (!is_string($answer) || strlen($answer) > 120 || self::normalize($answer) === '') {
                return null;
            }
        }
        if ($anyOrder) {
            $remaining = array_map(self::normalize(...), $words);
            $extra = [];
            foreach ($answers as $answer) {
                $position = array_search(self::normalize($answer), $remaining, true);
                if ($position === false) {
                    $extra[] = $answer;
                } else {
                    unset($remaining[$position]);
                }
            }
            $mistakes = [];
            foreach (array_keys($remaining) as $index => $position) {
                $mistakes[$position] = ['expected' => $words[$position], 'entered' => $extra[$index]];
            }
            return $mistakes;
        }
        $mistakes = [];
        foreach ($words as $position => $word) {
            $answer = $answers[$position];
            if (self::normalize($answer) !== self::normalize($word)) {
                $mistakes[$position] = ['expected' => $word, 'entered' => $answer];
            }
        }
        return $mistakes;
    }
}
