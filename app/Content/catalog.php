<?php
$subjects = [
    'cestina' => ['title' => 'Čeština', 'language' => 'cs-CZ', 'prefix' => 'cs', 'icon' => '📝'],
    'matematika' => ['title' => 'Matematika', 'language' => 'cs-CZ', 'prefix' => 'ma', 'icon' => '📐'],
    'anglictina' => ['title' => 'Angličtina', 'language' => 'en-GB', 'prefix' => 'en', 'icon' => '🌍'],
];
foreach ($subjects as $id => &$subject) {
    for ($grade = 1; $grade <= 9; $grade++) {
        $subject['grades'][$grade] = [
            'title' => "$grade. ročník", 'grade' => $grade, 'directory' => $subject['prefix'] . '-' . $grade,
            'language' => $subject['language'], 'school' => $id !== 'anglictina' || $grade !== 6,
        ];
    }
}
unset($subject);
return $subjects;
