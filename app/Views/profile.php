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
