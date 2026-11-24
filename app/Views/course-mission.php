<section class="panel learning-panel">
    <a class="back" href="<?= e($course['url']) ?>">Mapa ročníku</a>
    <p class="tag"><?= e($course['subject']) ?> · <?= e($course['title']) ?></p>
    <h1><?= e($mission['title']) ?></h1>
    <p><?= e($mission['intro']) ?></p>
    <ol class="missions lesson-list">
        <?php foreach ($mission['lessons'] as $id => $lesson): ?>
            <?php $score = $results['lesson:' . $id] ?? null; ?>
            <li class="mission-row <?= $score !== null && $score >= 80 ? 'done' : '' ?>">
                <div class="mission-name"><small><?= e($lesson['category']) ?></small><h3><?= e($lesson['name']) ?></h3></div>
                <a class="btn secondary" href="<?= e($missionUrl . '/' . $id) ?>" aria-label="<?= e('Otevřít lekci ' . $lesson['name']) ?>"><?= $score === null ? 'Otevřít' : e($score) . ' %' ?></a>
            </li>
        <?php endforeach ?>
    </ol>
    <h2>Test mise</h2>
    <?php if ($ready): ?>
        <p>Na odemčení další mise potřebuješ alespoň 80 %. Otázky se při novém pokusu promíchají.</p>
        <form method="post" action="<?= e($missionUrl) ?>"><?php csrf() ?><button class="btn primary">Spustit test mise</button></form>
    <?php else: ?>
        <p>Zvládni ověření v lekcích označených <strong>Základ</strong> a <strong>Opakování</strong>. Pak se otevře test mise.</p>
    <?php endif ?>
</section>

<?php if ($reset): ?>
<section class="panel learning-panel">
    <details>
        <summary>Začít tuto misi znovu</summary>
        <p>Ztratíš <strong><?= e($reset['xp']) ?> XP</strong> za vybrané učivo<?= $reset['exam'] ? ' a související závěrečný test' : '' ?>. Dosavadní pokusy zůstanou v historii. Při novém zvládnutí se XP vrátí. Zůstatek mincí se restartem nemění.</p>
        <form method="post" class="stack" action="<?= e($missionUrl) ?>">
            <?php csrf() ?>
            <input type="hidden" name="xp" value="<?= e($reset['key']) ?>">
            <label class="inline-check"><input type="checkbox" name="confirm" value="1" required> Rozumím a chci misi restartovat.</label>
            <button class="btn secondary" name="action" value="reset">Potvrdit restart</button>
        </form>
    </details>
</section>
<?php endif ?>
