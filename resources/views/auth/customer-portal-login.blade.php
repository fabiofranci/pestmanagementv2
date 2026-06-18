<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Area riservata clienti | {{ config('app.name', 'Pest Management V2') }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            :root {
                --portal-bg: linear-gradient(160deg, #0f172a 0%, #16314f 30%, #d9e6f2 30%, #f7fafc 100%);
                --portal-hero-bg: rgba(15, 23, 42, 0.86);
                --portal-panel-bg: rgba(255, 255, 255, 0.92);
                --portal-panel-border: rgba(15, 23, 42, 0.12);
                --portal-accent: #0f5d8c;
                --portal-accent-hover: #0a476d;
                --portal-accent-soft: rgba(15, 93, 140, 0.18);
            }

            body.customer-portal-page {
                min-height: 100vh;
                margin: 0;
                background: var(--portal-bg);
                color: #0f172a;
            }

            .customer-portal-shell {
                position: relative;
                min-height: 100vh;
                overflow: hidden;
            }

            .customer-portal-shell::before,
            .customer-portal-shell::after {
                content: '';
                position: absolute;
                border-radius: 9999px;
                pointer-events: none;
                filter: blur(36px);
            }

            .customer-portal-shell::before {
                top: 4rem;
                left: -5rem;
                width: 20rem;
                height: 20rem;
                background: radial-gradient(circle, rgba(56, 189, 248, 0.24) 0%, rgba(56, 189, 248, 0) 70%);
            }

            .customer-portal-shell::after {
                right: -6rem;
                bottom: -5rem;
                width: 24rem;
                height: 24rem;
                background: radial-gradient(circle, rgba(191, 219, 254, 0.45) 0%, rgba(191, 219, 254, 0) 72%);
            }

            .customer-portal-main {
                position: relative;
                z-index: 1;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                max-width: 82rem;
                margin: 0 auto;
                padding: 2rem 1.5rem;
            }

            .customer-portal-hero {
                background: var(--portal-hero-bg);
                color: #fff;
                box-shadow: 0 42px 120px -56px rgba(15, 23, 42, 0.85);
            }

            .customer-portal-panel {
                background: var(--portal-panel-bg);
                border: 1px solid var(--portal-panel-border);
                box-shadow: 0 42px 110px -54px rgba(15, 23, 42, 0.4);
            }

            .customer-portal-input {
                border: 1px solid #dbe4ee;
                background: #fff;
                color: #0f172a;
            }

            .customer-portal-input:focus {
                outline: none;
                border-color: var(--portal-accent);
                box-shadow: 0 0 0 4px var(--portal-accent-soft);
            }

            .customer-portal-submit {
                display: inline-flex;
                width: 100%;
                align-items: center;
                justify-content: center;
                appearance: none;
                border: 0;
                background: var(--portal-accent);
                color: #fff;
                cursor: pointer;
            }

            .customer-portal-submit:hover {
                background: var(--portal-accent-hover);
            }

            .customer-portal-submit:focus-visible {
                outline: 4px solid var(--portal-accent-soft);
                outline-offset: 2px;
            }

            @media (min-width: 1024px) {
                .customer-portal-main {
                    flex-direction: row;
                    align-items: stretch;
                    padding: 2rem 2.5rem;
                }

                .customer-portal-hero {
                    border-top-right-radius: 0;
                    border-bottom-right-radius: 0;
                }

                .customer-portal-panel {
                    max-width: 36rem;
                    border-top-left-radius: 0;
                    border-bottom-left-radius: 0;
                    border-left: 0;
                }
            }
        </style>
    </head>
    <body class="customer-portal-page min-h-screen bg-[linear-gradient(160deg,#0f172a_0%,#16314f_30%,#d9e6f2_30%,#f7fafc_100%)] text-slate-900">
        <div class="customer-portal-shell relative isolate overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-56 bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.18),transparent_50%),radial-gradient(circle_at_top_right,rgba(191,219,254,0.3),transparent_46%)]"></div>

            <main class="customer-portal-main relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:flex-row lg:items-stretch lg:px-10">
                <section class="customer-portal-hero flex flex-1 flex-col justify-between rounded-[2rem] border border-white/12 p-8 text-white backdrop-blur md:p-10 lg:rounded-r-none lg:pr-14">
                    <div class="space-y-8">
                        <div class="inline-flex w-fit items-center gap-3 rounded-full border border-white/15 bg-white/8 px-4 py-2 text-sm font-medium tracking-[0.18em] text-sky-100 uppercase">
                            <span class="h-2.5 w-2.5 rounded-full bg-sky-300"></span>
                            Area riservata clienti
                        </div>

                        <div class="max-w-2xl space-y-5">
                            <h1 class="text-4xl font-semibold tracking-[-0.05em] text-balance md:text-6xl">
                                Consulta interventi, rapportini e documentazione del tuo servizio.
                            </h1>
                            <p class="max-w-xl text-base leading-7 text-slate-100/78 md:text-lg">
                                Questo accesso e dedicato ai clienti finali del tenant. Potrai consultare solo dati, sedi e materiali riservati alla tua azienda.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <article class="rounded-[1.5rem] border border-white/12 bg-white/8 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                                <p class="text-sm font-medium uppercase tracking-[0.16em] text-sky-200/90">Interventi</p>
                                <p class="mt-3 text-sm leading-6 text-slate-100/76">Controlla lo storico delle attivita svolte presso le tue sedi e il relativo stato operativo.</p>
                            </article>
                            <article class="rounded-[1.5rem] border border-white/12 bg-white/8 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                                <p class="text-sm font-medium uppercase tracking-[0.16em] text-sky-200/90">Rapportini</p>
                                <p class="mt-3 text-sm leading-6 text-slate-100/76">Accedi ai riepiloghi, ai controlli eseguiti e ai dati di pertinenza del tuo cliente.</p>
                            </article>
                            <article class="rounded-[1.5rem] border border-white/12 bg-white/8 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                                <p class="text-sm font-medium uppercase tracking-[0.16em] text-sky-200/90">Schede e documenti</p>
                                <p class="mt-3 text-sm leading-6 text-slate-100/76">Consulta documentazione tecnica, schede di sicurezza e materiali condivisi dal tuo fornitore.</p>
                            </article>
                        </div>
                    </div>

                    <div class="mt-10 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm text-slate-100/72 md:flex-row md:items-center md:justify-between">
                        <p>Accesso riservato ai clienti autorizzati di Pest Management V2.</p>
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center gap-2 font-medium text-sky-200 transition hover:text-sky-100"
                        >
                            Vai al login gestionale
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </section>

                <section class="customer-portal-panel flex w-full max-w-xl items-center rounded-[2rem] p-6 backdrop-blur md:p-8 lg:rounded-l-none lg:p-10">
                    <div class="w-full space-y-8">
                        <div class="space-y-3">
                            <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-700">Portale clienti</p>
                            <div class="space-y-2">
                                <h2 class="text-3xl font-semibold tracking-[-0.04em] text-slate-950">Entra nell area riservata</h2>
                                <p class="text-sm leading-6 text-slate-600">
                                    Usa le credenziali ricevute dal tuo referente per consultare dati e documenti del tuo servizio.
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('customer.portal.login.store') }}" class="space-y-5">
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
                                    class="customer-portal-input w-full rounded-[1.1rem] px-4 py-3 text-base text-slate-900 outline-none ring-0 transition placeholder:text-slate-400"
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
                                    class="customer-portal-input w-full rounded-[1.1rem] px-4 py-3 text-base text-slate-900 outline-none ring-0 transition placeholder:text-slate-400"
                                    placeholder="Inserisci la password"
                                >
                            </div>

                            <label class="flex items-center gap-3 rounded-[1rem] border border-slate-200/80 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    @checked(old('remember'))
                                    class="h-4 w-4 rounded border-slate-300 text-sky-700 focus:ring-sky-500"
                                >
                                Mantieni l accesso su questo dispositivo
                            </label>

                            <button
                                type="submit"
                                class="customer-portal-submit inline-flex w-full items-center justify-center rounded-[1.15rem] px-4 py-3.5 text-base font-semibold transition focus:outline-none"
                            >
                                Accedi all area riservata
                            </button>
                        </form>

                        <div class="rounded-[1.5rem] border border-sky-100 bg-sky-50 px-4 py-4 text-sm leading-6 text-sky-950">
                            <p class="font-medium">Non hai ancora le credenziali?</p>
                            <p class="mt-1 text-sky-900/88">Contatta il tuo referente o l amministratore del tenant che gestisce il servizio della tua azienda.</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
