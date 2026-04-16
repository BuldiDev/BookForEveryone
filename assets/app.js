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

// Feed: pannello descrizione (toggle)
document.addEventListener('click', (e) => {
    const descBtn = e.target.closest('.js-action-description');
    if (descBtn) {
        const bookId = descBtn.dataset.book;
        const panel = document.getElementById('desc-' + bookId);
        if (panel) panel.classList.toggle('is-open');
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
document.addEventListener('DOMContentLoaded', () => {
    const feed = document.getElementById('feed');
    const feedCenter = document.querySelector('.feed-center');
    const prevBtn = document.getElementById('feed-prev');
    const nextBtn = document.getElementById('feed-next');

    if (feed && feedCenter && prevBtn && nextBtn) {
        const scrollToItem = (direction) => {
            const items = feed.querySelectorAll('.feed-item');
            if (!items.length) return;

            const feedRect = feedCenter.getBoundingClientRect();
            const feedCenterY = feedRect.top + feedRect.height / 2;
            let currentIndex = 0;

            // Find which item is currently most visible
            items.forEach((item, i) => {
                const itemRect = item.getBoundingClientRect();
                const itemCenter = itemRect.top + itemRect.height / 2;
                if (Math.abs(itemCenter - feedCenterY) < Math.abs(items[currentIndex].getBoundingClientRect().top + items[currentIndex].getBoundingClientRect().height / 2 - feedCenterY)) {
                    currentIndex = i;
                }
            });

            const targetIndex = direction === 'next'
                ? Math.min(currentIndex + 1, items.length - 1)
                : Math.max(currentIndex - 1, 0);

            items[targetIndex].scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        nextBtn.addEventListener('click', () => scrollToItem('next'));
        prevBtn.addEventListener('click', () => scrollToItem('prev'));

        // Scroll infinito
        let currentPage = 1;
        let isLoading = false;
        let hasMore = true;

        feedCenter.addEventListener('scroll', () => {
            if (isLoading || !hasMore) return;

            const items = feed.querySelectorAll('.feed-item');
            const totalItems = items.length;
            
            // Trova quale libro è attualmente visibile
            const feedRect = feedCenter.getBoundingClientRect();
            const feedCenterY = feedRect.top + feedRect.height / 2;
            
            let currentVisibleIndex = 0;
            items.forEach((item, i) => {
                const itemRect = item.getBoundingClientRect();
                const itemCenter = itemRect.top + itemRect.height / 2;
                if (itemCenter > feedCenterY - 200 && itemCenter < feedCenterY + 200) {
                    currentVisibleIndex = i;
                }
            });
            
            // Carica quando mancano 15 libri alla fine
            const remainingItems = totalItems - currentVisibleIndex;
            if (remainingItems <= 15) {
                loadMoreBooks();
            }
        });

        async function loadMoreBooks() {
            if (isLoading || !hasMore) return;
            
            isLoading = true;
            currentPage++;

            try {
                const section = feed.dataset.section || 'per-te';
                const response = await fetch(`/feed/load-more?page=${currentPage}&section=${section}`);
                const data = await response.json();

                if (data.html) {
                    feed.insertAdjacentHTML('beforeend', data.html);
                }

                hasMore = data.hasMore;
                isLoading = false;
            } catch (error) {
                console.error('Error loading more books:', error);
                isLoading = false;
            }
        }
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
