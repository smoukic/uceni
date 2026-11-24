<div class="learning-stats">
    <div><strong><?= e($summary['xp']) ?> XP</strong><small>Celkové zkušenosti</small></div>
    <div><strong>Úroveň <?= e($summary['level']) ?></strong><small><?= e($summary['title']) ?></small></div>
    <div><strong><?= e($summary['balance']) ?></strong><small>Mince k využití</small></div>
    <div><strong><?= e($summary['current']) ?> dní</strong><small>Série · jeden den volna je v pořádku</small></div>
</div>
<p class="muted">Rekord série je <?= e($summary['best']) ?> studijních dnů. Nejvíc za den: <?= e($summary['record']) ?> XP.</p>
<h3>Výzvy</h3>
<ul>
    <li>Denní návštěva · 5 XP za první přihlášenou návštěvu dne</li>
    <li>Dnes zvládni nové učivo · <?= min(1, (int) $summary['challenges']['daily']) ?> / 1</li>
    <li>Tento týden zvládni čtyři lekce nebo mise ve třech dnech · <?= min(4, (int) $summary['challenges']['weekly']) ?> / 4, <?= min(3, (int) $summary['challenges']['days']) ?> / 3 dní</li>
</ul>
<?php if ($summary['badges']): ?>
    <h3>Odznaky</h3>
    <ul class="badge-list"><?php foreach ($summary['badges'] as $badge): ?><li><?= e($badge) ?></li><?php endforeach ?></ul>
<?php endif ?>
