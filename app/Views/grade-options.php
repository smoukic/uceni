<?php for ($optionGrade = 1; $optionGrade <= 9; $optionGrade++): ?>
    <option value="<?= $optionGrade ?>" <?= $selectedGrade === $optionGrade ? 'selected' : '' ?>><?= $optionGrade ?>. ročník</option>
<?php endfor ?>
