<section class="panel narrow">
    <?php if ($path === '/obnova-hesla'): ?>
        <h1>Nejde se mi přihlásit</h1>
        <p>Zadej svoji přezdívku a e-mail, který máš ověřený v profilu.</p>
        <form class="stack" method="post">
            <?php csrf() ?>
            <label>Přezdívka <input required name="handle" maxlength="20" autocomplete="username"></label>
            <label>E-mail <input required type="email" name="email" maxlength="254" autocomplete="email"></label>
            <button class="btn primary">Poslat odkaz na obnovu</button>
        </form>
    <?php else: ?>
        <?php $verify = $path === '/overit-email'; ?>
        <h1><?= $verify ? 'Ověř e-mail' : 'Nastav nové heslo' ?></h1>
        <form class="stack" method="post">
            <?php csrf() ?>
            <input type="hidden" name="token" id="recovery-token" value="<?= e(preg_match('/^[a-f0-9]{64}$/D', $this->field('token')) ? $this->field('token') : '') ?>">
            <?php if (!$verify): ?>
                <label>Nové heslo <input required type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password"></label>
                <label>Zopakuj heslo <input required type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password"></label>
            <?php endif ?>
            <button class="btn primary"><?= $verify ? 'Potvrdit e-mail' : 'Uložit heslo' ?></button>
        </form>
        <script src="/recovery.js" defer></script>
    <?php endif ?>
    <p><a href="/prihlaseni">Zpět k přihlášení</a></p>
</section>
