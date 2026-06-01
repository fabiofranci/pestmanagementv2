<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Accesso clienti | {{ config('app.name', 'Pest Management V2') }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            :root {
                --tenant-login-bg: linear-gradient(160deg, #0b1f19 0%, #12382d 36%, #d8e7c6 36%, #eef4e8 100%);
                --tenant-login-hero-bg: rgba(11, 31, 25, 0.82);
                --tenant-login-panel-bg: rgba(255, 255, 255, 0.9);
                --tenant-login-panel-border: rgba(11, 31, 25, 0.12);
                --tenant-login-accent: #0f6b52;
                --tenant-login-accent-hover: #0b573f;
                --tenant-login-accent-soft: rgba(15, 107, 82, 0.16);
            }

            body.tenant-login-page {
                min-height: 100vh;
                margin: 0;
                background: var(--tenant-login-bg);
                color: #0f172a;
            }

            .tenant-login-shell {
                position: relative;
                min-height: 100vh;
                overflow: hidden;
            }

            .tenant-login-shell::before,
            .tenant-login-shell::after {
                content: '';
                position: absolute;
                border-radius: 9999px;
                pointer-events: none;
                filter: blur(30px);
            }

            .tenant-login-shell::before {
                top: 3rem;
                left: -4rem;
                width: 18rem;
                height: 18rem;
                background: radial-gradient(circle, rgba(179, 217, 98, 0.28) 0%, rgba(179, 217, 98, 0) 68%);
            }

            .tenant-login-shell::after {
                right: -5rem;
                bottom: -4rem;
                width: 22rem;
                height: 22rem;
                background: radial-gradient(circle, rgba(255, 236, 187, 0.35) 0%, rgba(255, 236, 187, 0) 70%);
            }

            .tenant-login-main {
                position: relative;
                z-index: 1;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                max-width: 80rem;
                margin: 0 auto;
                padding: 2rem 1.5rem;
            }

            .tenant-login-hero {
                background: var(--tenant-login-hero-bg);
                color: #fff;
                box-shadow: 0 40px 120px -56px rgba(6, 24, 19, 0.9);
            }

            .tenant-login-panel {
                background: var(--tenant-login-panel-bg);
                border: 1px solid var(--tenant-login-panel-border);
                box-shadow: 0 42px 110px -54px rgba(10, 34, 27, 0.65);
            }

            .tenant-login-form-input {
                border: 1px solid #e2e8f0;
                background: #fff;
                color: #0f172a;
            }

            .tenant-login-form-input:focus {
                outline: none;
                border-color: var(--tenant-login-accent);
                box-shadow: 0 0 0 4px var(--tenant-login-accent-soft);
            }

            .tenant-login-submit {
                display: inline-flex;
                width: 100%;
                align-items: center;
                justify-content: center;
                appearance: none;
                border: 0;
                background: var(--tenant-login-accent);
                color: #fff;
                cursor: pointer;
            }

            .tenant-login-submit:hover {
                background: var(--tenant-login-accent-hover);
            }

            .tenant-login-submit:focus-visible {
                outline: 4px solid var(--tenant-login-accent-soft);
                outline-offset: 2px;
            }

            @media (min-width: 1024px) {
                .tenant-login-main {
                    flex-direction: row;
                    align-items: stretch;
                    padding: 2rem 2.5rem;
                }

                .tenant-login-hero {
                    border-top-right-radius: 0;
                    border-bottom-right-radius: 0;
                }

                .tenant-login-panel {
                    max-width: 36rem;
                    border-top-left-radius: 0;
                    border-bottom-left-radius: 0;
                    border-left: 0;
                }
            }
        </style>
    </head>
    <body class="tenant-login-page min-h-screen bg-[linear-gradient(160deg,#0b1f19_0%,#12382d_36%,#d8e7c6_36%,#eef4e8_100%)] text-slate-900">
        <div class="tenant-login-shell relative isolate overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-56 bg-[radial-gradient(circle_at_top_left,rgba(179,217,98,0.32),transparent_52%),radial-gradient(circle_at_top_right,rgba(255,236,187,0.35),transparent_46%)]"></div>
            <div class="absolute -left-24 top-28 h-72 w-72 rounded-full bg-emerald-950/25 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-lime-200/60 blur-3xl"></div>

            <main class="tenant-login-main relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:flex-row lg:items-stretch lg:px-10">
                <section class="tenant-login-hero flex flex-1 flex-col justify-between rounded-[2rem] border border-white/20 bg-emerald-950/82 p-8 text-white shadow-[0_40px_120px_-56px_rgba(6,24,19,0.9)] backdrop-blur md:p-10 lg:rounded-r-none lg:pr-14">
                    <div class="space-y-8">
                        <div class="inline-flex w-fit items-center gap-3 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium tracking-[0.18em] text-emerald-50 uppercase">
                            <span class="h-2.5 w-2.5 rounded-full bg-lime-300"></span>
                            Accesso unico
                        </div>

                        <div class="max-w-2xl space-y-5">
                            <h1 class="text-4xl font-semibold tracking-[-0.05em] text-balance md:text-6xl">
                                Un unico accesso per clienti e amministratori.
                            </h1>
                            <p class="max-w-xl text-base leading-7 text-emerald-50/78 md:text-lg">
                                Usa le credenziali della tua organizzazione o del tuo profilo amministrativo per entrare in Pest Management V2.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <article class="rounded-[1.5rem] border border-white/12 bg-white/8 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                                <p class="text-sm font-medium uppercase tracking-[0.16em] text-lime-200/90">Documenti</p>
                                <p class="mt-3 text-sm leading-6 text-emerald-50/76">Controlla rapidamente contratti, schede e storico delle attività.</p>
                            </article>
                            <article class="rounded-[1.5rem] border border-white/12 bg-white/8 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                                <p class="text-sm font-medium uppercase tracking-[0.16em] text-lime-200/90">Monitoraggi</p>
                                <p class="mt-3 text-sm leading-6 text-emerald-50/76">Consulta punti di monitoraggio, aree e cataloghi di servizio del tuo tenant.</p>
                            </article>
                            <article class="rounded-[1.5rem] border border-white/12 bg-white/8 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                                <p class="text-sm font-medium uppercase tracking-[0.16em] text-lime-200/90">Accesso riservato</p>
                                <p class="mt-3 text-sm leading-6 text-emerald-50/76">Le credenziali vengono gestite centralmente ma il tuo accesso resta limitato al tenant assegnato.</p>
                            </article>
                        </div>
                    </div>

                    <div class="mt-10 border-t border-white/10 pt-6 text-sm text-emerald-50/72">
                        <p>Login principale di Pest Management V2 per clienti e amministratori autorizzati.</p>
                    </div>
                </section>

                <section class="tenant-login-panel flex w-full max-w-xl items-center rounded-[2rem] border border-emerald-950/10 bg-white/88 p-6 shadow-[0_42px_110px_-54px_rgba(10,34,27,0.65)] backdrop-blur md:p-8 lg:rounded-l-none lg:border-l-0 lg:p-10">
                    <div class="w-full space-y-8">
                        <div class="space-y-3">
                            <p class="text-sm font-medium uppercase tracking-[0.18em] text-emerald-700">Login generale</p>
                            <div class="space-y-2">
                                <h2 class="text-3xl font-semibold tracking-[-0.04em] text-slate-950">Entra in Pest Management V2</h2>
                                <p class="text-sm leading-6 text-slate-600">
                                    Dopo l'accesso vedrai solo le sezioni e i dati permessi al tuo account.
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                            @csrf

                            @if ($errors->any())
                                <div class="rounded-[1.25rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <div class="space-y-2">
                                <label for="email" class="text-sm font-medium text-slate-700">Email</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    class="tenant-login-form-input w-full rounded-[1.1rem] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:shadow-[0_0_0_4px_rgba(22,163,74,0.12)]"
                                    placeholder="nome@azienda.it"
                                >
                            </div>

                            <div class="space-y-2">
                                <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    class="tenant-login-form-input w-full rounded-[1.1rem] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:shadow-[0_0_0_4px_rgba(22,163,74,0.12)]"
                                    placeholder="Inserisci la password"
                                >
                            </div>

                            <label class="flex items-center gap-3 rounded-[1rem] border border-slate-200/80 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    @checked(old('remember'))
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                >
                                Mantieni l'accesso su questo dispositivo
                            </label>

                            <button
                                type="submit"
                                class="tenant-login-submit inline-flex w-full items-center justify-center rounded-[1.15rem] bg-emerald-700 px-4 py-3.5 text-base font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-200"
                            >
                                Accedi al portale
                            </button>
                        </form>

                        <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                            <p class="font-medium">Non trovi più le credenziali?</p>
                            <p class="mt-1 text-amber-800/90">Contatta il tuo referente Pest Management V2 o il superadmin che gestisce la tua organizzazione.</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
