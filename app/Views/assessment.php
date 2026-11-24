<section class="panel learning-panel narrow">
    <a class="back" href="<?= e($course['url']) ?>">Mapa ročníku</a>
    <h1><?= e($heading) ?></h1>
    <?php if ($items): ?>
        <p><?= $review ? 'Vrať se k úlohám, které ti dřív dělaly potíže. Správně zodpovězené otázky si připomeneš ještě další den.' : 'Čekají tě otázky ze všech misí. K dokončení ročníku potřebuješ alespoň 80 %.' ?></p>
        <p>Počet otázek: <?= count($items) ?></p>
        <form method="post"><?php csrf() ?><button class="btn primary"><?= $review ? 'Začít opakování' : 'Začít závěrečný test' ?></button></form>
    <?php else: ?>
        <p>Všechny otázky, které jsou na dnešek připravené, máš hotové.</p>
    <?php endif ?>
</section>
