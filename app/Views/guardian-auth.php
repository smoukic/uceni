<section class="panel learning-panel narrow">
    <h1>Pro rodiče</h1>
    <p>Rodičovský účet má vlastní přihlašovací údaje. Na sdíleném zařízení se po práci odhlaste.</p>
    <h2>Přihlášení rodiče</h2>
    <form class="stack" method="post" action="/rodic">
        <?php csrf() ?>
        <label>Přihlašovací jméno <input name="handle" required maxlength="30" autocomplete="username"></label>
        <label>Heslo <input name="password" type="password" required maxlength="72" autocomplete="current-password"></label>
        <button class="btn primary" name="action" value="login">Přihlásit se jako rodič</button>
    </form>
    <details class="separate">
        <summary>Aktivovat nový rodičovský účet</summary>
        <p>Jednorázový aktivační kód poskytne správce Učení. Po aktivaci si v rodičovské sekci vytvoříte jiný kód pro propojení s dítětem.</p>
        <form class="stack" method="post" action="/rodic">
            <?php csrf() ?>
            <label>Aktivační kód <input name="code" required minlength="32" maxlength="32" autocomplete="off"></label>
            <label>Přihlašovací jméno <input name="handle" required minlength="3" maxlength="30" autocomplete="username"></label>
            <label>Heslo (alespoň 12 znaků) <input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
            <button class="btn primary" name="action" value="activate">Aktivovat účet</button>
        </form>
    </details>
</section>
