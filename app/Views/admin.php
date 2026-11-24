<section class="panel learning-panel">
    <a class="back" href="/rodic">Rodičovská sekce</a>
    <h1>Správa aplikace</h1>
    <h2>Ročníky hráče</h2>
    <form class="stack" method="get">
        <label>Přezdívka hráče <input name="hledat" value="<?= e($search) ?>" maxlength="20" required></label>
        <button class="btn secondary">Vyhledat</button>
    </form>
    <?php if ($search !== '' && !$learners): ?><p>Řetězci neodpovídá žádný hráč.</p><?php endif ?>
    <?php foreach ($learners as $id => $name): ?>
        <details class="reward-editor"><summary><?= e($name) ?></summary>
            <?php $gradeSetting = $settings[$id]; $learnerId = $id; ?>
            <?php require __DIR__ . '/grade-form.php'; ?>
        </details>
    <?php endforeach ?>
</section>
<section class="panel learning-panel">
    <h2>Katalog virtuálních odměn</h2>
    <p>U vlastněných odměn se zachovává typ. Deaktivované odměny lze dál používat, pokud už byly zakoupené.</p>
    <?php $categories = ['avatar' => 'Avatar', 'frame' => 'Rámeček', 'title' => 'Titul', 'theme' => 'Motiv', 'pin' => 'Ozdoba']; ?>
    <?php foreach ($products + ['' => ['avatar', '', '', '', true]] as $id => [$category, $title, $value, $price, $active]): ?>
        <details class="reward-editor"><summary><?= $id ? e($title) . ' · ' . e($price) . ' mincí' . ($active ? '' : ' · skryto') : 'Přidat odměnu' ?></summary>
            <form method="post" class="stack">
                <?php csrf() ?>
                <label>ID <input name="item" value="<?= e($id) ?>" pattern="[a-z0-9\-]{2,40}" required <?= $id ? 'readonly' : '' ?>></label>
                <label>Typ <select name="category"><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
                <label>Název <input name="title" value="<?= e($title) ?>" maxlength="80" required></label>
                <label>Hodnota <input name="value" value="<?= e($value) ?>" maxlength="32" required></label>
                <small>Pro avatar a ozdobu použijte emoji, pro titul text. Rámeček má kód mint, gold, rose nebo sky. Motiv má kód forest, night, berry nebo ocean.</small>
                <label>Cena <input name="cost" type="number" min="1" max="100000" value="<?= e($price) ?>" required></label>
                <label class="inline-check"><input name="active" type="checkbox" value="1" <?= $active ? 'checked' : '' ?>> Zobrazit v obchodě</label>
                <button class="btn primary" name="action" value="cosmetic">Uložit odměnu</button>
            </form>
        </details>
    <?php endforeach ?>
</section>
