import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Brand } from '@/components/ui/Brand';
import { Icon, type IconName } from '@/components/ui/Icon';

const primaryCta =
    'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-atlas-accent px-5 py-3 text-sm font-semibold text-white hover:bg-[#066557]';
const secondaryCta =
    'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-atlas-border bg-white/80 px-5 py-3 text-sm font-semibold text-atlas-ink shadow-sm hover:border-atlas-accent/30 hover:bg-white';

const productLoop: Array<{
    number: string;
    title: string;
    description: string;
    icon: IconName;
}> = [
    {
        number: '01',
        title: 'Gérer',
        description: 'Clients, opportunités, devis, factures et paiements restent reliés dans un même fil.',
        icon: 'crm',
    },
    {
        number: '02',
        title: 'Comprendre',
        description: 'Atlas transforme vos données réelles en tendances lisibles, avec leur source et leur fraîcheur.',
        icon: 'activity',
    },
    {
        number: '03',
        title: 'Décider',
        description: 'Les risques et opportunités deviennent des priorités expliquées, classées par impact.',
        icon: 'advisor',
    },
    {
        number: '04',
        title: 'Agir',
        description: 'Vous ouvrez directement le bon client, la bonne facture ou la prochaine action utile.',
        icon: 'arrow-right',
    },
];

const capabilities: Array<{
    icon: IconName;
    title: string;
    description: string;
    detail: string;
}> = [
    {
        icon: 'crm',
        title: 'Un CRM qui reste praticable',
        description: 'Clients, contacts, opportunités et historique commercial sans configuration d’usine.',
        detail: 'Du premier échange au devis accepté',
    },
    {
        icon: 'billing',
        title: 'Une facturation vraiment suivie',
        description: 'Devis, PDF, factures, acomptes, avoirs, paiements partiels et relances par email.',
        detail: 'Chaque solde garde une trace vérifiable',
    },
    {
        icon: 'dashboard',
        title: 'Une vue d’ensemble utile',
        description: 'Pipeline, activité mesurée et encaissements réunis sans masquer les données manquantes.',
        detail: 'Les chiffres viennent de votre activité',
    },
    {
        icon: 'activity',
        title: 'Une santé d’activité explicable',
        description: 'Quatre facteurs lisibles pour repérer ce qui progresse, ce qui manque et ce qui fragilise.',
        detail: 'Couverture et fiabilité toujours visibles',
    },
    {
        icon: 'advisor',
        title: 'Des priorités, pas du bruit',
        description: 'Atlas met en avant les actions qui méritent votre attention et explique pourquoi maintenant.',
        detail: 'Recommandations bornées et traçables',
    },
    {
        icon: 'bell',
        title: 'Des rappels qui aboutissent',
        description: 'Notifications dans l’application et emails transactionnels pour les moments importants.',
        detail: 'Préférences et historique de livraison',
    },
];

const questions = [
    {
        question: 'À qui s’adresse Atlas ?',
        answer: 'Atlas est conçu d’abord pour les indépendants et petites structures de services : consultants, designers, développeurs, formateurs, agences et métiers du conseil qui travaillent avec un cycle devis–facture–paiement.',
    },
    {
        question: 'Atlas remplace-t-il mon comptable ?',
        answer: 'Non. Atlas aide à piloter l’activité commerciale et la facturation métier. Il ne promet ni tenue comptable complète, ni compte bancaire professionnel, ni rôle de Plateforme Agréée.',
    },
    {
        question: 'Dois-je renseigner une carte bancaire ?',
        answer: 'Non. L’accès anticipé permet d’explorer le produit complet pendant 30 jours sans carte. Aucun prélèvement automatique n’est déclenché sans consentement explicite.',
    },
    {
        question: 'Que se passe-t-il si mes données sont insuffisantes ?',
        answer: 'Atlas l’indique clairement. Aucun score, total ou conseil n’est inventé pour remplir un écran. La couverture, la fraîcheur et les éléments manquants restent visibles.',
    },
    {
        question: 'Puis-je récupérer mes données ?',
        answer: 'Oui. Pendant l’accès anticipé, le support organise une récupération assistée de votre espace et sa fermeture sur demande. L’export en libre-service n’est pas encore disponible, et Atlas ne vous le présente pas comme une fonctionnalité déjà livrée.',
    },
    {
        question: 'Le tarif est-il déjà définitif ?',
        answer: 'Non. L’offre Atlas Solo et son tarif sont encore en validation pendant l’Early Access. Les conditions finales seront présentées clairement avant tout engagement payant.',
    },
];

export function LandingPage() {
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Atlas — Pilotez votre activité avec confiance';

        return () => {
            document.title = previousTitle;
        };
    }, []);

    function closeMenu() {
        setMenuOpen(false);
    }

    return (
        <div className="min-h-screen overflow-hidden bg-[#f7f9f6] text-atlas-ink">
            <a
                href="#contenu"
                className="sr-only z-[100] rounded-lg bg-white px-4 py-3 font-semibold text-atlas-accent focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >
                Aller au contenu
            </a>

            <header className="fixed inset-x-0 top-0 z-50 border-b border-atlas-border/75 bg-[#f7f9f6]/88 backdrop-blur-xl">
                <div className="mx-auto flex h-[4.75rem] max-w-[82rem] items-center justify-between px-5 sm:px-8 lg:px-10">
                    <Link to="/" aria-label="Atlas, accueil" onClick={closeMenu}>
                        <Brand />
                    </Link>

                    <nav aria-label="Navigation principale du site" className="hidden items-center gap-8 lg:flex">
                        <a href="#produit" className="text-sm font-medium text-atlas-ink-muted hover:text-atlas-ink">
                            Produit
                        </a>
                        <a href="#fonctionnalites" className="text-sm font-medium text-atlas-ink-muted hover:text-atlas-ink">
                            Fonctionnalités
                        </a>
                        <a href="#acces-anticipe" className="text-sm font-medium text-atlas-ink-muted hover:text-atlas-ink">
                            Accès anticipé
                        </a>
                        <a href="#faq" className="text-sm font-medium text-atlas-ink-muted hover:text-atlas-ink">
                            FAQ
                        </a>
                    </nav>

                    <div className="hidden items-center gap-3 lg:flex">
                        <Link to="/app/login" className="px-3 py-2 text-sm font-semibold text-atlas-ink-muted hover:text-atlas-ink">
                            Se connecter
                        </Link>
                        <Link to="/app/register" className={`${primaryCta} min-h-10 px-4 py-2`}>
                            Essayer Atlas
                            <Icon name="arrow-right" className="size-4" />
                        </Link>
                    </div>

                    <button
                        type="button"
                        aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
                        aria-expanded={menuOpen}
                        aria-controls="landing-mobile-menu"
                        onClick={() => setMenuOpen((current) => !current)}
                        className="grid size-11 place-items-center rounded-xl border border-atlas-border bg-white text-atlas-ink shadow-sm lg:hidden"
                    >
                        <Icon name={menuOpen ? 'close' : 'menu'} className="size-5" />
                    </button>
                </div>

                {menuOpen && (
                    <div id="landing-mobile-menu" className="border-t border-atlas-border bg-white px-5 py-5 shadow-lg lg:hidden">
                        <nav aria-label="Navigation mobile" className="mx-auto grid max-w-[82rem] gap-1">
                            {[
                                ['Produit', '#produit'],
                                ['Fonctionnalités', '#fonctionnalites'],
                                ['Accès anticipé', '#acces-anticipe'],
                                ['FAQ', '#faq'],
                            ].map(([label, href]) => (
                                <a
                                    key={href}
                                    href={href}
                                    onClick={closeMenu}
                                    className="rounded-xl px-3 py-3 text-sm font-semibold text-atlas-ink hover:bg-atlas-surface"
                                >
                                    {label}
                                </a>
                            ))}
                            <div className="mt-4 grid grid-cols-2 gap-3 border-t border-atlas-border pt-5">
                                <Link to="/app/login" onClick={closeMenu} className={`${secondaryCta} px-3`}>
                                    Connexion
                                </Link>
                                <Link to="/app/register" onClick={closeMenu} className={`${primaryCta} px-3`}>
                                    Essayer Atlas
                                </Link>
                            </div>
                        </nav>
                    </div>
                )}
            </header>

            <main id="contenu">
                <section className="landing-hero-grid relative overflow-hidden px-5 pb-20 pt-36 sm:px-8 sm:pb-28 sm:pt-40 lg:px-10 lg:pb-32 lg:pt-48">
                    <div className="pointer-events-none absolute left-[-12rem] top-24 size-[32rem] rounded-full bg-atlas-accent/8 blur-3xl" />
                    <div className="pointer-events-none absolute right-[-16rem] top-[-8rem] size-[42rem] rounded-full bg-[#b6ddcf]/35 blur-3xl" />
                    <div className="relative mx-auto grid max-w-[82rem] items-center gap-14 lg:grid-cols-[.88fr_1.12fr] lg:gap-10 xl:gap-16">
                        <div className="relative z-10 max-w-2xl">
                            <div className="inline-flex items-center gap-2 rounded-full border border-atlas-accent/20 bg-white/75 px-3.5 py-2 text-xs font-semibold text-atlas-accent shadow-sm backdrop-blur">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-atlas-accent opacity-35" />
                                    <span className="relative inline-flex size-2 rounded-full bg-atlas-accent" />
                                </span>
                                Early Access ouvert
                            </div>
                            <h1 className="mt-7 text-[clamp(3rem,7vw,5.7rem)] font-semibold leading-[.96] tracking-[-0.065em] text-atlas-sidebar">
                                Votre activité.
                                <span className="mt-1 block text-atlas-accent">Enfin lisible.</span>
                            </h1>
                            <p className="mt-7 max-w-xl text-lg leading-8 text-atlas-ink-muted sm:text-xl sm:leading-9">
                                Atlas relie vos clients, vos devis, vos factures et vos données réelles pour vous montrer ce qui mérite votre attention — et pourquoi.
                            </p>
                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                <Link to="/app/register" className={`${primaryCta} sm:min-w-52`}>
                                    Démarrer gratuitement
                                    <Icon name="arrow-right" className="size-4" />
                                </Link>
                                <a href="#produit" className={`${secondaryCta} sm:min-w-44`}>
                                    Découvrir Atlas
                                </a>
                            </div>
                            <div className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-atlas-ink-muted">
                                {['30 jours complets', 'Sans carte bancaire', 'Aucun prélèvement automatique'].map((item) => (
                                    <span key={item} className="inline-flex items-center gap-2">
                                        <Icon name="check" className="size-3.5 text-atlas-accent" />
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <ProductPreview />
                    </div>
                </section>

                <section aria-label="Engagements Early Access" className="border-y border-atlas-border bg-white/70 px-5 sm:px-8 lg:px-10">
                    <div className="mx-auto grid max-w-[82rem] divide-y divide-atlas-border sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                        {[
                            ['30 jours', 'Produit complet'],
                            ['0 €', 'Pendant l’accès anticipé'],
                            ['Sans carte', 'Vous gardez le contrôle'],
                            ['Données réelles', 'Aucun indicateur inventé'],
                        ].map(([value, label]) => (
                            <div key={label} className="px-5 py-6 sm:px-7 lg:py-8">
                                <p className="text-xl font-semibold tracking-[-0.035em] text-atlas-sidebar">{value}</p>
                                <p className="mt-1 text-xs text-atlas-ink-muted">{label}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="produit" className="scroll-mt-24 px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                    <div className="mx-auto max-w-[82rem]">
                        <SectionIntro
                            kicker="Le constat"
                            title="Vous n’avez pas besoin d’un tableau de bord de plus."
                            description="Vous avez besoin de relier ce qui se passe aujourd’hui à la prochaine décision utile. Atlas transforme les tâches de gestion en une boucle de pilotage claire."
                            centered
                        />

                        <div className="relative mt-16 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div className="pointer-events-none absolute left-[12%] right-[12%] top-[3.1rem] hidden h-px bg-gradient-to-r from-transparent via-atlas-accent/25 to-transparent lg:block" />
                            {productLoop.map((step) => (
                                <article key={step.title} className="atlas-surface relative p-6 lg:p-7">
                                    <div className="relative z-10 flex items-center justify-between">
                                        <span className="grid size-12 place-items-center rounded-2xl bg-atlas-accent-soft text-atlas-accent">
                                            <Icon name={step.icon} className="size-5" />
                                        </span>
                                        <span className="text-[11px] font-semibold tracking-[.16em] text-atlas-ink-muted/60">{step.number}</span>
                                    </div>
                                    <h3 className="mt-8 text-xl font-semibold tracking-[-0.03em]">{step.title}</h3>
                                    <p className="mt-3 text-sm leading-6 text-atlas-ink-muted">{step.description}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="bg-atlas-sidebar px-5 py-24 text-white sm:px-8 sm:py-32 lg:px-10">
                    <div className="mx-auto grid max-w-[82rem] items-center gap-16 lg:grid-cols-[.82fr_1.18fr]">
                        <div className="max-w-xl">
                            <p className="atlas-kicker !text-[#61c9ae]">Une priorité expliquée</p>
                            <h2 className="mt-4 text-[clamp(2.4rem,5vw,4.2rem)] font-semibold leading-[1.02] tracking-[-0.055em]">
                                Savoir quoi faire. Et savoir pourquoi.
                            </h2>
                            <p className="mt-6 text-base leading-8 text-white/58 sm:text-lg">
                                Atlas ne remplace pas votre jugement. Il rassemble les preuves, rend l’incertitude visible et vous conduit vers le bon dossier pour agir.
                            </p>
                            <div className="mt-9 space-y-4">
                                {[
                                    'Chaque recommandation cite les données qui la déclenchent',
                                    'Les informations trop anciennes ou incomplètes sont signalées',
                                    'Vous gardez la main sur les emails, les relances et les décisions',
                                ].map((item) => (
                                    <div key={item} className="flex items-start gap-3 text-sm leading-6 text-white/72">
                                        <span className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-white/8 text-[#61c9ae]">
                                            <Icon name="check" className="size-3.5" />
                                        </span>
                                        {item}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <AdvisorPreview />
                    </div>
                </section>

                <section id="fonctionnalites" className="scroll-mt-24 px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                    <div className="mx-auto max-w-[82rem]">
                        <SectionIntro
                            kicker="Tout est relié"
                            title="Gérez le quotidien sans perdre la vue d’ensemble."
                            description="Atlas couvre le cycle essentiel des activités de services et conserve le lien entre les actions, les montants et les décisions."
                        />

                        <div className="mt-14 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {capabilities.map((capability) => (
                                <article key={capability.title} className="group rounded-[1.35rem] border border-atlas-border bg-white p-6 shadow-sm sm:p-7">
                                    <span className="grid size-11 place-items-center rounded-[.9rem] bg-atlas-surface text-atlas-accent transition-colors group-hover:bg-atlas-accent-soft">
                                        <Icon name={capability.icon} className="size-5" />
                                    </span>
                                    <h3 className="mt-6 text-lg font-semibold tracking-[-0.025em]">{capability.title}</h3>
                                    <p className="mt-3 text-sm leading-6 text-atlas-ink-muted">{capability.description}</p>
                                    <div className="mt-6 border-t border-atlas-border pt-4 text-xs font-medium text-atlas-accent">
                                        {capability.detail}
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="px-5 pb-24 sm:px-8 sm:pb-32 lg:px-10">
                    <div className="mx-auto grid max-w-[82rem] overflow-hidden rounded-[2rem] border border-[#d9e5df] bg-[#edf5f1] lg:grid-cols-2">
                        <div className="p-7 sm:p-10 lg:p-14">
                            <p className="atlas-kicker">Démarrage guidé</p>
                            <h2 className="mt-4 text-3xl font-semibold leading-tight tracking-[-0.045em] sm:text-4xl">
                                Ne repartez pas de zéro.
                            </h2>
                            <p className="mt-5 max-w-lg text-base leading-7 text-atlas-ink-muted">
                                Importez votre historique clients et facturation, puis laissez Atlas reconstruire une première lecture de votre activité à partir de faits existants.
                            </p>
                            <div className="mt-8 grid gap-3 sm:grid-cols-2">
                                {['Clients et contacts', 'Devis et factures', 'Paiements et avoirs', 'Analyse reconstruite'].map((item) => (
                                    <div key={item} className="flex items-center gap-2.5 text-sm font-medium">
                                        <Icon name="check" className="size-4 text-atlas-accent" />
                                        {item}
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="relative min-h-80 border-t border-[#d9e5df] bg-white/55 p-7 sm:p-10 lg:border-l lg:border-t-0 lg:p-14">
                            <div className="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgb(8_115_99/10%),transparent_45%)]" />
                            <div className="relative mx-auto max-w-md rounded-2xl border border-atlas-border bg-white p-5 shadow-[0_20px_55px_rgb(16_28_26/0.1)]">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-semibold">Importer mon historique</p>
                                        <p className="mt-1 text-xs text-atlas-ink-muted">Clients et facturation</p>
                                    </div>
                                    <span className="grid size-10 place-items-center rounded-xl bg-atlas-accent-soft text-atlas-accent">
                                        <Icon name="upload" className="size-5" />
                                    </span>
                                </div>
                                <div className="mt-6 space-y-3">
                                    {[
                                        ['Historique clients', 'Terminé', '100%'],
                                        ['Historique facturation', 'Terminé', '100%'],
                                        ['Analyse de l’activité', 'Prête', '100%'],
                                    ].map(([label, status, progress]) => (
                                        <div key={label} className="rounded-xl border border-atlas-border bg-atlas-surface/60 p-3.5">
                                            <div className="flex items-center justify-between gap-3 text-xs">
                                                <span className="font-medium">{label}</span>
                                                <span className="text-atlas-accent">{status}</span>
                                            </div>
                                            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-white">
                                                <div className="h-full rounded-full bg-atlas-accent" style={{ width: progress }} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="acces-anticipe" className="scroll-mt-24 bg-white px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                    <div className="mx-auto grid max-w-[76rem] items-center gap-12 lg:grid-cols-[.82fr_1.18fr]">
                        <div>
                            <p className="atlas-kicker">Atlas Solo · Early Access</p>
                            <h2 className="mt-4 text-[clamp(2.4rem,5vw,4rem)] font-semibold leading-[1.03] tracking-[-0.055em]">
                                Une seule offre. Toute la boucle de pilotage.
                            </h2>
                            <p className="mt-6 max-w-xl text-base leading-8 text-atlas-ink-muted sm:text-lg">
                                Nous validons Atlas avec les premiers utilisateurs avant toute commercialisation. Vous explorez le produit complet et vos retours façonnent la version initiale.
                            </p>
                        </div>

                        <div className="relative rounded-[2rem] border border-atlas-accent/25 bg-atlas-sidebar p-6 text-white shadow-[0_28px_80px_rgb(16_28_26/0.18)] sm:p-9">
                            <div className="absolute right-6 top-6 rounded-full bg-[#61c9ae]/15 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[.15em] text-[#7bd8c0]">
                                Accès anticipé
                            </div>
                            <p className="text-sm font-semibold text-white/55">Votre période d’exploration</p>
                            <div className="mt-3 flex items-end gap-3">
                                <span className="text-5xl font-semibold tracking-[-0.055em]">30 jours</span>
                                <span className="pb-1 text-sm text-white/50">sans carte</span>
                            </div>
                            <div className="mt-8 grid gap-3 border-y border-white/10 py-7 sm:grid-cols-2">
                                {[
                                    'CRM et cycle commercial',
                                    'Devis, factures et relances',
                                    'Imports historiques guidés',
                                    'Santé de l’activité',
                                    'Advisor et priorités',
                                    '1 Owner + 2 Members',
                                ].map((item) => (
                                    <div key={item} className="flex items-center gap-2.5 text-sm text-white/72">
                                        <Icon name="check" className="size-4 shrink-0 text-[#61c9ae]" />
                                        {item}
                                    </div>
                                ))}
                            </div>
                            <p className="mt-6 text-xs leading-5 text-white/45">
                                Le tarif Atlas Solo est encore en validation. Il vous sera communiqué clairement avant tout engagement. Aucun prélèvement automatique n’est programmé.
                            </p>
                            <Link to="/app/register" className="mt-7 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-atlas-sidebar hover:bg-[#edf7f3]">
                                Rejoindre l’Early Access
                                <Icon name="arrow-right" className="size-4" />
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                    <div className="mx-auto max-w-[82rem]">
                        <SectionIntro
                            kicker="Conçu pour les services"
                            title="Pour celles et ceux dont l’activité tient dans la relation client."
                            description="Atlas privilégie les structures avec peu de stock, un cycle commercial direct et un besoin quotidien de visibilité."
                            centered
                        />
                        <div className="mx-auto mt-12 flex max-w-4xl flex-wrap justify-center gap-3">
                            {['Consultants', 'Designers', 'Développeurs', 'Formateurs', 'Agences', 'Prestataires numériques', 'Métiers du conseil'].map((persona) => (
                                <span key={persona} className="rounded-full border border-atlas-border bg-white px-4 py-2.5 text-sm font-medium shadow-sm">
                                    {persona}
                                </span>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="faq" className="scroll-mt-24 border-t border-atlas-border bg-white px-5 py-24 sm:px-8 sm:py-32 lg:px-10">
                    <div className="mx-auto grid max-w-[76rem] gap-12 lg:grid-cols-[.65fr_1.35fr]">
                        <div>
                            <p className="atlas-kicker">Questions fréquentes</p>
                            <h2 className="mt-4 text-3xl font-semibold tracking-[-0.045em] sm:text-4xl">Avant de commencer.</h2>
                            <p className="mt-5 text-sm leading-7 text-atlas-ink-muted">
                                Atlas est encore en accès anticipé. Nous préférons une réponse précise à une promesse prématurée.
                            </p>
                        </div>
                        <div className="divide-y divide-atlas-border border-y border-atlas-border">
                            {questions.map(({ question, answer }) => (
                                <details key={question} className="group py-1">
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-5 py-5 text-left font-semibold marker:hidden">
                                        {question}
                                        <span className="grid size-8 shrink-0 place-items-center rounded-full bg-atlas-surface text-atlas-accent transition-transform group-open:rotate-45">
                                            <Icon name="plus" className="size-4" />
                                        </span>
                                    </summary>
                                    <p className="max-w-2xl pb-6 pr-10 text-sm leading-7 text-atlas-ink-muted">{answer}</p>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="px-5 py-16 sm:px-8 sm:py-24 lg:px-10">
                    <div className="relative mx-auto max-w-[82rem] overflow-hidden rounded-[2rem] bg-atlas-accent px-6 py-14 text-center text-white sm:px-12 sm:py-20">
                        <div className="pointer-events-none absolute -left-24 -top-28 size-80 rounded-full border border-white/10" />
                        <div className="pointer-events-none absolute -right-28 -top-10 size-96 rounded-full border border-white/10" />
                        <div className="pointer-events-none absolute bottom-[-10rem] left-1/2 size-80 -translate-x-1/2 rounded-full bg-white/10 blur-3xl" />
                        <div className="relative mx-auto max-w-3xl">
                            <p className="text-xs font-semibold uppercase tracking-[.18em] text-white/65">Early Access</p>
                            <h2 className="mt-4 text-[clamp(2.4rem,6vw,4.5rem)] font-semibold leading-[1] tracking-[-0.06em]">
                                Votre prochaine décision commence ici.
                            </h2>
                            <p className="mx-auto mt-6 max-w-xl text-base leading-7 text-white/72">
                                Créez votre espace, importez ce qui existe déjà et découvrez une lecture plus claire de votre activité.
                            </p>
                            <Link to="/app/register" className="mt-8 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-atlas-accent shadow-lg hover:bg-[#edf7f3]">
                                Démarrer gratuitement
                                <Icon name="arrow-right" className="size-4" />
                            </Link>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="border-t border-atlas-border bg-white px-5 py-10 sm:px-8 lg:px-10">
                <div className="mx-auto flex max-w-[82rem] flex-col gap-8 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Brand />
                        <p className="mt-4 max-w-sm text-xs leading-5 text-atlas-ink-muted">
                            Le système de pilotage quotidien des indépendants et petites entreprises de services.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-3 text-xs font-medium text-atlas-ink-muted">
                        <a href="#produit" className="hover:text-atlas-ink">Produit</a>
                        <a href="#acces-anticipe" className="hover:text-atlas-ink">Early Access</a>
                        <a href="#faq" className="hover:text-atlas-ink">FAQ</a>
                        <Link to="/app/login" className="hover:text-atlas-ink">Connexion</Link>
                    </div>
                </div>
                <div className="mx-auto mt-8 flex max-w-[82rem] flex-col gap-2 border-t border-atlas-border pt-6 text-[11px] text-atlas-ink-muted/75 sm:flex-row sm:items-center sm:justify-between">
                    <p>© 2026 Atlas. Produit en accès anticipé.</p>
                    <p>Données récupérables avec assistance · Décisions explicables · Utilisateur aux commandes</p>
                </div>
            </footer>
        </div>
    );
}

function SectionIntro({
    kicker,
    title,
    description,
    centered = false,
}: {
    kicker: string;
    title: string;
    description: string;
    centered?: boolean;
}) {
    return (
        <div className={`${centered ? 'mx-auto max-w-3xl text-center' : 'max-w-3xl'}`}>
            <p className="atlas-kicker">{kicker}</p>
            <h2 className="mt-4 text-[clamp(2.35rem,5vw,4.15rem)] font-semibold leading-[1.03] tracking-[-0.055em] text-atlas-sidebar">
                {title}
            </h2>
            <p className={`mt-6 text-base leading-8 text-atlas-ink-muted sm:text-lg ${centered ? 'mx-auto max-w-2xl' : 'max-w-2xl'}`}>
                {description}
            </p>
        </div>
    );
}

function ProductPreview() {
    return (
        <div aria-label="Aperçu du tableau de bord Atlas avec des données d’exemple" className="relative mx-auto w-full max-w-[44rem] lg:mx-0 lg:ml-auto">
            <div className="landing-float absolute -left-3 top-20 z-20 hidden rounded-xl border border-atlas-border bg-white px-3.5 py-3 shadow-[0_14px_35px_rgb(16_28_26/0.14)] sm:flex sm:items-center sm:gap-3 lg:-left-8 xl:-left-12">
                <span className="grid size-8 place-items-center rounded-lg bg-atlas-accent-soft text-atlas-accent">
                    <Icon name="check" className="size-4" />
                </span>
                <span>
                    <span className="block text-[10px] font-medium uppercase tracking-[.12em] text-atlas-ink-muted">Devis</span>
                    <span className="mt-0.5 block text-xs font-semibold">Accepté par le client</span>
                </span>
            </div>

            <div className="landing-float-delayed absolute -bottom-5 right-1 z-20 hidden rounded-xl border border-atlas-border bg-white px-3.5 py-3 shadow-[0_14px_35px_rgb(16_28_26/0.14)] sm:flex sm:items-center sm:gap-3 lg:-right-4">
                <span className="grid size-8 place-items-center rounded-lg bg-[#fff5e7] text-atlas-warm">
                    <Icon name="billing" className="size-4" />
                </span>
                <span>
                    <span className="block text-[10px] font-medium uppercase tracking-[.12em] text-atlas-ink-muted">Encaissement</span>
                    <span className="mt-0.5 block text-xs font-semibold">Relance à préparer</span>
                </span>
            </div>

            <div className="relative overflow-hidden rounded-[1.6rem] border border-atlas-sidebar/15 bg-white p-2 shadow-[0_35px_90px_rgb(16_28_26/0.17)] sm:rounded-[2rem] sm:p-3">
                <div className="flex items-center justify-between border-b border-atlas-border px-3 py-2.5 sm:px-4">
                    <div className="flex items-center gap-2">
                        <span className="size-2.5 rounded-full bg-[#ff8b83]" />
                        <span className="size-2.5 rounded-full bg-[#f5c867]" />
                        <span className="size-2.5 rounded-full bg-[#5fc8a8]" />
                    </div>
                    <span className="text-[9px] font-medium uppercase tracking-[.14em] text-atlas-ink-muted">Aperçu produit · données d’exemple</span>
                </div>
                <div className="grid min-h-[28rem] grid-cols-[4.4rem_1fr] overflow-hidden rounded-b-[1.15rem] bg-[#f5f7f4] sm:grid-cols-[9.5rem_1fr]">
                    <div className="bg-atlas-sidebar p-3 text-white sm:p-4">
                        <div className="flex items-center gap-2">
                            <span className="grid size-7 place-items-center rounded-lg bg-white text-atlas-sidebar">
                                <svg aria-hidden="true" viewBox="0 0 32 32" className="size-4" fill="none">
                                    <path d="M7 24 14.7 8.2a1.45 1.45 0 0 1 2.6 0L25 24" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" />
                                    <path d="M10.6 19h10.8" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" />
                                </svg>
                            </span>
                            <span className="hidden text-xs font-semibold sm:block">Atlas</span>
                        </div>
                        <div className="mt-8 space-y-2">
                            {[
                                ['dashboard', 'Accueil'],
                                ['crm', 'CRM'],
                                ['billing', 'Facturation'],
                                ['activity', 'Santé'],
                                ['advisor', 'Advisor'],
                            ].map(([icon, label], index) => (
                                <div key={label} className={`flex items-center gap-2 rounded-lg px-2 py-2 text-[10px] ${index === 0 ? 'bg-white/10 text-white' : 'text-white/48'}`}>
                                    <Icon name={icon as IconName} className="size-3.5 shrink-0" />
                                    <span className="hidden sm:block">{label}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="min-w-0 p-4 sm:p-6">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-[9px] font-semibold uppercase tracking-[.14em] text-atlas-accent">Vue d’ensemble</p>
                                <p className="mt-1 text-lg font-semibold tracking-[-0.035em] sm:text-2xl">Bonjour Camille</p>
                            </div>
                            <span className="rounded-full border border-atlas-border bg-white px-2.5 py-1 text-[8px] text-atlas-ink-muted">À jour</span>
                        </div>

                        <div className="mt-5 rounded-xl bg-atlas-sidebar p-4 text-white sm:p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-[9px] font-medium text-[#67cdb3]">PRIORITÉ DU JOUR</p>
                                    <p className="mt-2 text-sm font-semibold sm:text-base">Préparer deux relances clients</p>
                                    <p className="mt-1 max-w-xs text-[9px] leading-4 text-white/48 sm:text-[10px]">Deux factures ont dépassé leur échéance et gardent un solde ouvert.</p>
                                </div>
                                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white/8 text-[#67cdb3]">
                                    <Icon name="arrow-right" className="size-4" />
                                </span>
                            </div>
                        </div>

                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-atlas-border bg-white p-3.5">
                                <div className="flex items-center justify-between">
                                    <p className="text-[9px] font-semibold">Pipeline commercial</p>
                                    <Icon name="crm" className="size-3.5 text-atlas-accent" />
                                </div>
                                <div className="mt-4 flex h-16 items-end gap-1.5">
                                    {[38, 64, 48, 78, 55, 88].map((height, index) => (
                                        <span key={`${height}-${index}`} className="flex-1 rounded-t bg-atlas-accent/15" style={{ height: `${height}%` }}>
                                            <span className="block h-[45%] rounded-t bg-atlas-accent" />
                                        </span>
                                    ))}
                                </div>
                                <p className="mt-3 text-[8px] text-atlas-ink-muted">4 étapes actives · source CRM</p>
                            </div>
                            <div className="rounded-xl border border-atlas-border bg-white p-3.5">
                                <div className="flex items-center justify-between">
                                    <p className="text-[9px] font-semibold">Facturation récente</p>
                                    <Icon name="billing" className="size-3.5 text-atlas-accent" />
                                </div>
                                <div className="mt-3 space-y-2.5">
                                    {[
                                        ['Maison Lumen', 'Réglée'],
                                        ['Ateliers du Marais', 'À suivre'],
                                        ['Collectif Cobalt', 'En retard'],
                                    ].map(([client, status], index) => (
                                        <div key={client} className="flex items-center gap-2 border-b border-atlas-border/70 pb-2 last:border-0 last:pb-0">
                                            <span className={`size-1.5 rounded-full ${index === 2 ? 'bg-rose-400' : index === 1 ? 'bg-amber-400' : 'bg-emerald-400'}`} />
                                            <span className="min-w-0 flex-1 truncate text-[8px] font-medium">{client}</span>
                                            <span className="text-[7px] text-atlas-ink-muted">{status}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-3 rounded-xl border border-atlas-border bg-white p-3.5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-[9px] font-semibold">Activité mesurée</p>
                                    <p className="mt-1 text-[8px] text-atlas-ink-muted">Données disponibles et fraîcheur publiées</p>
                                </div>
                                <div className="flex gap-1">
                                    {[true, true, true, false].map((filled, index) => (
                                        <span key={index} className={`size-2 rounded-full ${filled ? 'bg-atlas-accent' : 'bg-atlas-border'}`} />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function AdvisorPreview() {
    return (
        <div className="relative">
            <div className="pointer-events-none absolute -inset-10 rounded-full bg-atlas-accent/12 blur-3xl" />
            <div className="relative overflow-hidden rounded-[1.6rem] border border-white/10 bg-white/[0.055] p-5 shadow-2xl backdrop-blur sm:p-7">
                <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-5">
                    <div className="flex items-center gap-3">
                        <span className="grid size-10 place-items-center rounded-xl bg-[#61c9ae]/12 text-[#61c9ae]">
                            <Icon name="advisor" className="size-5" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold">Priorité Atlas</p>
                            <p className="mt-0.5 text-[10px] text-white/40">Construite depuis vos données</p>
                        </div>
                    </div>
                    <span className="rounded-full border border-rose-300/20 bg-rose-300/10 px-2.5 py-1 text-[9px] font-semibold text-rose-200">Priorité haute</span>
                </div>
                <div className="py-7">
                    <p className="text-xl font-semibold tracking-[-0.03em] sm:text-2xl">Sécuriser les encaissements en retard</p>
                    <p className="mt-3 max-w-xl text-sm leading-6 text-white/52">
                        Deux factures arrivées à échéance conservent un solde. Commencez par le dossier le plus ancien.
                    </p>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Signal', '2 soldes ouverts'],
                        ['Source', 'Facturation'],
                        ['Fraîcheur', 'Aujourd’hui'],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-xl border border-white/8 bg-black/10 p-3.5">
                            <p className="text-[9px] uppercase tracking-[.12em] text-white/32">{label}</p>
                            <p className="mt-1.5 text-xs font-medium text-white/75">{value}</p>
                        </div>
                    ))}
                </div>
                <div className="mt-5 flex flex-col gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-[10px] text-white/35">Aucune action n’est exécutée sans votre décision.</p>
                    <span className="inline-flex items-center gap-2 text-xs font-semibold text-[#78d9c0]">
                        Voir les factures
                        <Icon name="arrow-right" className="size-3.5" />
                    </span>
                </div>
            </div>
        </div>
    );
}
