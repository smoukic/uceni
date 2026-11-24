<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3 || !is_file($argv[1])) {
    fwrite(STDERR, "Použití: php bin/operator-grant.php cesta/k/uceni.sqlite rodicovsky-ucet\n");
    exit(1);
}
require __DIR__ . '/../app/Learning/LearningStore.php';
require __DIR__ . '/../app/Learning/Accounts.php';
require __DIR__ . '/../app/Models/Store.php';
$store = new Store($argv[1]);
if (!$store->learning->grantOperator($argv[2])) {
    fwrite(STDERR, "Účet neexistuje nebo už má roli správce.\n");
    exit(1);
}
echo "Rodičovskému účtu přidělena role správce.\n";
