<?php $register = $path === '/registrace'; ?>
<section class="panel narrow">
    <h1><?= $register ? 'Vytvoř si hráče' : 'Vítej zpátky' ?></h1>
    <p><?= $register ? 'Vyber si přezdívku do hry. Pod ní tě uvidí tvoje parta.' : 'Přihlas se a pokračuj ve své výpravě.' ?></p>
    <form class="stack" action="<?= e($url($path)) ?>" method="post">
        <?php csrf() ?>
        <label for="handle">Přezdívka</label>
        <input id="handle" name="handle" required pattern="[a-zA-Z0-9_\-]{3,20}" maxlength="20" autocomplete="username" autocapitalize="none" spellcheck="false">
        <?php if ($register): ?><small>3–20 znaků. Písmena bez háčků a čárek, číslice, podtržítko nebo pomlčka.</small><?php endif ?>
        <label for="password">Heslo</label>
        <input id="password" name="password" type="password" required <?= $register ? 'minlength="8"' : '' ?> maxlength="72" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>">
        <?php if ($register): ?><small>Alespoň 8 znaků.</small><?php endif ?>
        <?php if ($register): ?>
            <label for="grade">Jaký ročník chodíš?</label>
            <select id="grade" name="grade" required><option value="">Vyber ročník</option><?php $selectedGrade = null; require __DIR__ . '/grade-options.php'; ?></select>
        <?php endif ?>
        <label class="inline-check"><input type="checkbox" name="remember" value="1"> Zapamatovat přihlášení na tomto zařízení</label>
        <button class="btn primary"><?= $register ? 'Vytvořit účet' : 'Přihlásit se' ?></button>
    </form>
    <p><a href="<?= e($url($register ? '/prihlaseni' : '/registrace')) ?>"><?= $register ? 'Už mám účet' : 'Chci si vytvořit účet' ?></a></p>
</section>

<?php if (!$register): ?>
    <p class="narrow"><a href="/obnova-hesla">Nejde se mi přihlásit</a></p>
<?php endif ?>
