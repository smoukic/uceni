<section class="hero">
    <div class="hero-copy">
        <p class="tag">ČEŠTINA <span>✦</span> <?= e(mb_strtoupper($challengeName)) ?></p>
        <h1>Vyjmenovaná<br><em>slova</em></h1>
        <p><?= $wordsMode ? 'Vyber správné kartičky, dokonči rozepsaná slova a zkus si vybavit celou sadu.' : 'Seřaď slovní kartičky, najdi chybějící článek a odemkni další kus mapy.' ?></p>
        <a class="btn primary" href="<?= e($url($inProgress ? '/hra' : '/mise/' . ($current ? $next : 0))) ?>"><?= $inProgress ? 'Pokračovat v rozehrané misi' : ($current ? ($next ? 'Další mise' : 'Začít výpravu') : 'Zopakovat výpravu') ?></a>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <span class="tile tile-y">Y</span><span class="tile tile-i">Í</span><span class="spark spark-one">✦</span><span class="spark spark-two">✧</span><span class="spark spark-three">✦</span>
    </div>
</section>
<section class="stats" aria-label="Tvůj postup">
    <div><span class="stat-icon gold" aria-hidden="true">ϟ</span><div><strong><?= e(array_sum($progress)) ?> XP</strong><small>Celkové skóre</small></div></div>
    <div><span class="stat-icon green" aria-hidden="true">✓</span><div><strong><?= count($progress) ?> / <?= count($packs) ?></strong><small>Splněné mise</small></div></div>
    <div><span class="stat-icon blue" aria-hidden="true">✦</span><div><strong><?= $current ? 'Po ' . e($current['letter']) : 'Hotovo!' ?></strong><small><?= $current ? 'Další výzva' : 'Mapa prozkoumána' ?></small></div></div>
</section>
<section class="map" aria-labelledby="map-title">
    <div class="section-title"><h2 id="map-title">Mapa misí</h2><span><?= count($progress) ?> ze <?= count($packs) ?> hotovo</span></div>
    <progress value="<?= count($progress) ?>" max="<?= count($packs) ?>" aria-label="Dokončené mise"></progress>
    <ol class="missions">
        <?php foreach ($packs as $i => $pack): ?>
            <?php $done = isset($progress[$i]); $locked = $i > $next; ?>
            <li class="mission-row <?= $locked ? 'locked' : ($done ? 'done' : 'active') ?>">
                <span class="mission-icon" aria-hidden="true"><?= $icons[$i] ?></span>
                <div class="mission-name"><small>PO <?= e($pack['letter']) ?></small><h3><?= e($pack['title']) ?></h3></div>
                <?php if ($locked): ?>
                    <span class="lock">Zamčeno</span>
                <?php else: ?>
                    <a class="btn <?= $done ? 'secondary' : 'primary' ?>" href="<?= e($url('/mise/' . $i)) ?>" aria-label="<?= e(($done ? 'Zopakovat ' : 'Otevřít misi ') . $pack['title']) ?>"><?= $done ? 'Zopakovat' : 'Hrát' ?></a>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
</section>
