<?php
$level = $round['level'];
$pack = $packs[$level];
$words = $pack['words'];
$block = Round::block($round, $words);
$phase = $round['phase'];
$final = $phase === 'final';
$fragments = $phase === 'fragments';
$highlight = static fn(string $word): string => preg_replace('/[yý]/u', '<mark>$0</mark>', e($word));
$titles = [
    'study' => $wordsMode ? 'Prohlédni si slova' : 'Objev nový úsek',
    'practice' => $wordsMode ? 'Která slova tam patří?' : 'Poskládej řetěz',
    'fragments' => 'Dokonči slova',
    'gap' => 'Najdi chybějící článek',
    'ready' => 'Finále mise!',
    'final' => $wordsMode ? 'Napiš všechna slova' : 'Napiš celou řadu',
    'retry' => 'Zkus celou řadu ještě jednou',
    'won' => 'Mise splněna!',
];
?>
<section class="panel narrow game">
    <a class="back" href="<?= e($url('/')) ?>">Mapa misí</a>
    <p class="tag"><?= e($challengeName) ?> · PO <?= e($pack['letter']) ?></p>
    <?php if (in_array($phase, ['study', 'practice', 'gap', 'fragments'], true)): ?>
        <div class="section-title"><span><?= $wordsMode ? 'Zvládnutá slova' : 'Postavené články' ?> <?= $round['offset'] ?> z <?= count($words) ?></span></div>
        <progress value="<?= $round['offset'] ?>" max="<?= count($words) ?>" aria-label="Zvládnutá slova v tréninku"></progress>
        <ol class="steps" aria-label="Výzvy v tomto úseku">
            <?php foreach (($wordsMode ? ['study' => 'Prohlédni', 'practice' => 'Vyber', 'fragments' => 'Dopiš'] : ['study' => 'Prohlédni', 'practice' => 'Poskládej', 'gap' => 'Doplň']) as $step => $label): ?>
                <li <?= $step === $phase ? 'aria-current="step"' : '' ?>><?= e($label) ?></li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>
    <h1><?= e($titles[$phase]) ?></h1>
    <?php if ($phase === 'study'): ?>
        <?php if ($result): ?>
            <p class="notice" role="status">
                <?php if (isset($round['missedLink'])): ?>
                    Na místo <?= $round['missedLink'] + 1 ?> patří <strong><?= $highlight($words[$round['missedLink']]) ?></strong>. Zkus úsek znovu.
                <?php elseif ($wordsMode): ?>
                    Ještě si procvič <?= e(implode(', ', array_column($result, 'expected'))) ?>.
                <?php else: ?>
                    Kartičky se pomíchaly. Mrkni na pořadí a zkus to znovu.
                <?php endif ?>
            </p>
        <?php elseif ($round['offset'] > 0): ?>
            <p class="game-success" role="status">✦ <?= $wordsMode ? 'Skupina zvládnuta!' : 'Úsek hotový! Připoj další slova.' ?></p>
        <?php endif ?>
        <p><?= $wordsMode ? 'Prohlédni si kartičky a zapamatuj si, která slova jsou v téhle skupině.' : 'Přečti si kartičky po řadě. Pak je zamícháme.' ?></p>
        <?php if (!$wordsMode && $round['offset'] > 0): ?><p class="trail-context">Navazuješ na <strong><?= $highlight($words[$round['offset'] - 1]) ?></strong></p><?php endif ?>
        <ol class="trail" start="<?= $round['offset'] + 1 ?>">
            <?php foreach ($block as $position => $word): ?>
                <li><?php if (!$wordsMode): ?><span class="tile-number"><?= $round['offset'] + $position + 1 ?></span><?php endif ?><strong><?= $highlight($word) ?></strong></li>
            <?php endforeach ?>
        </ol>
    <?php elseif ($phase === 'practice'): ?>
        <p><?= $wordsMode ? 'Klepni na slova z minulé obrazovky. Na pořadí nezáleží a mezi kartičkami jsou i slova navíc.' : 'Klepni na kartičky v pořadí, v jakém patří do řady. Jedna je navíc.' ?></p>
        <form class="card-game" action="<?= e($url('/hra/odpoved')) ?>" method="post" data-chain>
            <?php csrf() ?><input type="hidden" name="token" value="<?= e($round['token']) ?>">
            <ol class="trail chain-slots" aria-label="<?= $wordsMode ? 'Vybraná slova' : 'Tvoje pořadí' ?>">
                <?php foreach (array_keys($block) as $position): ?>
                    <li><?php if (!$wordsMode): ?><span class="tile-number"><?= $round['offset'] + $position + 1 ?></span><?php endif ?><strong data-slot>Volné místo</strong><input type="hidden" name="words[<?= $position ?>]" data-answer></li>
                <?php endforeach ?>
            </ol>
            <div class="card-bank" aria-label="Slovní kartičky">
                <?php $pool = $wordsMode ? array_diff(array_merge(...array_column($packs, 'words')), $words) : $words; ?>
                <?php foreach (Round::cards($block, $pool, $wordsMode ? 3 : 1) as $card): ?>
                    <button class="word-card" type="button" value="<?= e($card) ?>" data-card><?= $highlight($card) ?></button>
                <?php endforeach ?>
            </div>
            <p class="selection-status" role="status" data-status>Vybráno 0 z <?= count($block) ?></p>
            <div class="game-actions">
                <button class="btn primary" data-check disabled><?= $wordsMode ? 'Zkontrolovat výběr' : 'Zkontrolovat řetěz' ?></button>
                <button class="btn secondary" type="button" data-undo disabled>Vrátit poslední</button>
            </div>
        </form>
        <noscript><p>Pro skládání kartiček zapni JavaScript v prohlížeči.</p></noscript>
    <?php elseif ($phase === 'gap'): ?>
        <p class="game-success" role="status">✦ Řetěz drží!</p>
        <p>Které slovo patří na místo s otazníkem?</p>
        <ol class="trail" start="<?= array_key_first(Round::trail($round, $words)) + 1 ?>">
            <?php foreach (Round::trail($round, $words) as $position => $word): ?>
                <li <?= $position === $round['gap'] ? 'class="gap-slot"' : '' ?>>
                    <span class="tile-number"><?= $position + 1 ?></span>
                    <strong><?= $position === $round['gap'] ? '? <span class="sr-only">Chybějící slovo</span>' : $highlight($word) ?></strong>
                </li>
            <?php endforeach ?>
        </ol>
        <form class="card-bank" action="<?= e($url('/hra/odpoved')) ?>" method="post">
            <?php csrf() ?><input type="hidden" name="token" value="<?= e($round['token']) ?>">
            <?php foreach (Round::cards([$words[$round['gap']]], $words, 3) as $card): ?>
                <button class="word-card" name="words[0]" value="<?= e($card) ?>"><?= $highlight($card) ?></button>
            <?php endforeach ?>
        </form>
    <?php elseif ($phase === 'ready'): ?>
        <p>Všechny bloky máš zvládnuté. Napiš všech <?= count($words) ?> slov <?= $wordsMode ? 'v libovolném pořadí' : 'od začátku v přesném pořadí' ?>. Bezchybný výsledek má hodnotu <?= Round::REWARD ?> XP.</p>
    <?php elseif ($phase === 'retry'): ?>
        <?php if ($wordsMode): ?>
            <p>Chyběla ti tato slova. Zkus si je zapamatovat a pak zopakuj celou sadu.</p>
            <ul class="word-list">
                <?php foreach ($result as $mistake): ?><li><?= $highlight($mistake['expected']) ?></li><?php endforeach ?>
            </ul>
        <?php else: ?>
            <p>Na těchto místech se tvoje řada lišila. Projdi si opravy a pak zkus celou řadu znovu.</p>
            <ol class="word-list corrections">
                <?php foreach ($result as $position => $mistake): ?>
                    <li value="<?= $position + 1 ?>"><del><?= e($mistake['entered']) ?></del> <strong><?= e($mistake['expected']) ?></strong></li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    <?php elseif ($phase === 'won'): ?>
        <p>Napsal jsi všech <?= count($words) ?> správných slov<?= $wordsMode ? '' : ' ve správném pořadí' ?>. Za tuhle misi máš <strong><?= Round::REWARD ?> XP</strong>.</p>
        <?php if (isset($packs[$level + 1])): ?>
            <a class="btn primary" href="<?= e($url('/mise/' . ($level + 1))) ?>">Další mise</a>
        <?php else: ?>
            <a class="btn primary" href="<?= e($url('/')) ?>">Zpátky na mapu</a>
        <?php endif ?>
    <?php endif ?>
    <?php if ($final || $fragments): ?>
        <?php if ($fragments): ?>
            <p>Dopiš konec každého slova. Začátek už máš.</p>
            <?php if ($result): ?><p class="notice" role="status">Zkus ještě označené konce slov.</p><?php endif ?>
        <?php else: ?>
            <p>Do každého řádku napiš jedno slovo nebo ustálené spojení. <?= $wordsMode ? 'Na pořadí nezáleží. Každé slovo použij jednou.' : 'Slova zkontrolujeme najednou.' ?></p>
        <?php endif ?>
        <form class="stack" action="<?= e($url('/hra/odpoved')) ?>" method="post">
            <?php csrf() ?><input type="hidden" name="token" value="<?= e($round['token']) ?>">
            <?php foreach (($fragments ? $block : $words) as $position => $word): ?>
                <?php $incorrect = $fragments && isset($result[$position]); ?>
                <div class="word-field <?= $fragments ? 'fragment-field' : '' ?>">
                    <label for="word-<?= $position ?>"><?= $fragments ? $highlight(Round::prefix($word)) : '<span class="sr-only">Slovo číslo </span>' . ($position + 1) . '.' ?></label>
                    <input id="word-<?= $position ?>" name="words[<?= $position ?>]" required maxlength="45" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" <?= $incorrect ? 'aria-invalid="true"' : '' ?>>
                </div>
            <?php endforeach ?>
            <button class="btn primary"><?= $wordsMode ? 'Zkontrolovat slova' : 'Zkontrolovat řadu' ?></button>
        </form>
        <?php if ($fragments): ?>
            <form class="separate" action="<?= e($url('/hra/dal')) ?>" method="post">
                <?php csrf() ?><input type="hidden" name="token" value="<?= e($round['token']) ?>">
                <button class="btn secondary">Znovu prohlédnout kartičky</button>
            </form>
        <?php endif ?>
    <?php elseif (in_array($phase, ['study', 'ready', 'retry'], true)): ?>
        <form action="<?= e($url('/hra/dal')) ?>" method="post">
            <?php csrf() ?><input type="hidden" name="token" value="<?= e($round['token']) ?>">
            <button class="btn primary"><?= $phase === 'study' ? 'Zamíchat kartičky' : ($wordsMode ? 'Napsat všechna slova' : 'Napsat celou řadu') ?></button>
        </form>
    <?php endif ?>
</section>
<?php if ($phase === 'practice'): ?><script src="/chain.js" defer></script><?php endif ?>
