const form = document.querySelector('[data-sentence]');
if (form) {
    const selects = [...form.querySelectorAll('select')];
    const cards = [...form.querySelectorAll('.word-card')];
    const fallback = form.querySelector('[data-sentence-selects]');
    const board = form.querySelector('[data-sentence-cards]');
    const line = form.querySelector('[data-sentence-line]');
    const undo = form.querySelector('[data-sentence-undo]');
    const check = form.querySelector('[data-check-answer]');
    const picked = [];

    function render() {
        selects.forEach((select, index) => {
            select.value = picked[index]?.value ?? '';
        });
        line.textContent = picked.map(card => card.textContent).join(' ') || 'Vyber první kartičku.';
        cards.forEach(card => { card.disabled = picked.includes(card); });
        undo.disabled = picked.length === 0;
        check.disabled = picked.length !== selects.length;
    }

    fallback.hidden = true;
    board.hidden = false;
    selects.forEach(select => { select.required = false; });
    cards.forEach(card => card.addEventListener('click', event => {
        picked.push(card);
        render();
        if (event.detail === 0) {
            (check.disabled ? cards.find(option => !option.disabled) : check).focus();
        }
    }));
    undo.addEventListener('click', () => {
        const card = picked.pop();
        render();
        card?.focus();
    });
    render();
}
