<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campuslink - Site web pour techniciens</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50/50 text-gray-800 flex flex-col min-h-screen">

    <header class="max-w-[1600px] mx-auto bg-white border-b border-gray-100 sticky top-0 z-30">
        <div class="bg-white">
            <div class="w-full px-6 h-14 flex items-center justify-between">
                
                <!-- Gauche: Liens & Localisation -->
                <div class="flex items-center space-x-4 pl-0 md:pl-64">
                    <span class="text-xs font-bold text-gray-800 cursor-pointer">Tous les services</span>
                    <span class="text-gray-200 font-light">|</span>
                    <button onclick="toggleLocationModal()" class="flex items-center text-xs font-medium text-teal-600 hover:text-teal-700 transition space-x-1.5">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-gray-400"></i>
                        <span class="text-gray-400 font-normal">Campus Lyon</span>
                    </button>
                </div>

                <!-- Droite: Bouton + Proposer un service, Cloche & Avatar -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <button onclick="toggleServiceModal()" aria-label="Proposer un service" class="flex items-center space-x-1.5 border border-teal-500 text-teal-600 hover:bg-teal-50 px-2 sm:px-3.5 py-1.5 rounded-lg text-xs font-medium transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Proposer un service</span>
                    </button>

                    <button class="text-gray-400 hover:text-gray-600 p-1">
                        <i data-lucide="bell" class="w-4 h-4"></i>
                    </button>

                    <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 cursor-pointer">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=100" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                </div>

            </div>
        </div>
    </header>

    <div class="flex flex-1 max-w-[1600px] w-full mx-auto">