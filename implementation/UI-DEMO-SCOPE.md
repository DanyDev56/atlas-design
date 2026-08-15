---
title: Palier 4 — UI démo / early access
status: In Progress
owner: Product + Engineering
date: 2026-08-07
references:
  - ../fondation/decisions/ADR-002-mvp-implementation-stack.md
  - ../evolution/blueprint/navigation.md
  - ../evolution/blueprint/dashboard.md
  - ../evolution/blueprint/user-journeys.md
  - runbooks/beta-release-checklist.md
---

# UI démo — périmètre Palier 4

Interface **React 19 + Vite + TypeScript** consommant l'API existante (`/api`).
Objectif : une UI présentable aux futurs utilisateurs — pas l'application complète.

Le Playground (`/playground`) reste l'outil dev ; l'app produit vit sous **`/app`**.

---

## Lots

| Lot | Contenu | Statut | Gate |
|---|---|---|---|
| **0** | Shell, auth J1, client API, routing | ✓ | Connexion + navigation |
| **1** | Dashboard (widgets composition) | ✓ | Démo 2 min convaincante |
| **2** | CRM slice + devis | ✓ | Parcours J2 en UI |
| **3** | Polish démo (empty states, seed, responsive) | ✓ local | Recette humaine validée |
| **4** | Enrichissement CRM (contacts client) | ◐ | Contact exploitable depuis la fiche |

---

## Priorité actuelle

La recette locale du polish UI/UX est validée, y compris la compréhension du
dashboard en moins de 30 secondes. La tranche courante enrichit la fiche client
avec ses contacts avant de poursuivre les autres opérations CRM. Les travaux
de publication et de déploiement de l'image OCI sont volontairement différés et
restent tracés dans le
[`runbook des rôles d'exécution`](runbooks/runtime-roles.md#livraison-differee).

L'ordre de travail retenu est :

1. afficher les contacts rattachés à un client et identifier le principal ;
2. permettre leur ajout depuis la fiche avec contrôle de révision ;
3. relier ensuite le contact choisi aux opportunités commerciales ;
4. poursuivre avec l'édition et le cycle de vie CRM sans déplacer les règles
   métier dans le frontend.

---

## Lot 0 — Fondations (livré)

### Livré

- Entrée SPA : `/app` → `resources/js/web/`
- Client HTTP (`api/client.ts`) : Bearer, `Idempotency-Key`, erreurs JSON
- Auth : register (verify debug si token), login, workspace bootstrap
- Layout : sidebar, header, badge notifications
- Routes : login, register, onboarding, dashboard (placeholder Lot 1)

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
4. Copier le lien d'acceptation → page publique → Confirmer

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
- Surfaces dev : `/api/dev/*`, `/api/spike/*` et jeton de vérification sous opt-in explicite
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
  d'un devis ; lien public copiable et prévisualisable après envoi
- Page publique de devis complète avant acceptation : destinataire, lignes,
  montant total, validité et consentement explicite, avec lecture bornée par le
  jeton et erreurs masquées
- Espace Facturation accessible depuis la navigation, avec liste des devis et
  accès au détail
- Vérification obligatoire avant envoi : édition multi-lignes des brouillons,
  total recalculé par l'API, verrouillage explicite et lien client disponible
  uniquement après l'envoi
- Parcours facture après acceptation : création idempotente depuis le devis,
  émission numérotée, confirmation d'envoi, paiements partiels et solde restant
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
  données du scénario v3, accès aux devis et factures actionnables, navigation
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

## Lot 4 — Enrichissement CRM (en cours)

### Livré

- Lecture des contacts d'un client via un contrat API dédié et isolé par
  workspace avec la permission `crm.contacts.read`
- Fiche client enrichie : contacts, rôle, email, téléphone et identification du
  contact principal
- Ajout accessible depuis la fiche, champs optionnels validés par l'API,
  idempotence et contrôle de révision du client conservés
- Scénario démo v3 enrichi de sept contacts déterministes, dont un principal par
  client, sans doublon lors d'une nouvelle exécution du seed

### Suite logique

- Sélectionner un contact lors de la création d'une opportunité
- Ajouter les opérations d'édition, changement de contact principal et archivage
  lorsque leurs contrats applicatifs seront disponibles

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
