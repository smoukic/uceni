<?php
$next = 0;
while (isset($progress[$next])) {
    $next++;
}
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
$titles += [
    'subjects' => 'Předměty', 'course' => 'Mapa ročníku', 'course-mission' => 'Mise', 'lesson' => 'Lekce',
    'assessment' => 'Opakování a testy', 'exercise' => 'Cvičení', 'rewards' => 'Odměny',
    'guardian-auth' => 'Pro rodiče', 'guardian' => 'Rodičovská sekce', 'history' => 'Historie učení', 'admin' => 'Správa aplikace', 'forbidden' => 'Přístup odepřen',
];
$page ??= [];
$style = $profile['style'] ?? [];
$coursePage = isset($page['course']);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titles[$view]) ?> · Učení</title>
    <link rel="stylesheet" href="/style.css?v=<?= e((int) filemtime(__DIR__ . '/../../public/style.css')) ?>">
</head>
<body class="<?= isset($style['theme']) ? e('theme-' . $style['theme']) : '' ?>">
<header class="top">
    <div class="top-inner">
        <a class="brand" href="/predmety"><span class="logo" aria-hidden="true">U</span><span>učení<span class="brand-dot">.</span></span></a>
        <nav aria-label="Hlavní navigace">
            <a href="/predmety" <?= $view === 'subjects' ? 'aria-current="page"' : '' ?>>Předměty</a>
            <a href="<?= e($coursePage ? $page['course']['url'] : $url('/')) ?>" <?= in_array($view, ['map', 'course'], true) ? 'aria-current="page"' : '' ?>>Mapa</a>
            <a href="<?= e($url('/parta')) ?>" <?= $view === 'team' ? 'aria-current="page"' : '' ?>>Parta</a>
            <?php if ($player): ?><a href="/odmeny" <?= $view === 'rewards' ? 'aria-current="page"' : '' ?>>Odměny</a><?php endif ?>
            <?php if (!empty($_SESSION['guardian'])): ?><a href="/rodic">Rodič</a><?php endif ?>
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
    <?php
    if ($page) {
        (static function (string $view, array $page): void {
            extract($page, EXTR_SKIP);
            require __DIR__ . '/' . $view . '.php';
        })($view, $page);
    } else {
        require __DIR__ . '/' . $view . '.php';
    }
    ?>
</main>
<footer class="shell footer"><span>UČENÍ<span class="brand-dot">.</span></span> <a href="/rodic">Pro rodiče</a></footer>
</body>
</html>
