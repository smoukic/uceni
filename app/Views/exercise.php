<?php
$types = [
    'choice' => 'Výběr odpovědi', 'fill' => 'Doplň větu', 'order' => 'Skládání věty', 'translate' => 'Překlad',
    'match' => 'Spojování', 'correct' => 'Oprava věty', 'tense' => 'Gramatický čas', 'read' => 'Čtení', 'listen' => 'Poslech', 'dictation' => 'Diktát',
    'math' => 'Počítání', 'spelling' => 'Pravopis',
];
$practice = str_starts_with($run['task'], 'practice') || $run['task'] === 'review';
?>
<section class="panel learning-panel narrow exercise">
    <a class="back" href="<?= e($run['back']) ?>">Zpět na lekci, misi nebo test</a>
    <h1><?= e($run['heading']) ?></h1>
    <?php if ($run['finished']): ?>
        <h2><?= $practice ? 'Procvičování dokončeno' : ($passed ? 'Zvládnuto!' : 'Ještě jeden pokus?') ?></h2>
        <p>Na první pokus: <strong><?= $run['correct'] ?> z <?= $run['total'] ?> · <?= e($run['score']) ?> %</strong></p>
        <?php if (!empty($run['xp'])): ?><p class="game-success">Získáváš <strong>+<?= e($run['xp']) ?> XP</strong>.</p><?php endif ?>
        <?php if (!$practice && !$passed): ?>
            <p>K odemčení postupu potřebuješ alespoň 80 %. Zkus si nejdřív projít otázky, ve kterých jsi chyboval/a.</p>
        <?php endif ?>
        <div class="learning-actions">
            <a class="btn primary" href="<?= e($run['back']) ?>">Pokračovat</a>
            <a class="btn secondary" href="<?= e($course['url'] . '/opakovani') ?>">Opakování chyb</a>
        </div>
    <?php else: ?>
        <p class="tag"><?= e($types[$item['type']]) ?> · <?= $run['position'] + 1 ?> z <?= count($run['items']) ?></p>
        <progress value="<?= $run['position'] ?>" max="<?= count($run['items']) ?>" aria-label="Průběh cvičení"></progress>
        <?php if ($item['type'] === 'read'): ?><blockquote lang="<?= e($course['language']) ?>"><?= e($item['context']) ?></blockquote><?php endif ?>
        <?php if (in_array($item['type'], ['listen', 'dictation'], true)): ?>
            <audio controls preload="metadata">
                <source type="audio/mp4" src="<?= e('/audio/' . $course['directory'] . '/' . $itemId . '.m4a') ?>">
                Poslech vyžaduje prohlížeč s přehráváním zvuku.
            </audio>
            <?php if ($run['feedback'] !== null): ?><details><summary>Přepis poslechu</summary><p lang="<?= e($course['language']) ?>"><?= e($item['context']) ?></p></details><?php endif ?>
        <?php endif ?>
        <h2><?= e($item['prompt']) ?></h2>
        <?php if ($run['feedback'] !== null): ?>
            <div class="<?= $run['feedback'] ? 'game-success' : 'notice' ?> exercise-feedback" role="status">
                <strong><?= $run['feedback'] ? 'Správně!' : 'Tady pomůže tohle pravidlo' ?></strong>
                <?php if (!empty($run['hint'])): ?><p><?= e($run['hint']) ?></p><?php endif ?>
                <p><?= e($item['why']) ?></p>
                <?php if (!$run['feedback']): ?>
                    <p><strong>Správně:</strong>
                        <?php if ($item['type'] === 'match'): ?>
                            <?= e(implode(' · ', array_map(static fn($left, $right) => "$left → $right", array_keys($item['answer']), $item['answer']))) ?>
                        <?php else: ?>
                            <?= e(((array) $item['answer'])[0]) ?>
                        <?php endif ?>
                    </p>
                    <p>Tahle otázka se objeví v opakování.</p>
                <?php endif ?>
            </div>
            <form method="post" action="<?= e($path) ?>">
                <?php csrf() ?><input type="hidden" name="token" value="<?= e($run['token']) ?>">
                <button class="btn primary" name="action" value="next">Pokračovat</button>
            </form>
        <?php else: ?>
            <form class="stack" method="post" action="<?= e($path) ?>" <?= $item['type'] === 'order' ? 'data-sentence' : '' ?>>
                <?php csrf() ?><input type="hidden" name="token" value="<?= e($run['token']) ?>">
                <?php if ($item['type'] === 'order'): ?>
                    <div data-sentence-selects>
                        <?php foreach ($item['options'] as $position => $_): ?>
                            <label>Slovo <?= $position + 1 ?>
                                <select name="answer[<?= $position ?>]" required lang="<?= e($course['language']) ?>">
                                    <option value="">Vyber</option>
                                    <?php foreach ($item['options'] as $index => $word): ?><option value="<?= $index ?>"><?= e($word) ?></option><?php endforeach ?>
                                </select>
                            </label>
                        <?php endforeach ?>
                    </div>
                    <div data-sentence-cards hidden>
                        <p class="sentence-line" data-sentence-line aria-live="polite" lang="<?= e($course['language']) ?>">Vyber první kartičku.</p>
                        <div class="card-bank">
                            <?php foreach ($item['options'] as $index => $word): ?><button class="word-card" type="button" value="<?= $index ?>" lang="<?= e($course['language']) ?>"><?= e($word) ?></button><?php endforeach ?>
                        </div>
                        <button type="button" class="btn secondary" data-sentence-undo disabled>Vrátit poslední</button>
                    </div>
                <?php elseif ($item['type'] === 'match'): ?>
                    <?php $options = array_values($item['answer']); shuffle($options); ?>
                    <?php foreach ($item['options'] as $index => $label): ?>
                        <label class="match-row"><span><?= e($label) ?></span>
                            <select name="answer[<?= $index ?>]" required>
                                <option value="">Vyber dvojici</option>
                                <?php foreach ($options as $option): ?><option value="<?= e($option) ?>"><?= e($option) ?></option><?php endforeach ?>
                            </select>
                        </label>
                    <?php endforeach ?>
                <?php elseif ($item['options']): ?>
                    <?php $options = $item['options']; shuffle($options); ?>
                    <fieldset class="choice-stack">
                        <legend class="sr-only">Možnosti</legend>
                        <?php foreach ($options as $option): ?>
                            <label><input type="radio" name="answer" required value="<?= e($option) ?>"><span><?= e($option) ?></span></label>
                        <?php endforeach ?>
                    </fieldset>
                <?php else: ?>
                    <label>Tvoje odpověď <input name="answer" required maxlength="380" spellcheck="false" autocomplete="off" autocapitalize="off"></label>
                <?php endif ?>
                <button class="btn primary" name="action" value="answer" data-check-answer>Zkontrolovat</button>
            </form>
            <?php if ($item['type'] === 'order'): ?><script src="/learning.js" defer></script><?php endif ?>
        <?php endif ?>
        <form class="separate" method="post" action="<?= e($path) ?>">
            <?php csrf() ?><input type="hidden" name="token" value="<?= e($run['token']) ?>">
            <button class="btn secondary" name="action" value="exit">Ukončit cvičení</button>
        </form>
    <?php endif ?>
</section>
