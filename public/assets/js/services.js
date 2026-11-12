(() => {
    const serviceModal = document.getElementById('serviceModal');
    const detailModal = document.getElementById('detailModal');
    const locationModal = document.getElementById('locationModal');
    const commenter = serviceModal?.dataset.commenter || 'Moi';
    const selectedService = { id: 0 };
    let detailImages = [];
    let detailImageIndex = 0;

    window.toggleServiceModal = () => {
        serviceModal?.classList.toggle('hidden');
        serviceModal?.classList.toggle('flex');
    };

    window.closeDetailModal = () => {
        detailModal?.classList.add('hidden');
        detailModal?.classList.remove('flex');
    };

    window.showLocationModal = (address, fromProfile = false) => {
        if (!locationModal) return;
        const place = (address || 'Campus Lyon').trim();
        const phone = document.getElementById('locationPhone');
        document.getElementById('locationPlace').textContent = place;
        document.getElementById('locationDescription').textContent = fromProfile
            ? 'Adresse issue de ton profil'
            : 'Localisation du service';
        document.getElementById('locationMapFrame').src = `https://maps.google.com/maps?q=${encodeURIComponent(place)}&output=embed`;
        document.getElementById('locationDirections').href = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(place)}`;
        phone.classList.toggle('hidden', !fromProfile || !phone.textContent.replace('Téléphone :', '').trim());
        locationModal.classList.remove('hidden');
        locationModal.classList.add('flex');
    };

    window.toggleLocationModal = () => {
        if (locationModal?.classList.contains('hidden')) {
            window.showLocationModal(locationModal.dataset.profileLocation || 'Campus Lyon', true);
        } else {
            locationModal?.classList.add('hidden');
            locationModal?.classList.remove('flex');
        }
    };

    function renderDetailImage() {
        const image = document.getElementById('detailImage');
        const count = document.getElementById('detailImageCount');
        const hasMultiple = detailImages.length > 1;
        image.src = detailImages[detailImageIndex] || '';
        count.textContent = hasMultiple ? `${detailImageIndex + 1} / ${detailImages.length}` : '';
        document.getElementById('detailImagePrev').classList.toggle('hidden', !hasMultiple);
        document.getElementById('detailImageNext').classList.toggle('hidden', !hasMultiple);
    }

    function renderReviews(reviews) {
        const container = document.getElementById('detailReviews');
        container.replaceChildren();

        if (!reviews.length) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-slate-500';
            empty.textContent = 'Aucun avis pour le moment.';
            container.append(empty);
            return;
        }

        for (const review of reviews) {
            const row = document.createElement('p');
            row.className = 'rounded-lg bg-slate-50 p-3 text-sm';
            const stars = document.createElement('span');
            stars.className = 'mr-2 text-amber-500';

            for (let index = 0; index < Math.max(0, Math.min(5, Number(review.rating) || 0)); index += 1) {
                const star = document.createElement('i');
                star.className = 'fa-solid fa-star';
                star.setAttribute('aria-hidden', 'true');
                stars.append(star);
            }

            const author = [review.first_name, review.name].filter(Boolean).join(' ');
            row.append(stars, document.createTextNode(`${author} — ${review.comment || ''}`));
            container.append(row);
        }
    }

    function renderSeedReview(card) {
        const container = document.getElementById('detailReviews');
        const row = document.createElement('p');
        row.className = 'text-sm text-slate-500';
        const rating = document.createElement('span');
        rating.textContent = card.dataset.seedRating || '0.0';
        const star = document.createElement('i');
        star.className = 'fa-solid fa-star ml-1 text-amber-400';
        star.setAttribute('aria-hidden', 'true');
        const comment = document.createElement('span');
        comment.textContent = ` — ${card.querySelector('.latest-comment')?.textContent.trim() || 'Aucun avis pour le moment.'}`;
        row.append(rating, star, comment);
        container.replaceChildren(row);
    }

    async function loadServiceDetails(serviceId) {
        const response = await fetch(`index.php?api=service-detail&id=${encodeURIComponent(serviceId)}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Impossible de charger ce service.');

        document.getElementById('detailLike').querySelector('span').textContent = `${data.service.likes} j’aime`;
        document.getElementById('detailDescription').textContent = data.service.description || '';
        detailImages = (data.service.images || [data.service.image]).filter(Boolean);
        detailImageIndex = 0;
        renderDetailImage();
        renderReviews(data.reviews || []);
    }

    document.getElementById('detailImagePrev')?.addEventListener('click', () => {
        detailImageIndex = (detailImageIndex - 1 + detailImages.length) % detailImages.length;
        renderDetailImage();
    });
    document.getElementById('detailImageNext')?.addEventListener('click', () => {
        detailImageIndex = (detailImageIndex + 1) % detailImages.length;
        renderDetailImage();
    });

    document.getElementById('serviceImages')?.addEventListener('change', (event) => {
        const files = Array.from(event.target.files || []);
        const label = document.getElementById('serviceImageSelection');
        label.textContent = files.length ? files.map((file) => file.name).join(' / ') : 'Aucune photo sélectionnée';
        if (files.length > 8) {
            label.textContent = 'Maximum 8 photos.';
            event.target.value = '';
        }
    });

    document.getElementById('serviceForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const status = document.getElementById('serviceFormStatus');
        const submit = form.querySelector('button[type="submit"], button:not([type])');
        status.textContent = 'Publication en cours…';
        submit.disabled = true;

        try {
            const response = await fetch('index.php?api=service-create', { method: 'POST', body: new FormData(form) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Publication impossible.');
            status.textContent = 'Service publié. Actualisation de la liste…';
            window.setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            status.textContent = error.message;
            submit.disabled = false;
        }
    });

    document.querySelectorAll('.service-location-button').forEach((button) => {
        button.addEventListener('click', () => window.showLocationModal(button.dataset.location));
    });

    document.querySelectorAll('.discover-button').forEach((button) => {
        button.addEventListener('click', async () => {
            const card = button.closest('.service-card');
            document.getElementById('detailTitle').textContent = card.querySelector('h3').textContent;
            document.getElementById('detailOwner').textContent = card.dataset.owner;
            document.getElementById('detailDescription').textContent = card.dataset.description;
            document.getElementById('detailLike').querySelector('span').textContent = `${card.dataset.seedLikes} j’aime`;
            detailImages = [card.dataset.image].filter(Boolean);
            detailImageIndex = 0;
            selectedService.id = Number(card.dataset.id || 0);
            renderDetailImage();
            renderSeedReview(card);
            document.getElementById('detailStatus').textContent = '';
            detailModal.classList.remove('hidden');
            detailModal.classList.add('flex');

            if (selectedService.id) {
                try {
                    await loadServiceDetails(selectedService.id);
                } catch (error) {
                    document.getElementById('detailStatus').textContent = error.message;
                }
            }
            if (window.lucide) window.lucide.createIcons();
        });
    });

    document.getElementById('detailLike')?.addEventListener('click', async () => {
        if (!selectedService.id) return;
        const response = await fetch(`index.php?api=service-like&id=${selectedService.id}`, { method: 'POST' });
        const data = await response.json();
        if (response.ok) document.getElementById('detailLike').querySelector('span').textContent = `${data.likes} j’aime`;
    });

    document.getElementById('detailShare')?.addEventListener('click', () => {
        const shareData = { title: document.getElementById('detailTitle').textContent, url: window.location.href };
        if (navigator.share) navigator.share(shareData);
        else navigator.clipboard?.writeText(shareData.url);
    });

    document.getElementById('contactOwner')?.addEventListener('click', async () => {
        const status = document.getElementById('detailStatus');
        if (!selectedService.id) {
            status.textContent = 'La messagerie sera disponible pour ce service publié.';
            return;
        }
        const body = new URLSearchParams({ service_id: String(selectedService.id) });
        const response = await fetch('index.php?api=contact', { method: 'POST', body });
        const data = await response.json();
        if (data.redirect) window.location.href = data.redirect;
        else status.textContent = data.error || '';
    });

    for (const [formId, api] of [['bookingForm', 'booking-create'], ['reviewForm', 'review-create']]) {
        document.getElementById(formId)?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const status = document.getElementById('detailStatus');
            if (!selectedService.id) {
                status.textContent = 'Cette action est disponible après publication du service dans la base.';
                return;
            }

            const body = new FormData(event.currentTarget);
            body.append('service_id', String(selectedService.id));
            try {
                const response = await fetch(`index.php?api=${api}`, { method: 'POST', body });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || 'Une erreur est survenue.');
                status.textContent = api === 'review-create' ? 'Avis enregistré.' : 'Demande enregistrée.';
                if (api === 'review-create') {
                    event.currentTarget.reset();
                    await loadServiceDetails(selectedService.id);
                }
            } catch (error) {
                status.textContent = error.message;
            }
        });
    }

    document.querySelectorAll('.service-card').forEach((card) => {
        const storageKey = `uniflow:service:${card.dataset.serviceKey}`;
        const serviceId = Number(card.dataset.id || 0);
        let state;
        try {
            state = JSON.parse(localStorage.getItem(storageKey));
        } catch {
            state = null;
        }

        const databaseComments = Number(card.dataset.seedComments) || 0;
        if (!state || typeof state !== 'object') {
            state = {
                liked: card.dataset.liked === 'true',
                likes: Number(card.dataset.seedLikes),
                comments: databaseComments && card.dataset.seedComment
                    ? [{ author: card.dataset.seedAuthor, text: card.dataset.seedComment }]
                    : [],
            };
        }
        if (serviceId) {
            state.liked = card.dataset.liked === 'true';
            state.likes = Number(card.dataset.seedLikes);
        }

        const likeButtons = [card.querySelector('.like-button'), card.querySelector('.favorite-button')].filter(Boolean);
        const likeCount = card.querySelector('.like-count');
        const commentCount = card.querySelector('.comment-count');
        const commentPanel = card.querySelector('.comment-panel');
        const commentList = card.querySelector('.comment-list');
        const latest = card.querySelector('.latest-comment');

        function draw() {
            likeButtons.forEach((button) => {
                button.setAttribute('aria-pressed', String(state.liked));
                if (button.classList.contains('favorite-button')) {
                    button.setAttribute('aria-label', state.liked ? 'Retirer des favoris' : 'Ajouter aux favoris');
                }
                button.classList.toggle('text-rose-500', state.liked);
                button.querySelector('svg, i')?.classList.toggle('fill-current', state.liked);
            });
            likeCount.textContent = String(state.likes);
            commentCount.textContent = String(Math.max(databaseComments, state.comments.length));
            commentList.replaceChildren();

            state.comments.slice(-3).reverse().forEach((comment) => {
                const row = document.createElement('p');
                row.className = 'rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-600';
                const author = document.createElement('strong');
                author.className = 'text-slate-800';
                author.textContent = `${comment.author} `;
                row.append(author, document.createTextNode(comment.text));
                commentList.append(row);
            });

            const last = state.comments[state.comments.length - 1];
            if (last) {
                latest.replaceChildren();
                const author = document.createElement('strong');
                author.className = 'text-slate-700';
                author.textContent = `${last.author} `;
                latest.append(author, document.createTextNode(last.text));
            } else if (!databaseComments) {
                latest.textContent = 'Pas encore d’avis.';
            }
        }

        async function toggleLike() {
            if (serviceId) {
                likeButtons.forEach((button) => { button.disabled = true; });
                try {
                    const response = await fetch(`index.php?api=service-like&id=${serviceId}`, { method: 'POST' });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.error || 'Action impossible.');
                    state.liked = Boolean(data.liked);
                    state.likes = Number(data.likes);
                    card.dataset.liked = String(state.liked);
                    if (!state.liked && new URLSearchParams(location.search).get('page') === 'favorites') {
                        window.location.reload();
                        return;
                    }
                } catch (error) {
                    window.alert(error.message || 'Impossible de modifier le favori.');
                } finally {
                    likeButtons.forEach((button) => { button.disabled = false; });
                }
            } else {
                state.liked = !state.liked;
                state.likes = Math.max(0, state.likes + (state.liked ? 1 : -1));
                localStorage.setItem(storageKey, JSON.stringify(state));
            }
            draw();
        }

        draw();
        likeButtons.forEach((button) => button.addEventListener('click', toggleLike));
        const toggle = card.querySelector('.comment-toggle');
        toggle?.addEventListener('click', () => {
            const open = commentPanel.classList.contains('hidden');
            commentPanel.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', String(open));
        });
        card.querySelector('.comment-form')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = card.querySelector('.comment-input');
            const text = input.value.trim();
            if (!text) return;
            state.comments.push({ author: commenter, text });
            if (!serviceId) localStorage.setItem(storageKey, JSON.stringify(state));
            draw();
            input.value = '';
        });
    });

    if (window.lucide) window.lucide.createIcons();
})();
