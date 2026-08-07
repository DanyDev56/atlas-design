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
| **3** | Polish démo (empty states, seed, responsive) | ◐ | Beta élargie |

---

## Lot 0 — Fondations (en cours)

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
- Settings, rôles, import historique, inbox complète

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

## Lot 3 — Polish présentation (en cours)

### Livré

- Commande `make demo-seed` → compte `demo@atlas.test` / `DemoAtlas2026!`
- Données : 2 clients, opportunités (Open + Qualified), devis brouillon + accepté, facture payée
- Dashboard alimenté (outbox + snapshot analytics)
- Login : encart compte démo + pré-remplissage
- Layout responsive : menu mobile, paddings tablette
- Composants `PageSkeleton` et `EmptyState` réutilisés

### Compte démo

```bash
make demo-seed
# → http://localhost:8000/app/login
```

Identifiants affichés sur la page de connexion après seed.

### Reste hors scope Lot 3

- Env staging `demo.atlas…` (infra)

---

## Commandes

```bash
make serve                    # API :8000
make web-install              # première fois — deps npm React
make web-dev                  # Vite HMR :5173
make demo-seed                # compte démo + données présentation

# Ouvrir http://localhost:8000/app
```

### Page blanche sur `/app`

1. **`public/hot`** doit contenir `http://localhost:5173` (pas `0.0.0.0`) — relancer `make web-dev`.
2. **CORS** : la page est sur `:8000`, Vite sur `:5173` — `vite.config.js` doit avoir `server.cors: true`.
3. Vérifier la console navigateur (F12) : erreurs CORS ou `@vitejs/plugin-react can't detect preamble` → ajouter `@viteReactRefresh` avant `@vite` dans `app.blade.php`.
4. **Sans Vite dev** : `npm run build` puis `make serve` seul (assets servis depuis `:8000`).

---

## Critères « prêt à présenter »

- [ ] Dashboard compris en 30 s sans verbalisation
- [ ] Parcours register → workspace → dashboard < 2 min
- [ ] États vides/insuffisants soignés (pas d'écran blanc)
- [ ] Env démo stable (hors laptop personnel)
