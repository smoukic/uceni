<?php
$subjects = [
    'cestina' => ['title' => 'Čeština', 'language' => 'cs-CZ', 'prefix' => 'cs', 'icon' => '📝'],
    'matematika' => ['title' => 'Matematika', 'language' => 'cs-CZ', 'prefix' => 'ma', 'icon' => '📐'],
    'anglictina' => ['title' => 'Angličtina', 'language' => 'en-GB', 'prefix' => 'en', 'icon' => '🌍'],
    'clovek-a-svet' => ['title' => 'Člověk a jeho svět', 'language' => 'cs-CZ', 'prefix' => 'sv', 'icon' => '🧭', 'years' => [1, 5]],
    'informatika' => ['title' => 'Informatika', 'language' => 'cs-CZ', 'prefix' => 'in', 'icon' => '💻', 'years' => [4, 9]],
    'fyzika' => ['title' => 'Fyzika', 'language' => 'cs-CZ', 'prefix' => 'fy', 'icon' => '⚡', 'years' => [6, 9]],
    'dejepis' => ['title' => 'Dějepis', 'language' => 'cs-CZ', 'prefix' => 'de', 'icon' => '🏛️', 'years' => [6, 9]],
    'zemepis' => ['title' => 'Zeměpis', 'language' => 'cs-CZ', 'prefix' => 'ze', 'icon' => '🗺️', 'years' => [6, 9]],
    'prirodopis' => ['title' => 'Přírodopis', 'language' => 'cs-CZ', 'prefix' => 'pr', 'icon' => '🌱', 'years' => [6, 9]],
    'obcanka' => ['title' => 'Občanská výchova', 'language' => 'cs-CZ', 'prefix' => 'ov', 'icon' => '🤝', 'years' => [6, 9]],
    'chemie' => ['title' => 'Chemie', 'language' => 'cs-CZ', 'prefix' => 'ch', 'icon' => '⚗️', 'years' => [8, 9]],
];
foreach ($subjects as $id => &$subject) {
    [$first, $last] = $subject['years'] ?? [1, 9];
    for ($grade = $first; $grade <= $last; $grade++) {
        $subject['grades'][$grade] = [
            'title' => "$grade. ročník", 'grade' => $grade, 'directory' => $subject['prefix'] . '-' . $grade,
            'language' => $subject['language'], 'school' => $id !== 'anglictina' || $grade !== 6,
        ];
    }
}
unset($subject);
return $subjects;
