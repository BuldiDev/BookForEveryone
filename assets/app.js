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

// Feed: pannello descrizione (toggle) + caricamento lazy recensioni
document.addEventListener('click', (e) => {
    const descBtn = e.target.closest('.js-action-description');
    if (descBtn) {
        const bookId = descBtn.dataset.book;
        const panel  = document.getElementById('desc-' + bookId);
        if (!panel) return;
        panel.classList.toggle('is-open');
        if (panel.classList.contains('is-open')) loadReviews(bookId);
    }

    const closeBtn = e.target.closest('[data-close-desc]');
    if (closeBtn) {
        const bookId = closeBtn.dataset.closeDesc;
        const panel = document.getElementById('desc-' + bookId);
        if (panel) panel.classList.remove('is-open');
    }
});

function loadReviews(bookId) {
    const container = document.getElementById('desc-reviews-' + bookId);
    if (!container || container.dataset.loaded === 'true') return;

    const list = container.querySelector('.desc-reviews__list');
    list.innerHTML = '<p class="desc-reviews__placeholder">Caricamento…</p>';

    fetch(`/book/${bookId}/reviews`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            container.dataset.loaded = 'true';

            // Aggiorna header con media e conteggio
            const header = container.querySelector('.desc-reviews__header');
            if (data.avgRating) {
                header.innerHTML = `<span class="desc-reviews__title">Recensioni</span><span class="desc-reviews__avg">★ ${data.avgRating} <small>(${data.reviewsCount})</small></span>`;
            } else {
                header.innerHTML = `<span class="desc-reviews__title">Recensioni</span>`;
            }

            if (!data.reviews.length) {
                list.innerHTML = '<p class="desc-reviews__placeholder">Nessuna recensione ancora.</p>';
                return;
            }

            list.innerHTML = data.reviews.map(r => `
                <div class="desc-review-item">
                    <div class="desc-review-item__header">
                        <span class="desc-review-item__user">@${r.displayName}</span>
                        <span class="desc-review-item__rating">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</span>
                    </div>
                    ${r.content ? `<p class="desc-review-item__text">${r.content}</p>` : ''}
                </div>
            `).join('');
        })
        .catch(() => {
            list.innerHTML = '<p class="desc-reviews__placeholder">Errore nel caricamento.</p>';
        });
}

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
            if (action === 'like') {
                actionBtn.classList.toggle('feed-action--liked', data.liked);
                const label = actionBtn.querySelector('.feed-action__label');
                if (label) label.textContent = data.likesCount;
            }
            if (action === 'read') actionBtn.classList.toggle('feed-action--read', data.hasRead);
            if (action === 'own') actionBtn.classList.toggle('feed-action--own', data.ownsPhysically);
        });
    }

    if (action === 'share') {
        const shareUrl = window.location.origin + `/book/${bookId}`;
        
        if (navigator.share) {
            navigator.share({
                title: 'BookForEveryone',
                url: shareUrl
            }).catch(() => {
                // Se l'utente annulla la condivisione, non fare nulla
            });
        } else {
            // Fallback: copia il link negli appunti
            navigator.clipboard.writeText(shareUrl).then(() => {
                const originalLabel = actionBtn.querySelector('.feed-action__label').textContent;
                actionBtn.querySelector('.feed-action__label').textContent = 'Copiato!';
                setTimeout(() => {
                    actionBtn.querySelector('.feed-action__label').textContent = originalLabel;
                }, 2000);
            }).catch(() => {
                alert('Link: ' + shareUrl);
            });
        }
    }
});

// Follow button via AJAX
document.addEventListener('click', (e) => {
    const followBtn = e.target.closest('.feed-follow-btn[data-action="follow"], .user-follow-btn[data-action="follow"]');
    if (!followBtn) return;

    e.preventDefault();
    e.stopPropagation();

    const userId = followBtn.dataset.userId;
    const csrf = followBtn.dataset.csrf;

    const formData = new FormData();
    formData.append('_token', csrf);

    fetch(`/user/${userId}/follow`, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        // Feed follow button
        if (followBtn.classList.contains('feed-follow-btn')) {
            followBtn.classList.toggle('feed-follow-btn--following', data.following);
            
            if (data.following) {
                followBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>';
            } else {
                followBtn.innerHTML = '+';
            }
        }
        
        // User list follow button
        if (followBtn.classList.contains('user-follow-btn')) {
            if (data.following) {
                followBtn.textContent = 'Segui già';
                followBtn.classList.remove('btn--primary');
                followBtn.classList.add('btn--outline');
            } else {
                followBtn.textContent = 'Segui';
                followBtn.classList.remove('btn--outline');
                followBtn.classList.add('btn--primary');
            }
        }
    })
    .catch(err => console.error('Follow error:', err));
});

// Feed: frecce navigazione su/giù e scroll infinito
let _feedScrollCleanup = null;

function initFeedScroll() {
    // Pulisci listener precedenti (evita duplicati con Turbo)
    if (_feedScrollCleanup) {
        _feedScrollCleanup();
        _feedScrollCleanup = null;
    }

    const feed = document.getElementById('feed');
    const prevBtn = document.getElementById('feed-prev');
    const nextBtn = document.getElementById('feed-next');

    if (!feed || !prevBtn || !nextBtn) return;

    const scrollContainer = feed;

    const getVisibleIndex = () => {
        const items = feed.querySelectorAll('.feed-item');
        if (!items.length) return 0;
        const scrollTop = scrollContainer.scrollTop;
        let best = 0;
        let bestDist = Infinity;
        items.forEach((item, i) => {
            const dist = Math.abs(item.offsetTop - scrollTop);
            if (dist < bestDist) { bestDist = dist; best = i; }
        });
        return best;
    };

    const scrollToIndex = (idx) => {
        const items = feed.querySelectorAll('.feed-item');
        if (idx < 0 || idx >= items.length) return;
        scrollContainer.scrollTo({ top: items[idx].offsetTop, behavior: 'smooth' });
    };

    const onPrev = () => scrollToIndex(getVisibleIndex() - 1);
    const onNext = () => scrollToIndex(getVisibleIndex() + 1);
    nextBtn.addEventListener('click', onNext);
    prevBtn.addEventListener('click', onPrev);

    // Scroll infinito — carica in anticipo a metà batch
    let isLoading = false;
    let hasMore = true;
    let batchStart = 0; // indice da cui inizia il batch corrente
    let batchNum = 1;
    let lastLoggedIndex = -1;

    // Logga il libro corrente durante lo scroll
    const logCurrentBook = () => {
        const items = feed.querySelectorAll('.feed-item');
        if (!items.length) return;
        const scrollTop = scrollContainer.scrollTop;
        const viewH = scrollContainer.clientHeight;
        const center = scrollTop + viewH / 2;
        let currentIdx = 0;
        let bestDist = Infinity;
        items.forEach((item, i) => {
            const mid = item.offsetTop + item.offsetHeight / 2;
            const dist = Math.abs(mid - center);
            if (dist < bestDist) { bestDist = dist; currentIdx = i; }
        });
        if (currentIdx !== lastLoggedIndex) {
            lastLoggedIndex = currentIdx;
            const bookId = items[currentIdx].dataset.bookId || '?';
            console.log(`📖 Libro ${currentIdx + 1}/${items.length} (id: ${bookId})`);
        }
    };

    const onScroll = () => {
        logCurrentBook();

        if (isLoading || !hasMore) return;

        const items = feed.querySelectorAll('.feed-item');
        if (!items.length) return;

        // Trigger a metà del batch corrente
        const batchSize = items.length - batchStart;
        const triggerIdx = batchStart + Math.floor(batchSize / 2);
        const triggerItem = items[Math.min(triggerIdx, items.length - 1)];

        const rect = triggerItem.getBoundingClientRect();
        const containerRect = scrollContainer.getBoundingClientRect();

        if (rect.top < containerRect.bottom) {
            loadMoreBooks();
        }
    };

    scrollContainer.addEventListener('scroll', onScroll);

    async function loadMoreBooks() {
        if (isLoading || !hasMore) return;
        
        isLoading = true;
        batchNum++;
        console.log(`⏳ Caricamento batch #${batchNum}...`);

        try {
            const section = feed.dataset.section || 'per-te';
            const beforeCount = feed.querySelectorAll('.feed-item').length;
            const response = await fetch(`/feed/load-more?section=${section}`);
            const data = await response.json();

            if (data.html && data.html.trim()) {
                feed.insertAdjacentHTML('beforeend', data.html);
                batchStart = beforeCount; // il nuovo batch inizia dove finiva il precedente
            }

            const totalItems = feed.querySelectorAll('.feed-item').length;
            console.log(`✅ Batch #${batchNum} — +${totalItems - beforeCount} libri, ${totalItems} totali`);

            hasMore = data.hasMore;
            isLoading = false;
        } catch (error) {
            console.error('Error loading more books:', error);
            isLoading = false;
        }
    }

    // Cleanup per Turbo
    _feedScrollCleanup = () => {
        scrollContainer.removeEventListener('scroll', onScroll);
        nextBtn.removeEventListener('click', onNext);
        prevBtn.removeEventListener('click', onPrev);
    };
}

// Inizializza su turbo:load (copre sia primo caricamento che navigazioni Turbo)
document.addEventListener('turbo:load', initFeedScroll);
// Fallback se Turbo non è attivo
document.addEventListener('DOMContentLoaded', () => {
    if (!document.documentElement.hasAttribute('data-turbo-preview')) {
        initFeedScroll();
    }
});

// ========== BOOK ADD FORM ==========
function initBookAddForm() {
    const isbnInput = document.querySelector('[name="book_form[isbn]"]');
    const searchBtn = document.getElementById('isbn-search-btn');
    const errorDiv = document.getElementById('isbn-error');
    const loadingDiv = document.getElementById('isbn-loading');
    const detailsSection = document.getElementById('book-details-section');

    if (!isbnInput || !searchBtn) return;

    // ISBN search
    searchBtn.addEventListener('click', async () => {
        const isbn = isbnInput.value.trim();
        
        if (!isbn) {
            errorDiv.textContent = 'Inserisci un ISBN';
            errorDiv.style.display = 'block';
            return;
        }

        errorDiv.style.display = 'none';
        loadingDiv.style.display = 'block';
        searchBtn.disabled = true;

        try {
            const url = `/book/fetch-isbn/${encodeURIComponent(isbn)}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            const data = await response.json();

            loadingDiv.style.display = 'none';

            if (data.exists) {
                errorDiv.textContent = `Questo libro è già stato aggiunto da ${data.addedBy}!`;
                errorDiv.style.display = 'block';
                setTimeout(() => {
                    window.location.href = `/book/${data.bookId}`;
                }, 2000);
                return;
            }

            if (data.error) {
                errorDiv.textContent = data.error;
                errorDiv.style.display = 'block';
                searchBtn.disabled = false;
                return;
            }

            // Fill fields
            const titleField = document.querySelector('[name="book_form[title]"]');
            const authorField = document.querySelector('[name="book_form[bookAuthor]"]');
            const descField = document.querySelector('[name="book_form[description]"]');
            const pageCountField = document.querySelector('[name="book_form[pageCount]"]');
            const publisherField = document.querySelector('[name="book_form[publisher]"]');
            const yearField = document.querySelector('[name="book_form[publicationYear]"]');

            if (titleField) titleField.value = data.title || '';
            if (authorField) authorField.value = data.authors || '';
            if (descField) descField.value = data.description || '';
            if (pageCountField) pageCountField.value = data.pageCount || '';
            if (publisherField) publisherField.value = data.publisher || '';
            if (yearField) yearField.value = data.publishedDate || '';

            // Show details section
            detailsSection.style.display = 'block';
            searchBtn.disabled = false;

        } catch (error) {
            console.error('Fetch error:', error);
            loadingDiv.style.display = 'none';
            errorDiv.textContent = 'Errore durante la ricerca. Riprova.';
            errorDiv.style.display = 'block';
            searchBtn.disabled = false;
        }
    });

    // Enter key support
    isbnInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchBtn.click();
        }
    });

    // Genre selector
    const genreSearch = document.getElementById('genre-search');
    const genreSuggestions = document.getElementById('genre-suggestions');
    const selectedGenresDiv = document.getElementById('selected-genres');
    const genreSelect = document.querySelector('select[name="book_form[genres][]"]');

    if (!genreSearch || !genreSelect) return;

    const allGenres = Array.from(genreSelect.options).map(opt => ({
        id: opt.value,
        name: opt.text
    })).filter(g => g.id);

    const selectedGenres = new Set();

    function updateSelectedGenres() {
        selectedGenresDiv.innerHTML = '';
        selectedGenres.forEach(genreId => {
            const genre = allGenres.find(g => g.id === genreId);
            if (!genre) return;

            const tag = document.createElement('span');
            tag.className = 'genre-tag';
            tag.innerHTML = `
                ${genre.name}
                <button type="button" class="genre-tag-remove" data-genre-id="${genreId}">&times;</button>
            `;
            selectedGenresDiv.appendChild(tag);
        });

        Array.from(genreSelect.options).forEach(opt => {
            opt.selected = selectedGenres.has(opt.value);
        });
    }

    function showSuggestions(query) {
        const filtered = allGenres.filter(g => 
            g.name.toLowerCase().includes(query.toLowerCase()) && 
            !selectedGenres.has(g.id)
        );

        if (filtered.length === 0) {
            genreSuggestions.style.display = 'none';
            return;
        }

        genreSuggestions.innerHTML = filtered.map(g => `
            <div class="genre-suggestion-item" data-genre-id="${g.id}">
                ${g.name}
            </div>
        `).join('');
        genreSuggestions.style.display = 'block';
    }

    genreSearch.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        if (query.length > 0) {
            showSuggestions(query);
        } else {
            genreSuggestions.style.display = 'none';
        }
    });

    genreSearch.addEventListener('focus', (e) => {
        if (e.target.value.trim().length > 0) {
            showSuggestions(e.target.value.trim());
        }
    });

    genreSuggestions.addEventListener('click', (e) => {
        const item = e.target.closest('.genre-suggestion-item');
        if (!item) return;

        const genreId = item.dataset.genreId;
        selectedGenres.add(genreId);
        updateSelectedGenres();
        genreSearch.value = '';
        genreSuggestions.style.display = 'none';
    });

    selectedGenresDiv.addEventListener('click', (e) => {
        if (e.target.classList.contains('genre-tag-remove')) {
            const genreId = e.target.dataset.genreId;
            selectedGenres.delete(genreId);
            updateSelectedGenres();
        }
    });

    document.addEventListener('click', (e) => {
        if (!genreSearch.contains(e.target) && !genreSuggestions.contains(e.target)) {
            genreSuggestions.style.display = 'none';
        }
    });
}

// Init on load and on Turbo navigation
document.addEventListener('DOMContentLoaded', initBookAddForm);
document.addEventListener('turbo:load', initBookAddForm);

// ========== REVIEW PANEL ==========

// Hover stelle: evidenzia dalla 1 fino alla stella sotto il cursore
document.addEventListener('mouseover', (e) => {
    const star = e.target.closest('.review-star');
    if (!star) return;
    const container = star.closest('.review-panel__stars');
    if (!container) return;
    const hoverRating = parseInt(star.dataset.star);
    container.querySelectorAll('.review-star').forEach((s, i) => {
        s.classList.toggle('is-hover', i < hoverRating);
    });
});

document.addEventListener('mouseout', (e) => {
    const star = e.target.closest('.review-star');
    if (!star) return;
    const container = star.closest('.review-panel__stars');
    if (!container) return;
    container.querySelectorAll('.review-star').forEach(s => s.classList.remove('is-hover'));
});

document.addEventListener('click', (e) => {

    // Apri/chiudi pannello
    const reviewBtn = e.target.closest('.js-action-review');
    if (reviewBtn) {
        const bookId = reviewBtn.dataset.book;
        const panel  = document.getElementById('review-' + bookId);
        if (!panel) return;

        // Chiudi pannello descrizione se aperto
        const descPanel = document.getElementById('desc-' + bookId);
        if (descPanel) descPanel.classList.remove('is-open');

        panel.classList.toggle('is-open');
        return;
    }

    // Chiudi con il pulsante ×
    const closeBtn = e.target.closest('[data-close-review]');
    if (closeBtn) {
        const panel = document.getElementById('review-' + closeBtn.dataset.closeReview);
        if (panel) panel.classList.remove('is-open');
        return;
    }

    // Click su una stella
    const star = e.target.closest('.review-star');
    if (star) {
        const starsContainer = star.closest('.review-panel__stars');
        if (!starsContainer) return;
        const rating = parseInt(star.dataset.star);
        starsContainer.dataset.selectedRating = rating;
        starsContainer.querySelectorAll('.review-star').forEach((s, i) => {
            s.classList.toggle('is-active', i < rating);
        });
        return;
    }

    // Submit recensione
    const submitBtn = e.target.closest('.review-panel__submit');
    if (submitBtn) {
        const panel   = submitBtn.closest('.feed-card__review-panel');
        if (!panel) return;
        const bookId  = submitBtn.dataset.book;
        const starsEl = panel.querySelector('.review-panel__stars');
        const rating  = parseInt(starsEl?.dataset.selectedRating || 0);
        const content = panel.querySelector('.review-panel__text')?.value || '';

        if (rating === 0) {
            starsEl.classList.add('review-stars--shake');
            setTimeout(() => starsEl.classList.remove('review-stars--shake'), 400);
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Invio…';

        const formData = new FormData();
        formData.append('rating', rating);
        formData.append('content', content);

        fetch(`/book/${bookId}/review`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            panel.classList.remove('is-open');

            // Aggiorna pulsante nel feed
            const btn = document.querySelector(`.js-action-review[data-book="${bookId}"]`);
            if (btn) {
                btn.classList.add('feed-action--reviewed');
                const label = btn.querySelector('.feed-action__label');
                if (label) label.textContent = rating + '★';
                btn.dataset.currentRating  = rating;
                btn.dataset.currentContent = content;
            }

            // Aggiorna testo submit per future modifiche
            submitBtn.textContent = 'Aggiorna recensione';
            submitBtn.disabled = false;

            // Aggiorna valutazione media visibile nella card
            if (data.avgRating) {
                const ratingEl = document.querySelector(`.feed-card__rating[data-book="${bookId}"]`);
                if (ratingEl) ratingEl.textContent = data.avgRating + '/5';
            }
        })
        .catch(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Riprova';
        });
    }
});
