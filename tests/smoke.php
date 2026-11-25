<?php
declare(strict_types=1);

require __DIR__ . '/../app/Learning/Accounts.php';
require __DIR__ . '/../app/Learning/Curriculum.php';
require __DIR__ . '/../app/Learning/Recovery.php';
require __DIR__ . '/../app/Learning/LearningStore.php';
require __DIR__ . '/../app/Models/Store.php';

function check(bool $valid, string $test): void
{
    if (!$valid) {
        throw new RuntimeException($test);
    }
}

$database = tempnam(sys_get_temp_dir(), 'uceni-');
try {
    $db = new PDO('sqlite:' . $database);
    // Exercise an upgrade with legacy tables before creating the new schema.
    $db->exec("CREATE TABLE players(id INTEGER PRIMARY KEY, handle TEXT, password TEXT, school TEXT, team TEXT);
        CREATE TABLE medals(player INTEGER, level INTEGER, xp INTEGER, PRIMARY KEY(player, level));
        CREATE TABLE word_medals(player INTEGER, level INTEGER, xp INTEGER, PRIMARY KEY(player, level));
        INSERT INTO players VALUES (10, 'legacy', 'hash', 'school', NULL);
        INSERT INTO medals VALUES (10, 0, 100);
        INSERT INTO word_medals VALUES (10, 0, 100);");
    $store = new Store($database);
    $learn = $store->learning;
    check($learn->summary(10)['xp'] === 200 && $learn->summary(10)['balance'] === 20, 'Legacy XP and coins imported once');
    check($store->accounts->grade(10)['ceiling'] === 6, 'Legacy access preserved');
    check($store->accounts->initializeGrade(10, 3) && !$store->accounts->initializeGrade(10, 9), 'One-time learner grade selection');
    check(!$store->accounts->setGrade(10, 4, 3) && $store->accounts->setGrade(10, 3, 5), 'Grade scope and override');
    $learn->visit(10);
    $learn->visit(10);
    check($learn->summary(10)['xp'] === 205, 'Daily visit grants XP once');

    $curriculum = new Curriculum(__DIR__ . '/../app/Content');
    $courses = $items = $recordings = 0;
    foreach ($curriculum->catalog as $subject => $entry) {
        check(count($entry['grades']) === 9, "$subject has all grades");
        foreach ($entry['grades'] as $grade => $_) {
            $course = $curriculum->course($subject, (string) $grade);
            $courses++;
            check(count($course['missions']) >= 4, 'Grade course has missions');
            foreach ($course['items'] as $id => $item) {
                $items++;
                $answer = $item['answer'];
                if ($item['type'] === 'match') {
                    $response = array_values($answer);
                } elseif ($item['type'] === 'order') {
                    $tiles = array_map('strtolower', $item['options']);
                    $response = [];
                    foreach (explode(' ', strtolower(rtrim($answer, '.!?'))) as $word) {
                        $index = array_search($word, $tiles, true);
                        check($index !== false, "$id includes all required tiles");
                        $response[] = (string) $index;
                        unset($tiles[$index]);
                    }
                } else {
                    $response = ((array) $answer)[0];
                }
                check(Curriculum::check($item, $response), "Accepted answer for $subject/$grade/$id");
                check(!Curriculum::check($item, 'not-a-valid-answer'), "Invalid answer for $subject/$grade/$id");
                if (in_array($item['type'], ['listen', 'dictation'], true)) {
                    $recordings++;
                    $file = __DIR__ . '/../public/audio/' . $course['directory'] . "/$id.m4a";
                    check(is_file($file) && filesize($file) > 1024, "Audio exists for $id");
                }
            }
        }
    }
    check(!Curriculum::check(['type' => 'dictation', 'answer' => 'Ema má míč.'], 'ema ma mic'), 'Dictation checks case, marks and punctuation');
    check(Curriculum::check(['type' => 'math', 'answer' => '1/2'], '2/4'), 'Equivalent fractions');
    check(Curriculum::check(['type' => 'math', 'answer' => '0,7'], '0.70'), 'Decimal separator and scale');

    $course = $curriculum->course('anglictina', '6');
    $learn->recordResult(10, $course, 'lesson:be', 100, 'a');
    $learn->recordResult(10, $course, 'lesson:be', 100, 'a');
    $learn->recordResult(10, $course, 'mission:start', 100, 'b');
    $before = $learn->summary(10);
    $reset = $learn->missionResetPreview(10, $course, 'start');
    check($reset['xp'] === 160, 'Exact chapter reset XP quote');
    check(!$learn->resetMission(10, $course, 'start', 'wrong'), 'Invalid quote rejected');
    check($learn->resetMission(10, $course, 'start', $reset['key']), 'Chapter reset');
    check(!$learn->resetMission(10, $course, 'start', $reset['key']), 'Duplicate reset rejected');
    check($learn->summary(10)['xp'] === $before['xp'] - 160, 'XP loss applied');
    $learn->recordResult(10, $course, 'lesson:be', 100, 'c');
    $learn->recordResult(10, $course, 'mission:start', 100, 'd');
    check($learn->summary(10)['xp'] === $before['xp'] && $learn->summary(10)['balance'] === $before['balance'], 'Remastery restores XP without reissuing coins');
    check(!$learn->resetMission(10, $course, 'start', $reset['key']), 'Old quote cannot restart a remastered chapter');
    check(count($learn->recentAttempts(10)) === 4, 'Attempt history survives reset');

    $activation = $learn->guardianActivation();
    $guardian = $learn->registerGuardian('test-parent', 'test-guardian-password', $activation);
    check($guardian !== null && $learn->registerGuardian('second', 'test-guardian-password', $activation) === null, 'Single-use parent activation');
    check($learn->linkChild(10, $learn->guardianInvite($guardian)), 'Guardian consent links learner');
    check($learn->grantOperator('test-parent') && $learn->operator($guardian), 'Explicit operator promotion');
    check($learn->saveReward($guardian, 10, 0, 'Výlet', 12, true), 'Reward saved');
    $wallet = $learn->summary(10)['balance'];
    check($learn->claim(10, 1, str_repeat('a', 32)), 'Coins reserved');
    check(!$learn->claim(10, 1, str_repeat('b', 32)), 'Pending purchase is deduplicated');
    check(!$learn->changeClaim($guardian + 1, 1, 'rejected'), 'Unauthorized guardian cannot refund');
    check($learn->changeClaim($guardian, 1, 'rejected'), 'Rejection refunds coins');
    check(!$learn->changeClaim($guardian, 1, 'rejected') && !$learn->claim(10, 1, str_repeat('a', 32)), 'Replays cannot duplicate transaction');
    check($learn->summary(10)['balance'] === $wallet, 'Balance reconciles');
    check($learn->saveCosmetic('smoke-tile', ['pin', 'Ozdoba', '★', 1, 0]) && !$learn->buy(10, 'smoke-tile'), 'Inactive product unavailable');
    $db->prepare('UPDATE players SET password = ? WHERE id = 10')->execute([password_hash('test-old-password', PASSWORD_DEFAULT)]);
    $outbox = [];
    $recovery = new Recovery($db, new LearningStore($db), static function (string $email, string $purpose, string $token) use (&$outbox): bool {
        $outbox[] = compact('email', 'purpose', 'token');
        return true;
    });
    check($recovery->requestEmail(10, 'learner@example.test', 'test-old-password'), 'Verification issued');
    $recovery->requestReset('legacy', 'learner@example.test', '127.0.0.1');
    check(count($outbox) === 1, 'Unverified address cannot reset password');
    check($recovery->complete('verify', $outbox[0]['token']), 'Verification token consumed');
    $recovery->requestReset('legacy', 'wrong@example.test', '127.0.0.1');
    check(count($outbox) === 1, 'Wrong email cannot reset password');
    $recovery->requestReset('legacy', 'learner@example.test', '127.0.0.1');
    check(count($outbox) === 2, 'Reset message queued for verified address');
    $token = $outbox[1]['token'];
    check($recovery->complete('reset', $token, 'test-new-password'), 'Password changed');
    check(!$recovery->complete('reset', $token, 'test-other-password'), 'Reset token is single use');
    check((int) $db->query('SELECT epoch FROM password_epochs WHERE player = 10')->fetchColumn() === 1, 'Sessions revoked');
    check(password_verify('test-new-password', $db->query('SELECT password FROM players WHERE id = 10')->fetchColumn()), 'New password persisted');
    check($learn->summary(10)['xp'] === $before['xp'], 'Password reset preserves XP');

    check($db->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'SQLite integrity');
    check($db->query('PRAGMA foreign_key_check')->fetch() === false, 'Foreign keys');
    echo "$courses courses, $items questions, $recordings recordings. Account, migration, XP and wallet checks OK.\n";
} finally {
    unlink($database);
}
