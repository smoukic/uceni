<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Použití: php bin/guardian-invite.php cesta/k/uceni.sqlite\n");
    exit(1);
}
require __DIR__ . '/../app/Learning/LearningStore.php';
require __DIR__ . '/../app/Learning/Accounts.php';
require __DIR__ . '/../app/Models/Store.php';
$store = new Store($argv[1]);
echo $store->learning->guardianActivation(), PHP_EOL;
