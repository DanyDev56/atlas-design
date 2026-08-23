---
title: Palier 4 — UI démo / early access
status: Completed
owner: Product + Engineering
date: 2026-08-07
last_updated: 2026-08-23
references:
  - ../fondation/decisions/ADR-002-mvp-implementation-stack.md
  - ../evolution/blueprint/navigation.md
  - ../evolution/blueprint/dashboard.md
  - ../evolution/blueprint/user-journeys.md
  - ../evolution/blueprint/historical-import.md
  - ../fondation/product/pricing-strategy.md
  - ../fondation/decisions/ADR-003-subscriptions-context-ownership.md
  - runbooks/beta-release-checklist.md
---

# UI démo — périmètre Palier 4

Interface **React 19 + Vite + TypeScript** consommant l'API existante (`/api`).
Objectif : une UI présentable aux futurs utilisateurs — pas l'application complète.

Le Playground (`/playground`) reste l'outil dev ; l'app produit vit sous **`/app`**.

Cette surface permet de démontrer la valeur et de mener les entretiens de
pricing. Le backend contient désormais le catalogue candidat `Atlas Solo@1`, le
Trial de 30 jours, une projection d'Entitlements, un gateway factice et un
adaptateur Stripe Billing désactivé par défaut. L'UI
propriétaire expose l'essai, le périmètre et les prix candidats dans
`/app/settings/subscription`, ainsi qu'une simulation de checkout activable en
développement. Un cycle d'abonnement factice peut aussi être alimenté par des
webhooks HMAC pour vérifier activation, renouvellement, échec, résiliation,
ordre et rejeu. L'UI distingue explicitement cet état simulé d'un encaissement.
En mode Stripe configuré, elle redirige vers Checkout puis le Customer Portal,
sans considérer le retour navigateur comme une preuve de paiement. Elle
n'expose encore aucune offre contractuelle. Les gardes serveur, le bandeau d'accès restreint et le compteur de
places dans la gestion des membres sont livrés, mais l'enforcement reste
désactivé. Aucun prix ne doit être présenté comme
commercialisable avant passage du
[gate tarifaire](../fondation/product/pricing-strategy.md#gate-avant-commercialisation-payante).

---

## Lots

| Lot | Contenu | Statut | Gate |
|---|---|---|---|
| **0** | Shell, auth J1, client API, routing | ✓ | Connexion + navigation |
| **1** | Dashboard (widgets composition) | ✓ | Démo 2 min convaincante |
| **2** | CRM slice + devis | ✓ | Parcours J2 en UI |
| **3** | Polish démo (empty states, seed, responsive) | ✓ local | Recette humaine validée |
| **4** | Enrichissement CRM + Import clients/Billing + rebuild Analytics | ✓ BPT-013 | Package historique clients/Billing puis génération Analytics isolée |
| **5** | Abonnement candidat + checkout, cycle et accès simulés | ✓ local | Webhooks factices signés, gardes désactivées par défaut, aucun paiement |

---

## Priorité actuelle

Les lots 0 à 4 CRM, l’import historique Billing, le rebuild Analytics borné
et le step-up de confirmation sont livrés. L’import **clients** converge sur
`(Workspace, SourceSystem, ExternalId)` ; l’import Billing matérialise devis,
factures et paiements via l’outbox (`billing.history_import_requested`) sans
événements opérationnels. Le rebuild Analytics s’exécute après corrélation de
`ClientHistoryImportCompleted` et `BillingHistoryImportCompleted` pour le même
`SourceSystem`. Le dashboard compose aussi **Activité mesurée** depuis
`getAnalyticsOverview` (snapshot publié, jamais de totaux inventés).

Settings borné est livré : profil commercial, préférences, identité de
facturation (step-up), lecture des membres et invitations vers le rôle membre
standard. La preuve d’invitation est liée à l’adresse vérifiée, expire après
7 jours, peut être révoquée tant qu’elle est en attente et ne crée le
membership qu’à l’acceptation atomique. Le jeton n’est exposé qu’en
développement. L’invitation est maintenant remise par email via l’outbox ; les
rôles avancés restent hors périmètre.

Les avoirs de facturation sont livrés sur la fiche facture : brouillon,
émission, application totale ou partielle au solde, reliquat en crédit client
et prise en compte dans le net facturé Analytics. Les PDF déterministes des
devis, factures et avoirs sont générés à l'émission et téléchargeables par les
membres autorisés. Les relances sont enregistrées sur une facture émise avec
solde positif (`billing.invoices.remind`), placées dans l’outbox puis remises
par email avec le PDF de facture. Le canal manuel reste accepté par
compatibilité API. Un acompte unique (`Deposit`) peut être créé depuis un
devis accepté, puis la facture finale facture le reliquat une fois l’acompte
émis. L’import historique Billing inclut les avoirs déjà appliqués : CSV
canonique, soldes recalculés avec les paiements, identité
`(SourceSystem, ExternalId)`, aucun événement `CreditNoteIssued` /
`CreditNoteAppliedToInvoice`.

Le passage en retard est matérialisé par le scheduler (`atlas:billing:mark-overdue`,
horaire) : facture émise, solde positif, échéance strictement dépassée, une
seule fois par échéance. `SettlementStatus` reste dérivé du solde ; l’UI
affiche **En retard** sans bouton métier (`billing.invoices.mark-overdue` est
SystemActorOnly).

La récupération de mot de passe est livrée : demande opaque
(`POST /api/auth/recovery`), preuve à usage unique (1 h), nouveau mot de passe
(`POST /api/auth/recovery/complete`) qui incrémente `UserSecurityVersion` et
révoque toutes les sessions. Pas d’auto-connexion. Le jeton n’est exposé qu’en
debug, comme la vérification d’email. Les deux preuves sont désormais remises
par email via l’outbox.

Les travaux de publication OCI restent différés :
[`runbook des rôles d'exécution`](runbooks/runtime-roles.md#livraison-differee).

---

## Lot 0 — Fondations (livré)

### Livré

- Entrée SPA : `/app` → `resources/js/web/`
- Client HTTP (`api/client.ts`) : Bearer, `Idempotency-Key`, erreurs JSON
- Auth : register (verify debug si token), login, récupération de mot de passe
  (demande opaque + reset), workspace bootstrap
- Layout : sidebar, header, badge notifications
- Routes : login, register, mot de passe oublié, réinitialisation, onboarding,
  dashboard (placeholder Lot 1)

### Stack

- React 19, React Router 7, TypeScript strict
- Tailwind 4 (partagé avec Laravel Vite)
- Police : Instrument Sans (vite font plugin)

### Hors scope Lot 0

- Inertia, Livewire, Eloquent côté web (ADR-002)
- Logique métier recalculée dans le frontend
- Settings, rôles et import historique

---

## Lot 1 — Dashboard (priorité produit)

Écran héros branché sur `GET /api/workspaces/{id}/dashboard`.

| Widget | `data_state` UI |
|---|---|
| Priorité Advisor | Data / NoData / Unavailable |
| Business Health | Data / InsufficientData / NoData |
| Pipeline CRM | Data + volumes par état |
| Facturation | Liste factures récentes |
| Activité mesurée | Snapshot Analytics : période, fraîcheur, métriques strictes |
| Notifications | Badge non-lus (header) |

Règle : **jamais inventer** score, priorité ou compteur — afficher l'état API tel quel
(voir `dashboard.md`).

---

## Lot 2 — Vertical slice J2

### Livré

- `/app/crm` — liste clients + création
- `/app/crm/clients/:id` — détail client, opportunités, création opportunité
- `/app/crm/opportunities/:id` — qualification, création/envoi devis
- `/app/quotes/accept/:workspaceId/:quoteId` — acceptation publique (token en query)

### Parcours démo

1. CRM → Nouveau client
2. Client → Nouvelle opportunité
3. Opportunité → Qualifier → Nouveau devis → Envoyer
4. Ouvrir l'email dans Mailpit → suivre le lien d'acceptation → Confirmer

---

## Lot 3 — Polish présentation (livré localement)

### Livré

- Commande `make demo-seed` → compte `demo@atlas.test` / `DemoAtlas2026!`
- Commande `make demo-seed-empty` → compte `demo-empty@atlas.test` /
  `DemoEmpty2026!`, workspace dédié sans données métier et sans remise à zéro
  destructive
- Scénario démo versionné et rejouable : 6 clients, 7 contacts, 7 opportunités, devis
  brouillon/envoyé/accepté, facture à créer, brouillon à émettre, impayé
  partiellement réglé et historique soldé
- États Analytics, Santé, Advisor et Notifications reconstruits automatiquement ;
  un ancien compte démo est enrichi sans suppression de ses données
- Dashboard alimenté (outbox + snapshot analytics)
- Login : encart compte démo + pré-remplissage
- Déconnexion : révocation de la session serveur avant oubli local
- Session locale : rejet automatique des credentials expirés ou historiques incomplets
- Surfaces dev : `/api/dev/*`, `/api/spike/*` et jetons de vérification /
  récupération sous opt-in explicite
- Layout responsive : menu mobile, paddings tablette
- Workspace courant identifié par son nom autoritatif dans l’en-tête, via une
  lecture Workspace isolée par membership actif
- Composants `PageSkeleton` et `EmptyState` réutilisés
- Dashboard orienté action : recommandation traduite, urgence et impact lisibles,
  actions vers CRM ou facturation
- Santé de l'activité alignée sur le contrat API canonique (`overall_score`,
  `health_band`, `assessment_reliability`)
- Pipeline visualisé par étape et statuts de facturation traduits
- Rechargement explicite en cas d'erreur, focus clavier visible et respect de
  `prefers-reduced-motion`
- Onboarding reformulé autour de l'activité, avec progression et attente claire
  avant l'arrivée sur le dashboard
- Confirmations accessibles après création d'un client, d'une opportunité et
  d'un devis, avec suivi explicite de sa livraison email
- Page publique de devis complète avant acceptation : destinataire, lignes,
  montant total, validité et consentement explicite, avec lecture bornée par le
  jeton et erreurs masquées
- Espace Facturation accessible depuis la navigation, avec liste des devis et
  accès au détail
- Vérification obligatoire avant envoi : édition multi-lignes des brouillons,
  total recalculé par l'API, verrouillage explicite et livraison email suivie
  uniquement après l'envoi
- Parcours facture après acceptation : création idempotente depuis le devis,
  émission numérotée, envoi email suivi, renvoi possible, paiements partiels et solde restant
  autoritatif
- Liste persistante des factures dans Facturation, enrichie par le CRM sans en
  dépendre pour rester consultable
- Inbox Notifications accessible depuis l’en-tête et le dashboard, avec compteur
  autoritatif, filtre des non-lues, historique et marquage individuel comme lu
- Actions de notification limitées aux destinations CRM et Facturation
  allowlistées par le contrat Advisor
- Destination Santé de l’activité accessible depuis la navigation et le
  dashboard, avec score, fiabilité, couverture, facteurs et preuves issus de
  l’évaluation Business Health courante
- Actualisation explicite depuis Santé : publication déléguée au contrat
  Analytics, attente bornée de l’évaluation produite par le worker et état
  différé si la projection est encore en cours
- La fraîcheur Analytics décrit l’avancement réel du traitement, jamais le
  temps écoulé depuis la dernière activité métier : un Workspace sans nouvel
  événement reste courant lorsque tous ses événements connus sont traités
- Les états dépendants du temps — facture devenue en retard, encours, devis en
  attente, pipeline et fenêtres glissantes — sont recalculés à la date du
  snapshot même en l’absence de nouvel événement
- Une projection incomplète bloque temporairement la publication et conserve la
  dernière évaluation valide ; un snapshot `Lagging` historisé ne remplace pas
  cette vue courante
- Absence de score, données manquantes et risques observés expliqués sans
  transformer une preuve indisponible en note nulle
- Destination Advisor accessible depuis la navigation et le dashboard, avec
  priorité principale, alternatives, impact, urgence, confiance, effort et
  validité issus de l’overview courant
- États source insuffisante, source obsolète et absence justifiée de
  recommandation distingués ; rang interne non présenté comme une probabilité
- Décisions Advisor complètes : confirmation explicite d’une action réalisée ou
  rejet avec motif structuré, contrôle de révision, promotion immédiate de
  l’alternative suivante et relecture autoritative après chaque choix
- Navigation mobile fermable par Échap, focus restitué et défilement de fond
  bloqué pendant l'ouverture du menu
- États de chargement annoncés sans exposer les squelettes décoratifs aux
  technologies d'assistance
- Recette navigateur Playwright isolée des dépendances Vite : dashboard,
  données du scénario v4, accès aux devis et factures actionnables, navigation
  mobile et absence de débordement horizontal
- Facturation enrichie en parallèle avec les noms CRM pour réduire l’attente ;
  factures récentes du dashboard désormais directement ouvrables
- Libellés techniques de chantier retirés des écrans CRM et de connexion
- Retour d’un devis adapté à son point d’entrée : Facturation conserve le
  contexte, tandis que le parcours CRM revient à l’opportunité
- Parcours premier démarrage vérifié dans un navigateur, de l’inscription au
  dashboard en passant par la création de l’activité, sans pollution de la base
- États vides vérifiés sur dashboard, CRM, Facturation, Santé, Advisor et
  Notifications en desktop et mobile ; titres exposés comme vrais niveaux de
  section aux technologies d’assistance
- Fondations visuelles harmonisées sur tous les parcours : signature Atlas,
  navigation iconographique, en-têtes contextuels, surfaces, formulaires,
  statuts, chargements et feedbacks documentés dans
  [`docs/ui-foundations.md`](docs/ui-foundations.md)

### Compte démo

```bash
make demo-seed
make demo-seed-empty
# → http://localhost:8000/app/login
```

Identifiants affichés sur la page de connexion après seed.
Ils ne sont intégrés au build que si `VITE_DEMO_EMAIL` et `VITE_DEMO_PASSWORD`
sont définis. Les opt-in `ATLAS_DEVELOPMENT_ROUTES` et
`ATLAS_DEBUG_VERIFICATION_TOKENS` restent réservés au développement local.

### Reste hors scope Lot 3

- Env staging `demo.atlas…` (infra)

---

## Lot 4 — Enrichissement CRM

### Livré

- Lecture des contacts d'un client via un contrat API dédié et isolé par
  workspace avec la permission `crm.contacts.read`
- Fiche client enrichie : contacts, rôle, email, téléphone et identification du
  contact principal
- Ajout accessible depuis la fiche, champs optionnels validés par l'API,
  idempotence et contrôle de révision du client conservés
- Création d'opportunité enrichie d'un contact optionnel, avec présélection du
  contact principal et vérification de l'appartenance côté domaine
- Contact associé relu et présenté sur le détail de l'opportunité
- Contact principal modifiable ou effaçable depuis la fiche client, avec
  autorisation élevée, contrôle de révision, idempotence et relecture immédiate
- Événement `ClientPrimaryContactChanged` publié aussi bien lors de l'ajout d'un
  contact principal que lors d'un changement explicite
- Profil d'un contact éditable depuis sa carte : nom, rôle, email et téléphone,
  avec suppression explicite des champs optionnels laissés vides
- Mise à jour atomique des versions Client et Contact, idempotence et événement
  `ContactUpdated` sans donnée personnelle dans l'outbox
- Archivage confirmé par un motif conservé pour l'audit interne, refusé tant
  qu'une opportunité non terminale référence le contact
- Effacement atomique du contact principal lors de son archivage, avec événements
  `ContactArchived` et `ClientPrimaryContactChanged` sans donnée personnelle
- Contacts archivés conservés dans l'historique de la fiche, sans action de
  communication, d'édition ou d'affectation aux nouvelles opportunités
- Réactivation explicite d'un contact archivé, sans restauration automatique du
  statut principal, avec contrôle de révision et idempotence
- Événement `ContactReactivated` sans donnée personnelle et conservation des
  informations du dernier archivage pour l'audit interne
- Édition d'une opportunité `Open` ou `Qualified` : titre, estimation, devise et
  contact actif du même client, avec possibilité de retirer l'interlocuteur
- Réaffectation idempotente avec contrôle de révision et événement
  `OpportunityUpdated` sans donnée personnelle ; les devis et snapshots passés
  restent inchangés
- Clôture explicite d'une opportunité `Open` ou `Qualified` avec cinq raisons
  structurées et une note interne optionnelle bornée
- Résultat `Lost` terminal, idempotent et historisé ; événement
  `OpportunityLost` sans note libre et nouveau fait Analytics pour le pipeline
- Archivage d'un client après vérification autoritative de l'absence
  d'opportunités `Open` ou `Qualified`, avec motif d'audit et contrôle de révision
- Client archivé conservé dans les listes et sur une fiche historique en lecture
  seule ; contacts, opportunités et documents restent intacts et consultables
- Événement `ClientArchived` idempotent et sans donnée personnelle dans l'outbox
- Réactivation confirmée d'un client archivé avec contrôle de révision et
  idempotence, après validation du profil courant
- Retour à l'usage courant sans réactivation implicite des contacts archivés ;
  événement `ClientReactivated` sans donnée personnelle et audit d'archivage conservé
- Profil commercial d'un client actif consultable et modifiable depuis sa fiche :
  nom affiché, raison sociale, contexte, e-mail, téléphone et site web
- Mise à jour partielle validée par le domaine, idempotente et versionnée, sans
  perdre les métadonnées existantes ni modifier les snapshots Billing antérieurs
- Événement `ClientProfileUpdated` limité aux identifiants et versions, sans
  coordonnées ni description dans l'outbox
- Profil administratif d'un client actif consultable et remplaçable séparément :
  nom et e-mail de facturation, adresse, identifiants d'entreprise et fiscaux
- Données administratives normalisées et versionnées, avec rejet des types
  d'identifiants dupliqués, contrôle de révision et idempotence
- Les devis déjà créés conservent leur snapshot tandis que les documents futurs
  utilisent le nouveau profil ; événement `ClientBillingProfileUpdated` sans
  donnée administrative dans l'outbox
- Chronologie commerciale d'un client consultable depuis sa fiche, triée par
  date du fait et disponible en lecture sur un client archivé
- Ajout d'une note, d'un appel, d'une réunion ou d'un e-mail passé, avec
  rattachement facultatif à un contact et une opportunité du même dossier
- Agrégat Activity indépendant du Client, idempotent et atomique ; événement
  `ActivityRecorded` sans résumé libre dans l'outbox
- Scénario démo enrichi de huit interactions déterministes et contextualisées
- Correction du type, du résumé ou de la date d'une activité avec motif
  obligatoire, concurrence optimiste et rejeu idempotent
- Ancienne valeur, motif, acteur et instant conservés dans l'audit interne ;
  événement `ActivityCorrected` sans texte libre dans l'outbox
- Retrait logique et terminal d'une activité avec motif, acteur, instant,
  concurrence optimiste et rejeu idempotent
- Activité retirée absente de la chronologie courante sans suppression physique
  de son contenu ni de ses corrections ; événement `ActivityRemoved` sans texte
  libre dans l'outbox
- Scénario démo v4 enrichi de sept contacts déterministes, dont un principal par
  client, sans doublon lors d'une nouvelle exécution du seed
- Lecture d'audit autorisée et isolée par workspace, limitée aux activités
  corrigées ou retirées et distincte de la chronologie commerciale ordinaire
- Valeur courante, anciennes révisions, motifs, acteurs et horodatages présentés
  à la demande ; scénario v4 livré avec une correction et un retrait auditables
- Gain manuel d'une opportunité `Qualified` avec confirmation terminale,
  contrôle de révision, rejeu idempotent et permission élevée
- Résultat gagné conservant source `Manual | AcceptedQuote`, instant, acteur ou
  devis causal ; les deux sources produisent le même événement `OpportunityWon`
  et le même fait Analytics sans exposer l'acteur dans l'outbox

### Import historique clients (livré, borné CRM)

- Accessibilité depuis la navigation CRM via `/app/crm/import`
- Téléchargement d'un modèle CSV canonique avec colonnes obligatoires et optionnelles
- Prévisualisation non destructive : validation structurelle, détection de doublons
  et conflits d'identité historique `(SourceSystem, ExternalId)`
- Empreinte SHA256 immuable du package pour détection de modifications
- Confirmation explicite avec contrôle de l'empreinte, idempotence par
  `Idempotency-Key` et convergence du même `package_hash`
- Exécution via l'outbox (`ClientHistoryImportRequested` → worker
  `atlas:outbox:work` ; le contrôleur draine aussi l'outbox pour le dev local)
- Identité externe persistée sur le Client (pas dans le seul JSON profil) ;
  un rejeu identique ne duplique pas ; un contenu divergent sous la même clé
  est `Conflict`
- Aucun événement `ClientCreated` ; completion par `ClientHistoryImportCompleted`
- Isolation par workspace et permission `crm.clients.import-history` (Critical)
- Endpoints API : POST `preview`, POST `confirm`, GET status par `import_run_id`

### Import historique Billing (livré, borné devis/factures/paiements/avoirs)

- Accessibilité depuis Facturation via `/app/billing/import`
- Quatre CSV canoniques (`quotes`, `invoices`, `payments`, `credit_notes`) ; un
  fichier peut n’avoir que ses en-têtes, le package doit contenir au moins un
  enregistrement
- Prévisualisation non destructive : clients résolus depuis l’import CRM du
  même `SourceSystem`, arithmétique exacte, références paiement/avoir → facture
  du package, reliquat d’avoir `RefundDue` / `ClientCredit`
- Empreinte SHA256 immuable du package ; confirmation idempotente
- Exécution via l’outbox (`BillingHistoryImportRequested` → worker
  `atlas:outbox:work` ; le contrôleur draine aussi l’outbox pour le dev local)
- Checkpoints `Quotes` → `Invoices` → `Payments` → `CreditNotes` → `Validate` ;
  soldes dérivés des paiements et avoirs appliqués ; numéros source conservés,
  aucune séquence Atlas
- Aucun événement `QuoteSent` / `InvoiceIssued` / `PaymentRecorded` /
  `CreditNoteIssued` / `CreditNoteAppliedToInvoice` ; completion par
  `BillingHistoryImportCompleted`
- Documents importés lisibles et verrouillés contre les mutations opérationnelles
- Permission `billing.history.import` (Critical)
- Endpoints API : POST `preview`, POST `confirm`, GET status par `import_run_id`
- Confirmation CRM et Billing : step-up par re-saisie du mot de passe
  (`POST /api/auth/session/elevate`, portée `PermissionScoped`, 15 min).
  La prévisualisation n’exige pas d’élévation.

### Rebuild Analytics après import (livré, borné)

- Déclenché seulement après corrélation des deux completions pour le même
  `SourceSystem`
- Génération isolée `Building` puis bascule `Active` (`RebuildReason =
  HistoricalImport`) ; l’ancienne génération devient `Superseded`
- Faits Billing relus depuis le manifest d’import, sans `QuoteSent` /
  `InvoiceIssued` / `PaymentRecorded` / `CreditNoteIssued`
- Watermarks CRM/Billing avancés à la completion du rebuild ; snapshot publié
  sur la génération active
- Rejeu du même couple de runs sans doublon de faits ni de génération

### Settings workspace (livré, borné)

- Accessible depuis la navigation et le nom d’espace dans l’en-tête
- Profil commercial (`display_name`, nom commercial, description)
- Préférences (locale, fuseau, devise, pays) parmi les valeurs supportées
- Identité de facturation (raison sociale, email) avec step-up
- Lecture des membres et invitation vers le rôle membre standard
- Acceptation par preuve à usage unique, compte actif et adresse vérifiée
- Changement de rôle et rôles personnalisés hors surface

### Hors scope Lot 4 (BPT-013 restant)

- Connecteurs Freebe, Indy, Tiime

---

## Commandes

```bash
make serve                    # API :8000
make web-install              # première fois — deps npm React
make web-dev                  # Vite HMR :5173
make web-check                # TypeScript strict + build de production
make demo-seed                # compte démo + données présentation
make demo-seed-empty          # compte démo sans données métier

# Ouvrir http://localhost:8000/app
```

### Page blanche sur `/app`

1. **`public/hot`** doit contenir `http://localhost:5173` (pas `0.0.0.0`) — relancer `make web-dev`.
2. **CORS** : la page est sur `:8000`, Vite sur `:5173` — `vite.config.js` doit avoir `server.cors: true`.
3. Vérifier la console navigateur (F12) : erreurs CORS ou `@vitejs/plugin-react can't detect preamble` → ajouter `@viteReactRefresh` avant `@vite` dans `app.blade.php`.
4. **Sans Vite dev** : `npm run build` puis `make serve` seul (assets servis depuis `:8000`).

---

## Critères « prêt à présenter »

- [x] Dashboard compris en 30 s sans verbalisation
- [x] Parcours register → workspace → dashboard < 2 min
- [x] États vides/insuffisants soignés (pas d'écran blanc)
- [ ] Env démo stable (hors laptop personnel)
