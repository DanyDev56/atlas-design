# Atlas — Implémentation MVP

Ce dossier contient le code exécutable d'Atlas, conformément à
[`ADR-001`](../fondation/decisions/ADR-001-mvp-application-topology.md) et
[`ADR-002`](../fondation/decisions/ADR-002-mvp-implementation-stack.md).

Le référentiel parent reste la source de vérité métier. Ce dossier prouve
l'exécutabilité du MVP minimal : frontières modules, outbox, parcours J1–J3
et quality gates automatisés. Clôture technique :
[`MVP-CLOSURE.md`](MVP-CLOSURE.md) (2026-08-07).

---

## Prérequis

- Docker et Docker Compose
- Git

PHP, Composer et Node ne sont pas requis sur l'hôte : ils tournent dans les
conteneurs.

---

## Démarrage rapide

```bash
# 1. Environnement de développement
cd implementation
docker compose up -d

# Si un premier démarrage a échoué (PostgreSQL 18), supprimer le volume corrompu :
# docker compose down -v && docker compose up -d

# 2. Bootstrap local (réexécutable sans écraser .env ni APP_KEY)
./scripts/bootstrap.sh

# Ne pas utiliser sudo : Docker Desktop doit être accessible à l'utilisateur courant.
# Le script installe Composer/npm depuis les lockfiles, puis applique les migrations.

# 3. Vérifier la connexion PostgreSQL
docker compose exec app php artisan db:show
```

Après le bootstrap, l'application Laravel se trouve dans `implementation/app/`.
Les emails de développement sont capturés par Mailpit sur
<http://localhost:8025>. Voir le
[`runbook de livraison email`](runbooks/email-delivery.md).

---

## Structure cible (ADR-002)

```text
implementation/
  app/                    # Projet Laravel (généré par bootstrap.sh)
    apps/
      api/
      worker/
      scheduler/
      web/
    src/
      modules/            # 8 bounded contexts MVP
      composition/        # onboarding, dashboard, settings
      platform/           # persistence, messaging, security…
  docker-compose.yml
  scripts/
  spike-checklist.md      # 12 conditions ADR-002
```

---

## Incrément 0 — objectif

Prouver les douze conditions listées dans
[`spike-checklist.md`](spike-checklist.md).

Gate de sortie :

- un module exemple commit état + Domain Event + outbox atomiquement ;
- un consumer rejoue le message sans double effet ;
- la trace complète est consultable ;
- les tests d'architecture bloquent les imports inter-modules.

Plan détaillé :
[`evolution/blueprint/implementation-plan.md`](../evolution/blueprint/implementation-plan.md).

---

## Commandes utiles

```bash
make up          # Démarrer les services
make share       # Partager temporairement Atlas par un Quick Tunnel HTTPS
make stop-share  # Arrêter le tunnel depuis un autre terminal
make logs-share  # Suivre les logs cloudflared
make up-stripe   # Démarrer Atlas avec le listener webhook Stripe sandbox
make logs-stripe # Suivre les événements transférés par Stripe CLI
make stop-stripe # Arrêter uniquement le listener Stripe
make runtime-build # Construire l'artefact OCI immuable
make runtime-smoke # Tester l'artefact (RUNTIME_APP_KEY requis)
make up-runtime  # Démarrer les rôles API + worker + scheduler
make logs-runtime # Suivre leurs logs
make stop-runtime # Arrêter les rôles sans arrêter PostgreSQL
make down        # Arrêter
make shell       # Shell dans le conteneur app
make test        # Tests Pest dans la base isolée atlas_test (après bootstrap)
make web-check   # TypeScript strict + build Vite
make check-docs  # Quality gates documentaires du dépôt parent
make backup      # Sauvegarde PostgreSQL (Palier 3)
make verify-restore  # Canary SEC-TEST-023
make retention-purge # Purge rétention beta (SEC-GAP-004)
make up-observability  # Jaeger + OTLP collector
```

Landing Early Access publique : `/` — présentation du produit, conditions
d'exploration et conversion vers l'inscription. UI démo Palier 4 :
[`UI-DEMO-SCOPE.md`](UI-DEMO-SCOPE.md) — application React sur `/app`.
La démonstration extérieure sans déploiement est décrite dans le
[`runbook Quick Tunnel`](runbooks/quick-tunnel-demo.md).
La préparation d'une cohorte externe, le support, la sortie, la mesure et les
projets de textes sont centralisés dans le
[`programme beta fermée`](runbooks/beta-program.md) ; sa décision d'ouverture
reste pilotée par la
[`checklist de release`](runbooks/beta-release-checklist.md).
Le futur plan de contrôle interne est spécifié dans le
[`Blueprint du back-office`](../evolution/blueprint/backoffice.md), son
[`catalogue de métriques`](../evolution/blueprint/backoffice-metrics.md) et son
[`plan d'implémentation`](../evolution/blueprint/backoffice-implementation-plan.md).
Le socle local, son provisioning et sa révocation sont décrits dans le
[`runbook d'accès au back-office`](runbooks/backoffice-access.md). `ADR-004` est
accepté ; TOTP et le step-up sont livrés localement, mais l'accès externe reste
bloqué jusqu'à une authentification résistante au phishing ou une acceptation
de risque strictement bornée. Le registre pseudonymisé et la révocation ciblée
des sessions Operator sont disponibles sur `/backoffice/security` sous flags,
permissions et step-up explicites ; ils ne touchent jamais une session Workspace.
Les registres runtime, continuité, abonnements et webhooks sont décrits dans le
[`runbook d'exploitation du back-office`](runbooks/backoffice-operations.md).
Les dossiers support, demandes de données, versions de textes et consentements
facultatifs sont décrits dans le
[`runbook Support et conformité`](runbooks/support-compliance-operations.md).
La gestion locale du statut et de l'assignation Support y est activable comme
première action opérateur bornée ; les flags restent sûrs par défaut et aucune
action destructive n'est ouverte.
Le scénario de démonstration correspondant est créé par `make backoffice-seed`.
Le cycle d'abonnement factice signé et son rejeu sont décrits dans le
[`runbook des webhooks d'abonnement`](runbooks/subscription-webhooks.md).
La configuration Checkout, Customer Portal et dunning Stripe est décrite dans
le [`runbook Stripe Billing`](runbooks/stripe-billing.md).
Compte présentation : `make demo-seed` puis connexion `demo@atlas.test`. La
commande crée ou met à niveau un scénario rejouable couvrant les principaux
états CRM, devis, factures, Santé, Advisor et Notifications.
Compte sans données : `make demo-seed-empty` puis connexion
`demo-empty@atlas.test` pour vérifier le premier démarrage et tous les états
vides sans effacer le scénario de présentation.

Runbooks Palier 3 : [`runbooks/`](runbooks/) · Clôture Track A :
[`PALIER-3-CLOSURE.md`](PALIER-3-CLOSURE.md) · Matrice SEC-TEST :
[`SEC-TEST-MATRIX.md`](SEC-TEST-MATRIX.md) · Idempotence :
[`docs/idempotency.md`](docs/idempotency.md).

Topologie API / worker / scheduler :
[`runbooks/runtime-roles.md`](runbooks/runtime-roles.md).

Après pull Palier 3+, mettre à jour les dépendances PHP dans le conteneur :

```bash
make shell
composer update --no-interaction
```

---

## Ordre de construction

| Incrément | Contenu | Parcours |
|---|---|---|
| 0 | Socle, outbox, observabilité | Spike ADR-002 |
| 1 | Identity + Workspace | MVP-J1 |
| 2 | CRM | — |
| 3 | Billing | MVP-J2 (import historique Billing livré Palier 4) |
| 4 | Analytics | Fixtures FIX-001…010 + rebuild après import historique |
| 5 | Business Health | Fixtures |
| 6 | Advisor | Fixtures |
| 7 | Notifications + Dashboard | MVP-J3 |
| 8 | Durcissement | Release candidate ☑ |

Clôture technique : [`MVP-CLOSURE.md`](MVP-CLOSURE.md) · Checklist RC : [`MVP-RC.md`](MVP-RC.md)

---

## Fixtures de référence

Les oracles exécutables du MVP se trouvent dans
[`evolution/reference-fixtures/mvp-v1.json`](../evolution/reference-fixtures/mvp-v1.json).
Les tests d'incrément 4+ doivent converger vers ces résultats déterministes.
