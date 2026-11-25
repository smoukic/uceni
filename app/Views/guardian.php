<section class="panel learning-panel">
    <h1>Rodičovská sekce</h1>
    <?php if ($operator): ?><p><a href="/sprava">Správa aplikace</a></p><?php endif ?>
    <div class="learning-actions">
        <form method="post" action="/rodic"><?php csrf() ?><button class="btn primary" name="action" value="invite">Vytvořit kód k propojení</button></form>
        <form method="post" action="/rodic"><?php csrf() ?><button class="btn secondary" name="action" value="logout">Odhlásit rodiče</button></form>
    </div>
    <details class="separate">
        <summary>Změnit heslo rodičovského účtu</summary>
        <form class="stack" method="post" action="/rodic">
            <?php csrf() ?>
            <label>Současné heslo <input required name="current_password" type="password" maxlength="72" autocomplete="current-password"></label>
            <label>Nové heslo <input required name="next_password" type="password" maxlength="72" autocomplete="new-password"></label>
            <label>Zopakujte nové heslo <input required name="confirm_password" type="password" maxlength="72" autocomplete="new-password"></label>
            <button class="btn primary" name="action" value="password">Uložit nové heslo</button>
        </form>
    </details>
    <?php if ($invite): ?>
        <p>Jednorázový kód platí 15 minut. Dítě ho zadá po přihlášení na stránce <strong>Odměny</strong>.</p>
        <p><code class="invite-code"><?= e($invite) ?></code></p>
    <?php endif ?>
    <?php if (!$children): ?><p>Zatím není propojený žádný dětský účet.</p><?php endif ?>
    <?php if ($children): ?>
        <nav class="challenge-switch" aria-label="Propojené účty">
            <?php foreach ($children as $id => $childName): ?>
                <a href="/rodic?dite=<?= e($id) ?>" <?= $child === (int) $id ? 'aria-current="page"' : '' ?>><?= e($childName) ?></a>
            <?php endforeach ?>
        </nav>
    <?php endif ?>
</section>
<?php if ($child): ?>
    <section class="panel learning-panel">
        <h2>Postup hráče <?= e($children[$child]) ?></h2>
        <?php require __DIR__ . '/learning-stats.php'; ?>
        <?php require __DIR__ . '/study-activity.php'; ?>
        <details class="separate"><summary>Nastavení ročníku a přístupu k vyšším ročníkům</summary>
            <?php $learnerId = $child; require __DIR__ . '/grade-form.php'; ?>
        </details>
        <?php if ($results): ?>
            <h3>Nejlepší výsledky</h3>
            <ul class="history-list">
                <?php foreach ($results as $result): ?>
                    <li><span><?= e($labels[$result['course']][$result['task']] ?? $result['task']) ?> <small><?= e($result['updated']) ?></small></span>
                        <span><strong><?= e($result['score']) ?> %</strong> · pokusů: <?= e($result['attempts']) ?></span></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
        <?php if ($attempts): ?>
            <details class="separate">
                <summary>Poslední pokusy</summary>
                <ul class="history-list">
                    <?php foreach ($attempts as $attempt): ?>
                        <li><span><?= e($labels[$attempt['course']][$attempt['task']] ?? $attempt['task']) ?> <small><?= e($attempt['day']) ?></small></span><strong><?= e($attempt['score']) ?> %</strong></li>
                    <?php endforeach ?>
                </ul>
            </details>
        <?php endif ?>
        <?php if ($history): ?>
            <details class="separate">
                <summary>Aktivita a získané XP</summary>
                <ul class="history-list">
                    <?php foreach ($history as $event): ?>
                        <?php $code = $event['source'];
                        $label = match (true) {
                            str_starts_with($code, 'cestina:order:') => 'Čeština · Správné pořadí · po ' . (['B', 'L', 'M', 'P', 'S', 'V', 'Z'][(int) substr($code, 14)] ?? '?'),
                            str_starts_with($code, 'cestina:words:') => 'Čeština · Slova v řadě · po ' . (['B', 'L', 'M', 'P', 'S', 'V', 'Z'][(int) substr($code, 14)] ?? '?'),
                            str_starts_with($code, 'visit:') => 'Denní návštěva',
                            str_starts_with($code, 'challenge:day:') => 'Denní výzva za učivo',
                            str_starts_with($code, 'challenge:week:') => 'Týdenní výzva',
                            str_starts_with($code, 'bonus:') => 'Bonus za zvládnutí učiva',
                            default => 'Zvládnuté učivo',
                        };
                        if (str_starts_with($code, 'course:')) {
                            [, $courseKey, $task] = explode(':', $code, 3);
                            $label = $labels[$courseKey][$task] ?? $label;
                        }
                        ?>
                        <li><span><?= e($label) ?> <small><?= e($event['day'] ?? 'Před zavedením odměn') ?></small></span><strong>+<?= e($event['xp']) ?> XP</strong></li>
                    <?php endforeach ?>
                </ul>
            </details>
        <?php endif ?>
    </section>
    <section class="panel learning-panel">
        <h2>Vlastní odměny</h2>
        <?php foreach ([...$rewards, ['id' => 0, 'title' => '', 'cost' => '', 'active' => 1]] as $reward): ?>
            <details class="reward-editor" <?= $reward['id'] ? '' : 'open' ?>>
                <summary><?= $reward['id'] ? e($reward['title']) . ' · ' . e($reward['cost']) . ' mincí' . ($reward['active'] ? '' : ' · neaktivní') : 'Přidat odměnu' ?></summary>
                <form class="stack" method="post" action="/rodic">
                    <?php csrf() ?>
                    <input type="hidden" name="child" value="<?= e($child) ?>">
                    <input type="hidden" name="item" value="<?= e($reward['id']) ?>">
                    <label>Název odměny <input name="title" value="<?= e($reward['title']) ?>" maxlength="100" required></label>
                    <label>Cena v mincích <input name="cost" type="number" value="<?= e($reward['cost']) ?>" min="1" max="100000" required></label>
                    <label class="inline-check"><input name="active" type="checkbox" value="1" <?= $reward['active'] ? 'checked' : '' ?>> Nabízet v obchodě</label>
                    <button class="btn primary" name="action" value="save">Uložit odměnu</button>
                </form>
            </details>
        <?php endforeach ?>
    </section>
    <section class="panel learning-panel">
        <h2>Žádosti a jejich historie</h2>
        <?php foreach ($claims as $claim): ?>
            <?php $actions = match ($claim['status']) {
                'pending' => ['approved' => 'Schválit', 'rejected' => 'Zamítnout a vrátit mince'],
                'approved' => ['fulfilled' => 'Označit jako splněnou', 'rejected' => 'Zamítnout a vrátit mince'],
                default => [],
            }; ?>
            <?php if ($actions): ?>
                <form class="guardian-claim" method="post" action="/rodic">
                    <?php csrf() ?>
                    <input type="hidden" name="child" value="<?= e($child) ?>">
                    <input type="hidden" name="item" value="<?= e($claim['id']) ?>">
                    <p><strong><?= e($claim['title']) ?></strong> · <?= e($claim['cost']) ?> mincí · <?= e($statuses[$claim['status']]) ?></p>
                    <div class="learning-actions">
                        <?php foreach ($actions as $action => $text): ?>
                            <button class="btn <?= $action === 'rejected' ? 'secondary' : 'primary' ?>" name="action" value="<?= e($action) ?>"><?= e($text) ?></button>
                        <?php endforeach ?>
                    </div>
                </form>
            <?php endif ?>
        <?php endforeach ?>
        <?php require __DIR__ . '/reward-history.php'; ?>
    </section>
<?php endif ?>
