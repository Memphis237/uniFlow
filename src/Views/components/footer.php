</div> <!-- Fin du container flex principal -->

    <!-- Footer bas de page -->
    <footer class="bg-white border-t border-gray-100 py-6 mt-auto">
        <div class="max-w-[1600px] mx-auto px-8 flex flex-col sm:flex-row items-center justify-between text-[11px] text-gray-400 space-y-2 sm:space-y-0">
            <div class="flex items-center space-x-1">
                <span class="font-bold text-gray-600">campuslink<span class="text-teal-500">.</span></span>
                <span>Le talent est juste à côté.</span>
            </div>
            <div>
                <span>Fait pour les étudiants, avec les étudiants.</span>
            </div>
            <div>
                <a href="#" class="hover:underline text-gray-500">Une question ?</a>
            </div>
        </div>
    </footer>

    <!-- Modal 1: Proposer un service -->
    <div id="serviceModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <button onclick="toggleServiceModal()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-600 p-1">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>

            <div class="mb-6">
                <h3 class="text-2xl font-extrabold text-gray-900">Ton talent mérite d'être partagé.</h3>
                <p class="text-sm text-gray-500 mt-2">Un petit service pour eux. Un coup de pouce pour ton budget.</p>
            </div>

            <form action="save_service.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Le titre de ton service</label>
                    <input type="text" name="title" required placeholder="Ex. Des cours d'anglais pour progresser" 
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">La catégorie</label>
                    <select name="category" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 text-sm bg-white">
                        <option value="coiffure">Coiffure & beauté</option>
                        <option value="cours">Cours & soutien</option>
                        <option value="demenagement">Déménagement</option>
                        <option value="informatique">Informatique</option>
                        <option value="photo">Photo & créa</option>
                        <option value="autres">Autres</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Ton prix (€)</label>
                        <input type="number" name="price" placeholder="15" required 
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Pour...</label>
                        <select name="unit" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 text-sm bg-white">
                            <option value="heure">Une heure</option>
                            <option value="prestation">La prestation</option>
                            <option value="jour">Par jour</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Raconte ce que tu proposes</label>
                    <textarea name="description" rows="3" placeholder="Tes compétences, ce qui est inclus, tes disponibilités..." 
                              class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 text-sm resize-none"></textarea>
                </div>

                <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-3 flex items-start space-x-3">
                    <i data-lucide="shield-check" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5"></i>
                    <p class="text-xs text-emerald-800">
                        Ton offre sera associée à ton profil étudiant. Une photo de catégorie sera ajoutée automatiquement.
                    </p>
                </div>

                <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3.5 px-4 rounded-xl flex items-center justify-center space-x-2 transition shadow-sm mt-4">
                    <span>Publier mon service</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal 2: Localisation Campus Lyon -->
    <div id="locationModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl relative">
            
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Localisation</h3>
                <button onclick="toggleLocationModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="flex items-center space-x-3 mb-4">
                <div class="bg-emerald-50 p-2.5 rounded-xl text-teal-600">
                    <i data-lucide="map-pin" class="w-5 h-5"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-900">Campus Lyon</h4>
                    <p class="text-xs text-gray-500">Campus Lyon et alentours</p>
                </div>
            </div>

            <div class="relative rounded-2xl overflow-hidden border border-gray-100 mb-4">
                <iframe class="w-full h-64 border-0" src="https://www.openstreetmap.org/export/embed.html?bbox=4.8000%2C45.7200%2C4.8900%2C45.7800&amp;layer=mapnik" loading="lazy"></iframe>
                <div class="absolute bottom-2 left-2 right-2 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-[10px] text-gray-500 text-center">
                    La position affichée est indicative pour ce prototype local.
                </div>
            </div>

            <a href="https://maps.google.com" target="_blank" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-4 rounded-xl flex items-center justify-center space-x-2 transition">
                <span>Ouvrir l'itinéraire dans Google Maps</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function toggleServiceModal() {
            document.getElementById('serviceModal').classList.toggle('hidden');
        }

        function toggleLocationModal() {
            document.getElementById('locationModal').classList.toggle('hidden');
        }
    </script>
</body>
</html>