<section class="panel narrow">
    <p class="tag">HRÁČSKÝ PROFIL</p>
    <h1><?= e($player['handle']) ?></h1>
    <p><?= e($challengeName) ?> · <?= e(array_sum($progress)) ?> XP · <?= count($progress) ?> / <?= count($packs) ?> misí</p>
    <form class="stack" action="<?= e($url('/profil')) ?>" method="post">
        <?php csrf() ?>
        <label for="school">Škola (nepovinné)</label>
        <input id="school" name="school" maxlength="100" value="<?= e($player['school']) ?>" aria-describedby="school-note">
        <small id="school-note">Název školy vidíš ve hře jen ty.</small>
        <button class="btn primary">Uložit profil</button>
    </form>
    <form class="separate" action="<?= e($url('/odhlaseni')) ?>" method="post"><?php csrf() ?><button class="btn secondary">Odhlásit se</button></form>
</section>

<?php if ($profile): ?>
<section class="panel learning-panel">
    <h2>Můj postup napříč předměty</h2>
    <?php $summary = $profile['summary']; ?>
    <div class="profile-identity">
        <span class="profile-avatar <?= isset($style['frame']) ? e('frame-' . $style['frame']) : '' ?>" aria-hidden="true"><?= e($style['avatar'] ?? '🧭') ?></span>
        <div><strong><?= e($player['handle']) ?></strong><?php if (isset($style['pin'])): ?> <span aria-label="Ozdoba profilu"><?= e($style['pin']) ?></span><?php endif ?><p><?= e($style['title'] ?? $summary['title']) ?></p></div>
    </div>
    <?php require __DIR__ . '/learning-stats.php'; ?>
    <a class="btn secondary" href="/odmeny">Otevřít odměny</a>
</section>
<?php endif ?>

<?php if ($profile): ?>
<section class="panel learning-panel narrow">
    <h2>Ročník a přihlášení</h2>
    <?php if ($profile['grade']['grade'] === null): ?>
        <form class="stack" method="post" action="/profil">
            <?php csrf() ?>
            <label>Vyber svůj ročník jednou pro tento účet
                <select name="grade" required><option value="">Vyber ročník</option><?php $selectedGrade = null; require __DIR__ . '/grade-options.php'; ?></select>
            </label>
            <button class="btn primary" name="action" value="grade">Potvrdit ročník</button>
        </form>
    <?php else: ?>
        <p>Chodíš do <?= e($profile['grade']['grade']) ?>. ročníku. Učivo máš otevřené do <?= e($profile['grade']['ceiling']) ?>. ročníku.</p>
    <?php endif ?>
    <details class="separate">
        <summary>Změnit heslo</summary>
        <form class="stack" method="post" action="/profil">
            <?php csrf() ?>
            <label>Současné heslo <input required name="current_password" type="password" maxlength="72" autocomplete="current-password"></label>
            <label>Nové heslo <input required name="next_password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></label>
            <label>Zopakuj nové heslo <input required name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></label>
            <button class="btn primary" name="action" value="password">Uložit nové heslo</button>
        </form>
    </details>
    <p><a href="/historie">Historie učení a opakování</a></p>
</section>
<?php endif ?>

<section class="panel learning-panel narrow">
    <h2>E-mail pro obnovu hesla</h2>
    <p><?= $verifiedEmail ? 'Ověřený e-mail: ' . e($verifiedEmail) : 'Přidej a ověř e-mail, ať si můžeš obnovit zapomenuté heslo.' ?></p>
    <form class="stack" method="post" action="/profil">
        <?php csrf() ?>
        <label>E-mail <input name="email" type="email" required maxlength="254" autocomplete="email"></label>
        <label>Současné heslo <input required name="current_password" type="password" maxlength="72" autocomplete="current-password"></label>
        <button class="btn primary" name="action" value="email">Poslat ověřovací odkaz</button>
    </form>
</section>
