<?php $categories = ['avatar' => 'Avatar', 'frame' => 'Rámeček', 'title' => 'Titul', 'theme' => 'Motiv', 'pin' => 'Ozdoba profilu']; ?>
<section class="panel learning-panel">
    <h1>Odměny</h1>
    <p>Za zvládnuté učivo získáváš XP i mince. Mince můžeš utratit, XP ti zůstávají.</p>
    <?php require __DIR__ . '/learning-stats.php'; ?>
</section>

<section class="panel learning-panel">
    <h2>Obchod</h2>
    <ul class="reward-grid">
        <?php foreach ($cosmetics as $id => [$category, $title, $value, $cost, $available]): ?>
            <?php $purchased = in_array($id, $owned, true); $active = ($equipped[$category] ?? null) === $id; ?>
            <?php if (!$purchased && !$available) { continue; } ?>
            <li>
                <span class="reward-preview <?= $category === 'frame' ? e('frame-' . $value) : ($category === 'theme' ? e('swatch-' . $value) : '') ?>" aria-hidden="true"><?= in_array($category, ['avatar', 'pin'], true) ? e($value) : '✦' ?></span>
                <small><?= e($categories[$category]) ?></small>
                <h3><?= e($title) ?></h3>
                <p><?= $active ? 'Používáš' : ($purchased ? 'Zakoupeno' : e($cost) . ' mincí') ?></p>
                <form method="post">
                    <?php csrf() ?><input type="hidden" name="item" value="<?= e($id) ?>">
                    <button class="btn <?= $purchased ? 'secondary' : 'primary' ?>" name="action" value="<?= $active ? 'remove' : ($purchased ? 'equip' : 'buy') ?>" <?= !$purchased && $summary['balance'] < $cost ? 'disabled' : '' ?>><?= $active ? 'Přestat používat' : ($purchased ? 'Použít' : 'Koupit') ?></button>
                </form>
            </li>
        <?php endforeach ?>
    </ul>
</section>
<section class="panel learning-panel">
    <h2>Odměny od rodiče</h2>
    <?php if (!$linked): ?>
        <p>Pokud ti rodič dal kód pro propojení, zadej ho sem.</p>
        <form class="stack" method="post">
            <?php csrf() ?><label>Kód <input name="code" required maxlength="24" autocomplete="off"></label>
            <button class="btn secondary" name="action" value="link">Propojit účty</button>
        </form>
    <?php elseif (!$rewards): ?>
        <p>Rodič zatím nepřidal žádnou odměnu.</p>
    <?php else: ?>
        <ul class="reward-grid">
            <?php foreach ($rewards as $reward): ?>
                <li><h3><?= e($reward['title']) ?></h3><p><?= e($reward['cost']) ?> mincí</p>
                    <form method="post">
                        <?php csrf() ?><input type="hidden" name="item" value="<?= e($reward['id']) ?>">
                        <input type="hidden" name="request" value="<?= bin2hex(random_bytes(16)) ?>">
                        <button class="btn primary" name="action" value="claim" <?= $reward['open'] || $summary['balance'] < $reward['cost'] ? 'disabled' : '' ?>><?= $reward['open'] ? 'Žádost probíhá' : 'Požádat o odměnu' ?></button>
                    </form>
                </li>
            <?php endforeach ?>
        </ul>
        <p class="muted">Při žádosti mince rezervujeme. Pokud rodič žádost zamítne, vrátí se ti.</p>
    <?php endif ?>
</section>

<section class="panel learning-panel">
    <h2>Historie odměn a mincí</h2>
    <?php require __DIR__ . '/reward-history.php'; ?>
</section>
