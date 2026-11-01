<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Espace scolaire du {{ $centreSettings['name'] }} : suivi des élèves, des présences, des notes, des emplois du temps et des paiements.">
        <title>{{ $centreSettings['name'] }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ $centreSettings['name'] }} - Accueil">
                    <x-application-logo class="h-11 w-11 text-teal-700" />
                    <span class="max-w-[15rem] text-sm font-bold leading-tight text-slate-900 sm:text-base">{{ $centreSettings['name'] }}</span>
                </a>
                <a href="{{ Auth::check() ? route('dashboard') : route('login') }}" class="inline-flex items-center justify-center rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                    {{ Auth::check() ? 'Mon espace' : 'Se connecter' }}
                </a>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden bg-slate-950 text-white">
                <div class="absolute inset-0 bg-gradient-to-br from-teal-900/70 via-slate-950 to-slate-950"></div>
                <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 py-20 sm:py-24 lg:grid-cols-[1.2fr_0.8fr] lg:px-8 lg:py-32">
                    <div class="max-w-2xl">
                        <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-teal-300/30 bg-teal-300/10 px-3 py-1.5 text-sm font-medium text-teal-100">
                            <span class="h-2 w-2 rounded-full bg-teal-300"></span>
                            Espace scolaire
                        </p>
                        <h1 class="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            Toute la vie scolaire,
                            <span class="text-teal-300">au même endroit.</span>
                        </h1>
                        <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                            {{ $centreSettings['name'] }} accompagne le suivi des élèves, des familles et des équipes pédagogiques dans un espace simple et sécurisé.
                        </p>
                        <div class="mt-9 flex flex-wrap items-center gap-4">
                            <a href="{{ Auth::check() ? route('dashboard') : route('login') }}" class="inline-flex items-center justify-center rounded-lg bg-teal-400 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200 focus:ring-offset-2 focus:ring-offset-slate-950">
                                Accéder à mon espace
                                <span aria-hidden="true" class="ms-2">→</span>
                            </a>
                            <a href="#services" class="text-sm font-semibold text-white underline decoration-slate-500 underline-offset-4 transition hover:decoration-white">
                                Découvrir les services
                            </a>
                        </div>
                    </div>

                    <div class="mx-auto w-full max-w-md">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl backdrop-blur sm:p-8">
                            <div class="flex items-center gap-4 border-b border-white/10 pb-6">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-300/10 text-teal-300">
                                    <x-application-logo class="h-8 w-8" />
                                </div>
                                <div>
                                    <p class="font-semibold text-white">{{ $centreSettings['name'] }}</p>
                                    <p class="mt-1 text-sm text-slate-400">Un suivi clair, au quotidien</p>
                                </div>
                            </div>
                            <div class="mt-6 grid grid-cols-2 gap-3">
                                @foreach (['Présences', 'Notes', 'Emploi du temps', 'Paiements'] as $service)
                                    <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-slate-900/60 px-3 py-3 text-sm text-slate-200">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-teal-300/10 text-xs font-bold text-teal-300" aria-hidden="true">✓</span>
                                        {{ $service }}
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-6 text-sm leading-6 text-slate-400">
                                Les informations sont accessibles selon le profil : administration, équipe enseignante, parents et élèves.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="services" class="mx-auto max-w-7xl px-6 py-20 sm:py-24 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold uppercase tracking-widest text-teal-700">Un espace partagé</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Les repères essentiels pour chaque journée.</h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">
                        Retrouvez les principaux outils de suivi scolaire dans une interface adaptée à chaque membre du centre.
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-lg font-bold text-amber-800" aria-hidden="true">P</div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Présences</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Consultez les présences et retrouvez l’historique des absences des élèves.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-100 text-lg font-bold text-sky-800" aria-hidden="true">N</div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Notes et évaluations</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Suivez les évaluations, les résultats et les moyennes scolaires.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-lg font-bold text-violet-800" aria-hidden="true">E</div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Emploi du temps</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Consultez les horaires des cours, des classes et des enseignants.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-lg font-bold text-emerald-800" aria-hidden="true">F</div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Suivi financier</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">L’administration suit les échéanciers, les paiements et les reçus.</p>
                    </article>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <p class="font-medium text-slate-700">{{ $centreSettings['name'] }}</p>
                <p>Portail de suivi scolaire</p>
            </div>
        </footer>
    </body>
</html>
