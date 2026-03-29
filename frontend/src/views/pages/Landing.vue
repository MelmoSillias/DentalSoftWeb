<script setup lang="ts">
type Offer = {
    name: string;
    price: string;
    target: string;
    details: string[];
};

const coreFeatures = [
    {
        title: 'Dossier patient centralise',
        text: 'Historique medical, consultations, radiographies et ordonnances accessibles en quelques clics.'
    },
    {
        title: 'Agenda intelligent',
        text: 'Planification par praticien, rappels automatiques et suivi des absences en temps reel.'
    },
    {
        title: 'Facturation et devis',
        text: 'Creation de devis, suivi des paiements, impression de recus et tableau de bord financier.'
    },
    {
        title: 'Multi-profils securise',
        text: 'Acces differencie pour medecin, reception, administration et direction avec journal d activite.'
    },
    {
        title: 'Statistiques de pilotage',
        text: 'Suivi des performances du cabinet: frequentation, actes, revenus et progression mensuelle.'
    },
    {
        title: 'Solution cloud et locale',
        text: 'Utilisation possible en reseau local ou via internet pour les cliniques multisites.'
    }
];

const offers: Offer[] = [
    {
        name: 'Pack Demarrage',
        price: '49 000 FCFA / mois',
        target: 'Pour petit cabinet',
        details: ['1 medecin + 1 reception', 'Agenda et dossiers patients', 'Support standard']
    },
    {
        name: 'Pack Pro Cabinet',
        price: '89 000 FCFA / mois',
        target: 'Pour cabinet en croissance',
        details: ['Jusqu a 5 utilisateurs', 'Facturation complete + statistiques', 'Sauvegarde et assistance prioritaire']
    },
    {
        name: 'Pack Clinique Plus',
        price: '149 000 FCFA / mois',
        target: 'Pour structures multiservices',
        details: ['Utilisateurs illimites', 'Parametrage avance et modules sur mesure', 'Accompagnement dedie']
    }
];

const afterSalesServices = [
    'Installation et parametrage initial dans votre structure',
    'Formation de votre equipe (medecin, reception, administration)',
    'Support technique WhatsApp, telephone et visio',
    'Maintenance preventive et corrective reguliere',
    'Mises a jour fonctionnelles et securitaires incluses',
    'Assistance reseau et conseils d infrastructure'
];

const team = [
    { name: 'Bamogo Mohamed', role: 'Developpeur Web' },
    { name: 'Moustapha Sidibe', role: 'Technicien Reseau' },
    { name: 'Seydou Berthe', role: 'Technicien Reseau' },
    { name: 'Soumaila Sangare', role: 'Developpeur Web' }
];
</script>

<template>
    <div class="landing-page min-h-screen">
        <div class="noise-overlay"></div>

        <header class="mx-auto max-w-7xl px-6 pb-10 pt-8 lg:px-10">
            <nav class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/20 bg-white/70 px-5 py-4 backdrop-blur-md">
                <div>
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">SIMDEVS</p>
                    <h1 class="text-xl font-bold text-slate-900">DentalSoft</h1>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm font-medium text-slate-700">
                    <a href="#fonctionnalites" class="nav-link">Fonctionnalites</a>
                    <a href="#tarifs" class="nav-link">Tarifs</a>
                    <a href="#sav" class="nav-link">SAV</a>
                    <a href="#equipe" class="nav-link">Equipe</a>
                </div>

                <a href="#contact" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-700">Demander une demo</a>
            </nav>
        </header>

        <main class="mx-auto max-w-7xl space-y-20 px-6 pb-20 lg:px-10">
            <section class="grid items-center gap-10 lg:grid-cols-2">
                <div class="stagger-item">
                    <p class="mb-3 inline-flex rounded-full border border-amber-400/60 bg-amber-100/70 px-4 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-amber-900">
                        Solution web pour cabinets dentaires
                    </p>
                    <h2 class="max-w-xl text-4xl font-extrabold leading-tight text-slate-900 md:text-5xl">
                        Digitalisez la gestion de votre cabinet avec DentalSoft
                    </h2>
                    <p class="mt-5 max-w-xl text-base leading-7 text-slate-700 md:text-lg">
                        DentalSoft est une application web concue pour simplifier le travail quotidien des structures dentaires: accueil patient,
                        consultation, facturation, suivi d activites et pilotage global en un seul espace.
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="#tarifs" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-emerald-500">
                            Voir les offres en FCFA
                        </a>
                        <a href="#fonctionnalites" class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-bold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-500">
                            Explorer les fonctionnalites
                        </a>
                    </div>
                </div>

                <div class="stagger-item hero-panel rounded-3xl p-8 md:p-10">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-teal-800">Impact concret</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <article class="stat-box">
                            <p class="stat-value">-40%</p>
                            <p class="stat-label">de temps administratif</p>
                        </article>
                        <article class="stat-box">
                            <p class="stat-value">+30%</p>
                            <p class="stat-label">de punctualite aux rendez-vous</p>
                        </article>
                        <article class="stat-box">
                            <p class="stat-value">100%</p>
                            <p class="stat-label">des dossiers centralises</p>
                        </article>
                        <article class="stat-box">
                            <p class="stat-value">24/7</p>
                            <p class="stat-label">acces cloud securise</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="fonctionnalites" class="space-y-6">
                <div class="stagger-item">
                    <p class="section-tag">Fonctionnalites incluses</p>
                    <h3 class="section-title">Tous les outils pour gerer votre structure dentaire</h3>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="feature in coreFeatures" :key="feature.title" class="stagger-item feature-card">
                        <h4 class="text-lg font-bold text-slate-900">{{ feature.title }}</h4>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ feature.text }}</p>
                    </article>
                </div>
            </section>

            <section id="tarifs" class="space-y-6">
                <div class="stagger-item">
                    <p class="section-tag">Tarification simple</p>
                    <h3 class="section-title">Choisissez un plan adapte a votre taille</h3>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    <article v-for="offer in offers" :key="offer.name" class="stagger-item pricing-card">
                        <p class="text-xs uppercase tracking-[0.2em] text-emerald-700">{{ offer.target }}</p>
                        <h4 class="mt-3 text-2xl font-black text-slate-900">{{ offer.name }}</h4>
                        <p class="mt-3 text-xl font-extrabold text-emerald-700">{{ offer.price }}</p>
                        <ul class="mt-4 space-y-2 text-sm leading-6 text-slate-700">
                            <li v-for="detail in offer.details" :key="detail" class="flex items-start gap-2">
                                <span class="mt-1 h-2 w-2 rounded-full bg-emerald-600"></span>
                                <span>{{ detail }}</span>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section id="sav" class="grid gap-8 lg:grid-cols-2">
                <div class="stagger-item">
                    <p class="section-tag">Services apres vente</p>
                    <h3 class="section-title">Un accompagnement humain et technique</h3>
                    <p class="mt-4 max-w-xl text-sm leading-7 text-slate-700 md:text-base">
                        Notre objectif n est pas seulement de livrer un logiciel. Nous restons a vos cotes pour garantir son adoption, sa performance
                        et sa fiabilite sur le long terme.
                    </p>
                </div>

                <div class="stagger-item rounded-3xl border border-slate-200/80 bg-white/80 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.12)]">
                    <ul class="space-y-3 text-sm text-slate-700 md:text-base">
                        <li v-for="service in afterSalesServices" :key="service" class="flex gap-3 rounded-xl bg-slate-50 px-4 py-3">
                            <span class="mt-0.5 text-emerald-700">✓</span>
                            <span>{{ service }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section id="equipe" class="space-y-6">
                <div class="stagger-item">
                    <p class="section-tag">Qui sommes-nous ?</p>
                    <h3 class="section-title">SIMDEVS, groupe de jeunes maliens engage pour la digitalisation</h3>
                    <p class="mt-4 max-w-4xl text-sm leading-7 text-slate-700 md:text-base">
                        Nous sommes un groupe de jeunes maliens proposant des solutions informatiques dans divers secteurs afin de digitaliser et
                        dynamiser notre pays. Avec DentalSoft, nous mettons notre expertise web et reseau au service des professionnels de sante.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article v-for="member in team" :key="member.name" class="stagger-item member-card">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">SIMDEVS</p>
                        <h4 class="mt-2 text-lg font-bold text-slate-900">{{ member.name }}</h4>
                        <p class="mt-1 text-sm font-medium text-emerald-700">{{ member.role }}</p>
                    </article>
                </div>
            </section>
        </main>

        <footer id="contact" class="mx-auto max-w-7xl px-6 pb-14 lg:px-10">
            <div class="stagger-item rounded-3xl border border-white/30 bg-slate-900 p-8 text-white shadow-[0_25px_60px_rgba(2,6,23,0.45)] md:p-10">
                <p class="text-xs uppercase tracking-[0.2em] text-emerald-300">Contact</p>
                <h3 class="mt-3 text-2xl font-black md:text-3xl">Pret a lancer DentalSoft dans votre structure ?</h3>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-200 md:text-base">
                    Ecrivez-nous pour une demonstration personnalisee et une proposition adaptee a votre cabinet ou clinique.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="mailto:contact@simdevs.ml" class="rounded-xl bg-emerald-500 px-5 py-3 text-sm font-bold text-slate-900 transition hover:-translate-y-0.5 hover:bg-emerald-400">
                        contact@simdevs.ml
                    </a>
                    <a href="tel:+22300000000" class="rounded-xl border border-slate-400 px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:border-slate-200">
                        +223 00 00 00 00
                    </a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.landing-page {
    --bg-soft: #fffdf5;
    --bg-main: #f8fdfb;
    --ink: #0f172a;
    --muted: #475569;
    --accent: #0f9d85;
    --accent-soft: #d6f5ee;
    min-height: 100vh;
    background:
        radial-gradient(circle at 8% 10%, rgba(255, 193, 7, 0.22), transparent 25%),
        radial-gradient(circle at 92% 2%, rgba(15, 157, 133, 0.16), transparent 26%),
        linear-gradient(160deg, var(--bg-soft) 0%, var(--bg-main) 100%);
    color: var(--ink);
    font-family: 'Poppins', 'Nunito Sans', 'Segoe UI', sans-serif;
    position: relative;
    overflow-x: hidden;
}

.noise-overlay {
    pointer-events: none;
    position: absolute;
    inset: 0;
    opacity: 0.2;
    background-image: radial-gradient(rgba(15, 23, 42, 0.12) 0.35px, transparent 0.35px);
    background-size: 9px 9px;
    mix-blend-mode: multiply;
}

.nav-link {
    border-bottom: 2px solid transparent;
    padding-bottom: 0.2rem;
    transition: all 0.2s ease;
}

.nav-link:hover {
    color: #0f766e;
    border-color: #0f766e;
}

.hero-panel {
    background: linear-gradient(145deg, rgba(216, 252, 244, 0.85), rgba(255, 244, 225, 0.8));
    border: 1px solid rgba(15, 157, 133, 0.3);
    box-shadow: 0 30px 65px rgba(15, 23, 42, 0.16);
}

.stat-box {
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 1rem;
    padding: 0.95rem;
    background: rgba(255, 255, 255, 0.72);
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 900;
    color: #0f766e;
    line-height: 1.05;
}

.stat-label {
    margin-top: 0.25rem;
    font-size: 0.85rem;
    color: var(--muted);
}

.section-tag {
    display: inline-flex;
    border: 1px solid rgba(15, 157, 133, 0.35);
    background: rgba(214, 245, 238, 0.8);
    color: #0f766e;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    border-radius: 999px;
    padding: 0.45rem 0.9rem;
}

.section-title {
    margin-top: 0.85rem;
    max-width: 56rem;
    font-size: clamp(1.65rem, 2.2vw, 2.2rem);
    line-height: 1.2;
    font-weight: 900;
    color: var(--ink);
}

.feature-card,
.pricing-card,
.member-card {
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 1.15rem;
    background: rgba(255, 255, 255, 0.88);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    padding: 1.2rem;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.feature-card:hover,
.pricing-card:hover,
.member-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 26px 52px rgba(15, 23, 42, 0.14);
}

.stagger-item {
    opacity: 0;
    transform: translateY(16px);
    animation: revealUp 0.65s ease forwards;
}

.stagger-item:nth-child(2) {
    animation-delay: 0.08s;
}

.stagger-item:nth-child(3) {
    animation-delay: 0.16s;
}

.stagger-item:nth-child(4) {
    animation-delay: 0.24s;
}

@keyframes revealUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 640px) {
    .landing-page {
        background:
            radial-gradient(circle at 15% 12%, rgba(255, 193, 7, 0.18), transparent 24%),
            radial-gradient(circle at 90% 5%, rgba(15, 157, 133, 0.13), transparent 28%),
            linear-gradient(180deg, var(--bg-soft) 0%, var(--bg-main) 100%);
    }
}
</style>
