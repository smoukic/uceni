<section class="panel narrow">
    <a class="back" href="<?= e($url('/')) ?>">Mapa misí</a>
    <p class="tag"><?= e($challengeName) ?> · PO <?= e($packs[$level]['letter']) ?></p>
    <h1><?= e($packs[$level]['title']) ?></h1>
    <?php if ($inProgress): ?>
        <p>Jednu misi už máš rozehranou. Dokonči ji a můžeš vyrazit dál.</p>
        <a class="btn primary" href="<?= e($url('/hra')) ?>">Pokračovat v rozehrané misi</a>
    <?php else: ?>
        <p>Čeká tě <?= count($packs[$level]['words']) ?> slov. <?= $wordsMode ? 'Vyber slova z kartiček, pak doplň jejich konce. Ve finále napiš všechna slova v libovolném pořadí.' : 'Seřaď kartičky, doplň chybějící slovo a na konci zkus celou řadu zpaměti.' ?></p>
        <?php if ($player): ?>
            <form action="<?= e($url('/mise/start')) ?>" method="post"><?php csrf() ?><input type="hidden" name="level" value="<?= $level ?>"><button class="btn primary">Spustit misi</button></form>
        <?php else: ?>
            <a class="btn primary" href="<?= e($url('/registrace')) ?>">Vytvořit hráče</a>
            <a class="btn secondary" href="<?= e($url('/prihlaseni')) ?>">Přihlásit se</a>
        <?php endif ?>
    <?php endif ?>
</section>

<?php if ($player && isset($progress[$level])): ?>
<section class="panel learning-panel narrow">
    <details>
        <summary>Začít část znovu</summary>
        <p>Ztratíš <strong><?= e($progress[$level]) ?> XP</strong> za tuto misi a další mise budou dočasně zamčené. Po novém zvládnutí se XP vrátí. Mince zůstávají.</p>
        <form method="post" action="<?= e($url('/restart-cestiny')) ?>" class="stack">
            <?php csrf() ?>
            <input type="hidden" name="level" value="<?= e($level) ?>"><input type="hidden" name="xp" value="<?= e($progress[$level]) ?>">
            <label class="inline-check"><input type="checkbox" name="confirm" value="1" required> Rozumím a chci misi restartovat.</label>
            <button class="btn secondary">Potvrdit restart</button>
        </form>
    </details>
</section>
<?php endif ?>
