<?php
$courseNames = ['cestina' => 'Čeština', 'matematika' => 'Matematika', 'anglictina' => 'Angličtina'];
$formatTime = static function (int $seconds): string {
    if ($seconds < 60) {
        return "$seconds s";
    }
    return intdiv($seconds, 60) . ' min';
};
?>
<h2>Aktivita a úspěšnost</h2>
<div class="learning-stats">
    <div><strong><?= e($activity['totals']['days']) ?></strong><small>Studijní dny</small></div>
    <div><strong><?= e($formatTime((int) $activity['totals']['seconds'])) ?></strong><small>Odhad aktivního času</small></div>
    <div><strong><?= e($activity['totals']['answers']) ?></strong><small>Zodpovězené úlohy</small></div>
</div>
<p class="muted">Čas se počítá u odpovědí a zahrnuje nejvýše 90 sekund na úlohu. Starší výsledky bez měření zůstávají v přehledu XP.</p>
<?php if ($activity['days']): ?>
    <h3>Posledních 14 dní</h3>
    <ol class="study-chart">
        <?php foreach ($activity['days'] as $day): ?>
            <li><time datetime="<?= e($day['day']) ?>"><?= e(substr($day['day'], 5)) ?></time>
                <meter min="0" max="100" value="<?= e((int) $day['score']) ?>" aria-label="Průměrná úspěšnost"></meter>
                <strong><?= e((int) $day['score']) ?> %</strong><small><?= e($formatTime((int) $day['seconds'])) ?></small>
            </li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
<h3>Poslední procvičování, opakování a testy</h3>
<?php if (!$activity['recent']): ?><p>Jakmile zodpovíš cvičení, ukáže se tady.</p><?php endif ?>
<ul class="history-list">
    <?php foreach ($activity['recent'] as $activityRow): ?>
        <?php [$subjectKey, $year] = explode('/', $activityRow['course'], 2); ?>
        <li><span><strong><?= e($labels[$activityRow['course']][$activityRow['task']] ?? 'Vyjmenovaná slova') ?></strong>
            <small><?= e($courseNames[$subjectKey] ?? $subjectKey) ?> · <?= e($year) ?>. ročník · <?= e($activityRow['day']) ?><?= $activityRow['completed'] ? '' : ' · rozpracováno' ?></small></span>
            <span><?= e($activityRow['score']) ?> % · <?= e($activityRow['answered']) ?> odp. · <?= e($formatTime((int) $activityRow['seconds'])) ?></span></li>
    <?php endforeach ?>
</ul>
