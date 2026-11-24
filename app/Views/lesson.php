<section class="panel learning-panel narrow">
    <a class="back" href="<?= e($missionUrl) ?>">Zpět na misi</a>
    <p class="tag"><?= e($lesson['category']) ?></p>
    <h1><?= e($lesson['name']) ?></h1>
    <p><?= e($lesson['explanation']) ?></p>
    <h2>Příklady</h2>
    <ul class="example-list">
        <?php foreach ($lesson['examples'] as $example): ?>
            <li><?= e($example) ?></li>
        <?php endforeach ?>
    </ul>
    <?php if ($lesson['speaking']): ?>
        <h2>Zkus to nahlas</h2>
        <p><?= e($lesson['speaking'][0]) ?></p>
        <ul class="example-list" lang="<?= e($course['language']) ?>">
            <?php foreach (array_slice($lesson['speaking'], 1) as $line): ?><li><?= e($line) ?></li><?php endforeach ?>
        </ul>
    <?php endif ?>
    <div class="learning-actions">
        <form method="post" action="<?= e($lessonUrl) ?>"><?php csrf() ?><button class="btn primary" name="action" value="practice">Procvičit</button></form>
        <form method="post" action="<?= e($lessonUrl) ?>"><?php csrf() ?><button class="btn secondary" name="action" value="quiz">Ověřit znalosti</button></form>
    </div>
    <?php if (isset($results['lesson:' . $lesson['id']])): ?><p>Nejlepší výsledek ověření: <?= e($results['lesson:' . $lesson['id']]) ?> %</p><?php endif ?>
</section>
