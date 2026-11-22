<?php
$next = count($progress);
$current = $packs[$next] ?? null;
$inProgress = $round && $round['phase'] !== 'won';
$challengeName = $wordsMode ? 'Slova v řadě' : 'Správné pořadí';
$challengePage = match ($view) {
    'profile' => '/profil',
    'team' => '/parta',
    default => '/',
};
$icons = ['🦫', '🌲', '🐭', '🦇', '🦉', '🌪️', '🏔️'];
$titles = ['map' => 'Mapa misí', 'auth' => 'Hráčský účet', 'profile' => 'Můj profil', 'team' => 'Moje parta', 'game' => 'Výprava', 'mission' => 'Příprava na misi', '404' => 'Ztracená stezka'];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titles[$view]) ?> · Učení</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<header class="top">
    <div class="top-inner">
        <a class="brand" href="<?= e($url('/')) ?>"><span class="logo" aria-hidden="true">U</span><span>učení<span class="brand-dot">.</span></span></a>
        <nav aria-label="Hlavní navigace">
            <a href="<?= e($url('/')) ?>" <?= $view === 'map' ? 'aria-current="page"' : '' ?>>Mapa</a>
            <a href="<?= e($url('/parta')) ?>" <?= $view === 'team' ? 'aria-current="page"' : '' ?>>Parta</a>
            <a class="nav-account" href="<?= e($url($player ? '/profil' : '/prihlaseni')) ?>"><?= $player ? 'Profil' : 'Přihlásit se' ?></a>
        </nav>
    </div>
</header>
<main class="shell">
    <?php if ($message): ?><p class="notice" role="status"><?= e($message) ?></p><?php endif ?>
    <?php if (in_array($view, ['map', 'team', 'profile'], true)): ?>
        <nav class="challenge-switch" aria-label="Výběr výzvy">
            <a href="<?= e($challengePage) ?>" <?= !$wordsMode ? 'aria-current="page"' : '' ?>><span aria-hidden="true">▦</span> Správné pořadí</a>
            <a href="<?= e($challengePage . '?vyzva=slova') ?>" <?= $wordsMode ? 'aria-current="page"' : '' ?>><span aria-hidden="true">✦</span> Slova v řadě</a>
        </nav>
    <?php endif ?>
    <?php require __DIR__ . '/' . $view . '.php'; ?>
</main>
<footer class="shell footer"><span>UČENÍ<span class="brand-dot">.</span></span> <span>Čeština · Vyjmenovaná slova</span></footer>
</body>
</html>
