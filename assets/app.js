import './stimulus_bootstrap.js';

// BookForEveryone - Main JS

// Onboarding: toggle generi
document.addEventListener('click', (e) => {
    const chip = e.target.closest('.genre-chip');
    if (chip) {
        chip.classList.toggle('is-selected');
        const checkbox = chip.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = !checkbox.checked;

        const form = document.getElementById('onboarding-form');
        if (form) {
            const selected = form.querySelectorAll('.genre-chip.is-selected').length;
            const submit = document.getElementById('onboarding-submit');
            if (submit) submit.disabled = selected < 3;
        }
    }
});

// Feed: pannello descrizione
document.addEventListener('click', (e) => {
    const descBtn = e.target.closest('.js-action-description');
    if (descBtn) {
        const bookId = descBtn.dataset.book;
        const panel = document.getElementById('desc-' + bookId);
        if (panel) panel.classList.add('is-open');
    }

    const closeBtn = e.target.closest('[data-close-desc]');
    if (closeBtn) {
        const bookId = closeBtn.dataset.closeDesc;
        const panel = document.getElementById('desc-' + bookId);
        if (panel) panel.classList.remove('is-open');
    }
});

// Feed: azioni (like, letto, posseggo) via fetch
document.addEventListener('click', (e) => {
    const actionBtn = e.target.closest('.feed-action[data-action]');
    if (!actionBtn) return;

    const action = actionBtn.dataset.action;
    const bookId = actionBtn.dataset.book;

    if (['like', 'read', 'own'].includes(action)) {
        fetch(`/book/${bookId}/action/${action}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (action === 'like') actionBtn.classList.toggle('feed-action--liked', data.liked);
            if (action === 'read') actionBtn.classList.toggle('feed-action--read', data.hasRead);
        });
    }

    if (action === 'share' && navigator.share) {
        navigator.share({
            title: 'BookForEveryone',
            url: `/book/${bookId}`
        });
    }
});
