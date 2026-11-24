<?php if (!$claims && !$coins): ?>
    <p>Zatím tu nejsou žádné nákupy ani transakce.</p>
<?php endif ?>
<?php if ($claims): ?>
    <h3>Žádosti o odměny</h3>
    <ul class="history-list">
        <?php foreach ($claims as $claim): ?>
            <li><span><strong><?= e($claim['title']) ?></strong> · <?= e($claim['cost']) ?> mincí</span>
                <span><?= e($statuses[$claim['status']]) ?></span>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
<?php if ($coins): ?>
    <h3>Pohyby mincí</h3>
    <ul class="history-list">
        <?php foreach ($coins as $entry): ?>
            <li><span><?= e($entry['label']) ?> <small><?= e(substr($entry['created'], 0, 10)) ?></small></span>
                <strong><?= $entry['amount'] > 0 ? '+' : '' ?><?= e($entry['amount']) ?></strong></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
