<!-- Sidebar Navbar -->
<aside class="w-64 bg-white border-r border-gray-100 p-5 hidden md:flex flex-col justify-start shrink-0 sticky top-14 self-start h-[calc(100vh-3.5rem)] overflow-y-auto">
    <div class="space-y-9">
        
        <!-- Logo Campuslink -->
        <div class="flex items-center space-x-2 px-1">
            <div class="bg-teal-500 text-white p-1.5 rounded-xl">
                <i data-lucide="graduation-cap" class="w-5 h-5"></i>
            </div>
            <span class="text-xl font-extrabold text-gray-900 tracking-tight">campuslink<span class="text-teal-500">.</span></span>
        </div>

        <!-- Badge Mon Campus -->
        <div class="bg-emerald-50/50 p-3 rounded-2xl flex items-center space-x-3 border border-emerald-100/60 cursor-pointer hover:bg-emerald-50 transition" onclick="toggleLocationModal()">
            <div class="bg-white p-2 rounded-xl text-teal-600 shadow-sm border border-teal-50">
                <i data-lucide="graduation-cap" class="w-4 h-4"></i>
            </div>
            <div class="flex-1">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mon campus</p>
                <p class="text-xs font-bold text-gray-800">Université de Lyon</p>
            </div>
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
        </div>

        <!-- Links de Navigation -->
        <nav class="space-y-1">
            <p class="px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Mon espace</p>
            
            <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-gray-600 hover:bg-gray-50 transition">
                <i data-lucide="home" class="w-4 h-4"></i>
                <span class="text-xs font-medium">Découvrir</span>
            </a>

            <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-xl bg-emerald-50/80 text-teal-700 font-bold">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
                <span class="text-xs">Tous les services</span>
            </a>

            <a href="#" class="flex items-center justify-between px-3 py-2 rounded-xl text-gray-600 hover:bg-gray-50 transition">
                <div class="flex items-center space-x-3">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                    <span class="text-xs font-medium">Mon campus</span>
                </div>
                <span class="bg-cyan-100 text-cyan-700 text-[9px] font-bold px-1.5 py-0.5 rounded-md">Nouveau</span>
            </a>

            <a href="#" class="flex items-center justify-between px-3 py-2 rounded-xl text-gray-600 hover:bg-gray-50 transition">
                <div class="flex items-center space-x-3">
                    <i data-lucide="message-square" class="w-4 h-4"></i>
                    <span class="text-xs font-medium">Messages</span>
                </div>
                <span class="bg-teal-600 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">2</span>
            </a>

            <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-gray-600 hover:bg-gray-50 transition">
                <i data-lucide="heart" class="w-4 h-4"></i>
                <span class="text-xs font-medium">Mes favoris</span>
            </a>
        </nav>

        <!-- Card "Ton talent a de la valeur." -->
        <div class="bg-emerald-50/40 border border-emerald-100/80 rounded-2xl p-4 text-left space-y-3 relative overflow-hidden">
            <div class="w-7 h-7 bg-emerald-100 text-teal-600 rounded-lg flex items-center justify-center">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-900">Ton talent a de la valeur.</h4>
                <p class="text-[11px] text-gray-500 mt-1 leading-relaxed">Partage tes savoir-faire et meuble tes fins de mois.</p>
            </div>
            <button onclick="toggleServiceModal()" class="w-full bg-white hover:bg-emerald-50 text-teal-700 border border-emerald-200 font-semibold py-2 px-3 rounded-xl text-xs flex items-center justify-center space-x-1.5 shadow-sm transition">
                <span>Proposer un service</span>
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            </button>
        </div>
    </div>

    <!-- Liens du bas & Profil utilisateur Alex Martin -->
    <div class="space-y-3 border-t border-gray-100">
        <div class="space-y-0.5">
            <a href="#" class="flex items-center space-x-3 px-3 py-1.5 rounded-lg text-gray-500 hover:bg-gray-50 text-xs font-medium">
                <i data-lucide="user" class="w-4 h-4"></i>
                <span>Mon profil</span>
            </a>
            <a href="#" class="flex items-center space-x-3 px-3 py-1.5 rounded-lg text-gray-500 hover:bg-gray-50 text-xs font-medium">
                <i data-lucide="help-circle" class="w-4 h-4"></i>
                <span>Aide & conseils</span>
            </a>
            <a href="#" class="flex items-center space-x-3 px-3 py-1.5 rounded-lg text-gray-400 hover:bg-gray-50 text-xs font-medium">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>Se déconnecter</span>
            </a>
        </div>

        <div class="flex items-center justify-between p-2 rounded-xl hover:bg-gray-50 cursor-pointer border border-transparent hover:border-gray-100">
            <div class="flex items-center space-x-2.5">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100" class="w-8 h-8 rounded-full object-cover" alt="Alex Martin">
                <div>
                    <p class="text-xs font-bold text-gray-800">Alex Martin</p>
                    <p class="text-[10px] text-gray-400">Étudiant vérifié</p>
                </div>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
        </div>
    </div>
</aside>