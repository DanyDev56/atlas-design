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

# 2. Bootstrap Laravel (première fois uniquement)
./scripts/bootstrap.sh

# Si un bootstrap précédent s'est arrêté sur pest --init, relancer suffit :
# le script reprend automatiquement là où il en était.

# 3. Vérifier la connexion PostgreSQL
docker compose exec app php artisan db:show
```

Après le bootstrap, l'application Laravel se trouve dans `implementation/app/`.

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
make down        # Arrêter
make shell       # Shell dans le conteneur app
make test        # Tests Pest (après bootstrap)
make check-docs  # Quality gates documentaires du dépôt parent
make backup      # Sauvegarde PostgreSQL (Palier 3)
make verify-restore  # Canary SEC-TEST-023
make up-observability  # Jaeger + OTLP collector
```

Runbooks Palier 3 : [`runbooks/`](runbooks/) · Clôture Track A :
[`PALIER-3-CLOSURE.md`](PALIER-3-CLOSURE.md) · Matrice SEC-TEST :
[`SEC-TEST-MATRIX.md`](SEC-TEST-MATRIX.md) · Idempotence :
[`docs/idempotency.md`](docs/idempotency.md).

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
| 3 | Billing + import historique | MVP-J2 |
| 4 | Analytics | Fixtures FIX-001…010 |
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
