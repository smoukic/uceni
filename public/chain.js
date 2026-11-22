const chain = document.querySelector('[data-chain]');
const cards = [...chain.querySelectorAll('[data-card]')];
const slots = [...chain.querySelectorAll('[data-slot]')];
const answers = [...chain.querySelectorAll('[data-answer]')];
const check = chain.querySelector('[data-check]');
const undo = chain.querySelector('[data-undo]');
const status = chain.querySelector('[data-status]');
const picked = [];

function render() {
    slots.forEach((slot, position) => {
        const card = picked[position];
        slot.replaceChildren(...(card
            ? [...card.childNodes].map(node => node.cloneNode(true))
            : ['Volné místo']));
        slot.parentElement.classList.toggle('filled', Boolean(card));
        answers[position].value = card?.value ?? '';
    });
    const full = picked.length === slots.length;
    cards.forEach(card => {
        const used = picked.includes(card);
        card.disabled = used || full;
        card.classList.toggle('used', used);
    });
    check.disabled = !full;
    undo.disabled = picked.length === 0;
    status.textContent = `Vybráno ${picked.length} z ${slots.length}`;
}

for (const card of cards) {
    card.addEventListener('click', event => {
        if (card.disabled) return;
        picked.push(card);
        render();
        if (event.detail === 0) {
            (picked.length === slots.length ? check : cards.find(option => !option.disabled)).focus();
        }
    });
}

undo.addEventListener('click', () => {
    const card = picked.pop();
    render();
    if (undo.disabled) card?.focus();
});
