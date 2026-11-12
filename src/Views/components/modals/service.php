<div
    id="serviceModal"
    data-commenter="<?= htmlspecialchars(trim((string) (($user['first_name'] ?? '') . ' ' . ($user['name'] ?? ''))) ?: 'Moi', ENT_QUOTES, 'UTF-8') ?>"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="serviceModalTitle"
>
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
            <h2 id="serviceModalTitle" class="text-xl font-bold">Ton talent mérite d’être partagé.</h2>
            <button type="button" onclick="toggleServiceModal()" aria-label="Fermer" class="rounded-lg border p-2">
                <i data-lucide="x"></i>
            </button>
        </div>

        <form id="serviceForm" class="space-y-4 p-6" enctype="multipart/form-data">
            <p class="text-sm text-slate-500">Un petit service pour eux. Un coup de pouce pour ton budget.</p>
            <label class="block text-sm">
                Le titre de ton service
                <input name="title" required maxlength="255" class="mt-2 w-full rounded-lg border p-3" placeholder="Ex. Des cours d’anglais pour progresser">
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    La catégorie
                    <select name="category" required class="mt-2 w-full rounded-lg border p-3">
                        <option value="Cours & soutien">Cours & soutien</option>
                        <option>Coiffure & beauté</option>
                        <option>Déménagement</option>
                        <option>Informatique</option>
                        <option>Photo & créa</option>
                        <option>Autres</option>
                    </select>
                </label>
                <label class="block text-sm">
                    Ton prix (€)
                    <input name="price" required type="number" min="0" step="0.01" class="mt-2 w-full rounded-lg border p-3" placeholder="15">
                </label>
            </div>
            <label class="block text-sm">
                Pour...
                <input name="unit" required maxlength="80" class="mt-2 w-full rounded-lg border p-3" placeholder="Une heure">
            </label>
            <label class="block text-sm">
                Raconte ce que tu proposes
                <textarea name="description" required maxlength="1000" rows="4" class="mt-2 w-full rounded-lg border p-3" placeholder="Tes compétences, ce qui est inclus, tes disponibilités..."></textarea>
            </label>
            <div class="rounded-xl border border-dashed border-teal-300 bg-teal-50/60 p-4">
                <label for="serviceImages" class="flex cursor-pointer items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-teal-700 shadow-sm">
                        <i data-lucide="images" class="h-5 w-5"></i>
                    </span>
                    <span>
                        <strong class="block text-sm text-slate-800">Photos du service</strong>
                        <small class="text-xs text-slate-500">Ajoute jusqu’à 8 images (JPG, PNG ou WebP, 5 Mo chacune)</small>
                    </span>
                    <span class="ml-auto rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white">Choisir</span>
                </label>
                <input id="serviceImages" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required class="sr-only">
                <p id="serviceImageSelection" class="mt-3 text-xs text-slate-500" aria-live="polite">Aucune photo sélectionnée</p>
            </div>
            <p id="serviceFormStatus" class="text-sm" role="status"></p>
            <button class="w-full rounded-lg bg-teal-700 py-3 font-semibold text-white">Publier mon service</button>
        </form>
    </div>
</div>

<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div class="flex justify-between p-5">
            <h2 id="detailTitle" class="text-xl font-bold"></h2>
            <button type="button" onclick="closeDetailModal()" aria-label="Fermer">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="relative bg-slate-100">
            <img id="detailImage" class="h-64 w-full object-cover" alt="">
            <button type="button" id="detailImagePrev" aria-label="Photo précédente" class="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 shadow hover:bg-white">
                <i data-lucide="chevron-left"></i>
            </button>
            <button type="button" id="detailImageNext" aria-label="Photo suivante" class="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 shadow hover:bg-white">
                <i data-lucide="chevron-right"></i>
            </button>
            <span id="detailImageCount" class="absolute bottom-3 right-3 rounded-full bg-slate-950/65 px-3 py-1 text-xs font-medium text-white"></span>
        </div>
        <div class="p-6">
            <p id="detailOwner" class="text-sm text-teal-700"></p>
            <p id="detailDescription" class="mt-3 text-slate-600"></p>
            <div class="mt-5 flex items-center gap-4">
                <button id="detailLike" type="button" class="inline-flex items-center gap-2 text-rose-600">
                    <i data-lucide="heart"></i><span></span>
                </button>
                <button id="detailShare" type="button" class="inline-flex gap-2">
                    <i data-lucide="share-2"></i>Partager
                </button>
            </div>
            <form id="bookingForm" class="mt-6 grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Proposer un rendez-vous<input name="scheduled_at" required type="datetime-local" class="mt-2 w-full rounded-lg border p-3"></label>
                <label class="text-sm">Message (facultatif)<input name="message" maxlength="255" class="mt-2 w-full rounded-lg border p-3"></label>
                <button class="rounded-lg bg-teal-700 py-3 text-white sm:col-span-2">Demander un rendez-vous</button>
            </form>
            <button id="contactOwner" type="button" class="mt-3 w-full rounded-lg border border-teal-700 py-3 text-teal-800">Contacter</button>
            <h3 class="mt-7 font-semibold">Avis</h3>
            <div id="detailReviews" class="mt-3 space-y-3"></div>
            <form id="reviewForm" class="mt-4 flex flex-wrap gap-2">
                <select name="rating" class="rounded-lg border p-2" aria-label="Note">
                    <option value="5">5 étoiles</option><option value="4">4 étoiles</option>
                    <option value="3">3 étoiles</option><option value="2">2 étoiles</option>
                    <option value="1">1 étoile</option>
                </select>
                <input name="comment" required maxlength="255" class="min-w-0 flex-1 rounded-lg border p-2" placeholder="Écrire un avis après le rendez-vous">
                <button class="rounded-lg bg-teal-700 px-4 text-white">Noter</button>
            </form>
            <p id="detailStatus" class="mt-2 text-sm" role="status"></p>
        </div>
    </div>
</div>
