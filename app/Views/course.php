<?php
$completed = count(array_filter(array_keys($course['missions']), static fn($key) => ($results['mission:' . $key] ?? 0) >= 80));
$nextMission = array_key_first($course['missions']);
foreach ($course['missions'] as $id => $entry) {
    if ($access[$id] && $id !== 'diktat' && ($results['mission:' . $id] ?? 0) < 80) {
        $nextMission = $id;
        break;
    }
}
[$intro, $symbol, $icon] = match ($course['subject']) {
    'Matematika' => ['Počítej, hledej pravidla a řeš krátké příběhy. Každý zvládnutý test otevírá další misi.', '∑', '📐'],
    'Čeština' => ['Trénuj pravopis, čti krátké texty a zkus si diktát. Každý zvládnutý test otevírá další misi.', 'Č', '✏️'],
    default => ['Trénuj na krátkých rozhovorech, větách a příbězích. Zvládnutím testu otevřeš další misi.', 'Hi!', '🔊'],
};
?>
<section class="hero">
    <div class="hero-copy">
        <a class="back" href="/predmety?trida=<?= e($course['grade']) ?>">Předměty</a>
        <p class="tag"><?= e($course['title']) ?></p>
        <h1><?= e($course['subject']) ?></h1>
        <p><?= e($intro) ?></p>
        <div class="learning-actions">
            <a class="btn primary" href="<?= e($course['url'] . '/' . $nextMission) ?>">Na misi</a>
            <a class="btn secondary" href="<?= e($course['url'] . '/opakovani') ?>">Opakování<?= $due ? ' · ' . count($due) : '' ?></a>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true"><span class="orbit orbit-one"></span><span class="tile tile-y"><?= e($symbol) ?></span><span class="tile tile-i"><?= e($icon) ?></span></div>
</section>
<section class="map" aria-labelledby="course-map">
    <div class="section-title"><h2 id="course-map">Mapa misí</h2><span><?= $completed ?> / <?= count($course['missions']) ?> hotovo</span></div>
    <progress value="<?= $completed ?>" max="<?= count($course['missions']) ?>" aria-label="Dokončené mise"></progress>
    <ol class="missions">
        <?php foreach ($course['missions'] as $key => $mission): ?>
            <?php $done = ($results['mission:' . $key] ?? 0) >= 80; ?>
            <li class="mission-row <?= !$access[$key] ? 'locked' : ($done ? 'done' : 'active') ?>">
                <span class="mission-icon" aria-hidden="true"><?= e($mission['icon']) ?></span>
                <div class="mission-name"><h3><?= e($mission['title']) ?></h3><small><?= $done ? 'Zvládnuto · ' . e($results['mission:' . $key]) . ' %' : $course['topics'][$mission['topic']][0] . ' · ' . count($mission['lessons']) . ' lekce' ?></small></div>
                <?php if ($access[$key]): ?>
                    <a class="btn <?= $done ? 'secondary' : 'primary' ?>" href="<?= e($course['url'] . '/' . $key) ?>"><?= $done ? 'Zopakovat' : 'Otevřít misi' ?></a>
                <?php else: ?>
                    <span class="lock">Zamčeno</span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
        <li class="mission-row <?= $allPassed ? 'active' : 'locked' ?>">
            <span class="mission-icon" aria-hidden="true">🏁</span>
            <div class="mission-name"><h3>Závěrečný test ročníku</h3><?php if (isset($results['exam'])): ?><small>Nejlepší výsledek <?= e($results['exam']) ?> %</small><?php endif ?></div>
            <?php if ($allPassed): ?><a class="btn primary" href="<?= e($course['url'] . '/zaverecny-test') ?>">Otevřít test</a><?php else: ?><span class="lock">Po dokončení misí</span><?php endif ?>
        </li>
    </ol>
</section>
