<form method="post" class="stack">
    <?php csrf() ?>
    <input type="hidden" name="player" value="<?= e($learnerId) ?>">
    <input type="hidden" name="child" value="<?= e($learnerId) ?>">
    <label>Školní ročník
        <select name="grade" required><?php $selectedGrade = $gradeSetting['grade'] ?? 6; require __DIR__ . '/grade-options.php'; ?></select>
    </label>
    <label>Učivo otevřené nejvýše do
        <select name="ceiling" required><?php $selectedGrade = $gradeSetting['ceiling']; require __DIR__ . '/grade-options.php'; ?></select>
    </label>
    <button class="btn primary" name="action" value="grade">Uložit přístup k učivu</button>
</form>
