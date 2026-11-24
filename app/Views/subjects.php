<section class="hero school-hero">
    <div class="hero-copy"><h1>Škola<br><em>hrou</em></h1><p>Vyber ročník a předmět. Splň krátkou misi, získej zkušenosti a vrať se k úlohám, které ještě potřebuješ potrénovat.</p></div>
    <div class="hero-art" aria-hidden="true"><span class="orbit orbit-one"></span><span class="tile tile-y">A</span><span class="tile tile-i">∑</span></div>
</section>
<section class="map" aria-labelledby="school-grade">
    <h2 id="school-grade">Vyber ročník</h2>
    <nav class="grade-grid" aria-label="Ročníky">
        <?php for ($year = 1; $year <= 9; $year++): ?>
            <a href="/predmety?trida=<?= $year ?>" aria-label="<?= $year ?>. ročník<?= $year > $ceiling ? ', zamčeno' : '' ?>" <?= $year === $grade ? 'aria-current="page"' : '' ?>><?= $year ?>.<?= $year > $ceiling ? ' 🔒' : '' ?></a>
        <?php endfor ?>
    </nav>
    <?php if ($needsGrade): ?><p class="notice">Ročník svého stávajícího účtu si můžeš jednou vybrat <a href="/profil">v profilu</a>.</p><?php endif ?>
    <?php if ($grade > $ceiling): ?>
        <p class="notice">Tento ročník je zamčený. Rozsah učiva může upravit rodič nebo správce.</p>
    <?php else: ?>
        <div class="subject-tiles">
            <?php foreach ($subjects as $id => $subject): ?>
                <article class="panel subject-tile">
                    <span class="mission-icon" aria-hidden="true"><?= e($subject['icon']) ?></span>
                    <h3><?= e($subject['title']) ?></h3>
                    <p><?= $grade ?>. ročník</p>
                    <a class="btn primary" href="<?= e("/predmet/$id/$grade") ?>">Otevřít mapu</a>
                    <?php if ($id === 'cestina'): ?>
                        <a class="subject-extra" href="<?= e("/predmet/$id/$grade/diktat") ?>">Diktáty</a>
                        <?php if ($grade >= 3): ?><a class="subject-extra" href="/">Vyjmenovaná slova</a><?php endif ?>
                    <?php endif ?>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
