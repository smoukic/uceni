<section class="panel narrow">
    <p class="tag">SOUTĚŽ SE SPOLUŽÁKY</p>
    <h1>Moje parta</h1>
    <?php if ($player['team']): ?>
        <p>Pošli spolužákům kód a porovnejte svoje skóre.</p>
        <div class="study"><small>Pozvánkový kód</small><p class="invite"><?= e($player['team']) ?></p></div>
        <h2>Žebříček · <?= e($challengeName) ?></h2>
        <ol class="rankings">
            <?php foreach ($rankings as $entry): ?>
                <li><span><?= e($entry['handle']) ?></span><strong><?= e($entry['score']) ?> XP</strong></li>
            <?php endforeach ?>
        </ol>
        <form class="separate" action="<?= e($url('/parta')) ?>" method="post"><?php csrf() ?><button class="btn secondary" name="action" value="leave">Opustit partu</button></form>
    <?php else: ?>
        <p>Založ partu nebo se přidej pomocí kódu. V žebříčku se zobrazí tvoje přezdívka a XP.</p>
        <form action="<?= e($url('/parta')) ?>" method="post"><?php csrf() ?><button class="btn primary" name="action" value="create">Založit partu</button></form>
        <form class="stack separate" action="<?= e($url('/parta')) ?>" method="post">
            <?php csrf() ?>
            <label for="code">Pozvánkový kód</label>
            <input id="code" name="code" required maxlength="10" pattern="[0-9A-Fa-f]{10}" autocomplete="off" spellcheck="false">
            <button class="btn secondary" name="action" value="join">Přidat se k partě</button>
        </form>
    <?php endif ?>
</section>
