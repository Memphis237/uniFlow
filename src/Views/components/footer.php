</main>
</div>
<footer class="md:ml-[260px] border-t border-slate-200 bg-white px-8 py-6 flex flex-wrap justify-between gap-3 text-xs text-slate-400"><span><b class="text-slate-600">UniFlow.</b> Le talent est juste à côté.</span><span>Fait pour les étudiants, avec les étudiants.</span><a href="index.php?page=help" class="text-slate-500">Une question ?</a></footer>
<div id="serviceModal" class="fixed inset-0 bg-slate-900/40 z-50 hidden items-center justify-center p-4"><div class="bg-white rounded-2xl max-w-lg w-full p-6"><div class="flex justify-between mb-5"><h2 class="text-xl font-bold">Proposer un service</h2><button onclick="toggleServiceModal()" aria-label="Fermer"><i data-lucide="x"></i></button></div><p class="text-sm text-slate-500">La publication de services sera bientôt disponible.</p></div></div>
<div id="locationModal" class="fixed inset-0 bg-slate-900/40 z-50 hidden items-center justify-center p-4"><div class="bg-white rounded-2xl max-w-lg w-full p-6"><div class="flex justify-between mb-4"><h2 class="text-xl font-bold">Localisation</h2><button onclick="toggleLocationModal()" aria-label="Fermer"><i data-lucide="x"></i></button></div><p class="text-slate-600">Campus Lyon et ses alentours.</p></div></div>
<script>
function toggleServiceModal(){const modal=document.getElementById('serviceModal');modal.classList.toggle('hidden');modal.classList.toggle('flex')}
function toggleLocationModal(){const modal=document.getElementById('locationModal');modal.classList.toggle('hidden');modal.classList.toggle('flex')}
lucide.createIcons();
</script>
</body></html>
