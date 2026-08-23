---
id: RUN-019
title: Back-office Operator Access
status: In Review
owner: Engineering and Security
version: 0.1.0
last_updated: 2026-08-23

references:
  - ../../fondation/decisions/ADR-004-operator-control-plane.md
  - ../../evolution/blueprint/backoffice.md
  - ../../evolution/blueprint/backoffice-implementation-plan.md
  - ../SEC-TEST-MATRIX.md
---

# Accès opérateur au back-office

## Portée actuelle

Le premier incrément fournit un shell `/backoffice` sans donnée métier et en
lecture seule. Il sépare les sessions opérateur des sessions Workspace, stocke
les jetons uniquement sous forme hachée et audite provisioning, refus,
connexion, consultation du contexte et révocation. Un trigger PostgreSQL rend
le registre d'audit append-only, y compris face à une mutation accidentelle.

Le mode mot de passe seul est strictement réservé à `local` et `testing`. Le
middleware refuse l'accès en staging ou production, même si une variable est
mal configurée. Ne jamais exposer ce mode par Quick Tunnel.

## Activation locale

Ajouter dans `implementation/app/.env` :

```dotenv
BACKOFFICE_ENABLED=true
BACKOFFICE_ALLOW_PASSWORD_ONLY_LOCAL=true
BACKOFFICE_READ_ONLY=true
BACKOFFICE_SESSION_MINUTES=30
```

Puis appliquer la migration et purger un éventuel cache de configuration :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan migrate
docker compose -f implementation/docker-compose.yml exec app php artisan config:clear
```

## Provisionner un opérateur

Le compte doit déjà exister, être actif et avoir une adresse vérifiée. Aucun
formulaire public, rôle Owner ou invitation Workspace ne crée un grant.

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:grant demo@atlas.test \
  --reason="Accès opérateur local pour recette"
```

Sans `--permissions`, le grant reçoit uniquement :

- `operations.backoffice.access` ;
- `operations.dashboard.read`.

Une expiration peut être imposée avec `--expires=2026-08-24T18:00:00+02:00`.
Les permissions supplémentaires sont passées par une liste séparée par des
virgules et sont rejetées si elles ne figurent pas au catalogue canonique.

Ouvrir ensuite `http://localhost:8000/backoffice/login` avec les identifiants
du compte. Un jeton `/app` ne fonctionne pas sur `/api/operator`, et le jeton
opérateur ne fonctionne pas sur `/api/auth/session/context`.

## Révoquer immédiatement

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:revoke demo@atlas.test \
  --reason="Fin de recette locale"
```

La commande révoque le grant et toutes ses sessions opérateur actives dans la
même transaction. Elle ne révoque pas les sessions Workspace du compte.

## Vérifications minimales

```bash
./implementation/scripts/run-tests.sh \
  tests/Unit/Operations/OperatorPermissionCatalogTest.php \
  tests/Integration/Operations/OperatorAccessFoundationTest.php

make web-check
```

Attendus : séparation d'audience, deny-by-default, révocation immédiate,
absence du jeton brut en base, audit présent et build frontend valide.

## Conditions avant une cible externe

Ne pas activer le back-office hors local tant que les éléments suivants ne sont
pas livrés et recettés :

- MFA opérateur et step-up pour les capacités sensibles ;
- politique de provisioning, rotation, break-glass et revue des grants ;
- rétention et accès à l'audit privilégié ;
- collecte d'alertes et journalisation centralisée sans donnée sensible ;
- gates des écrans effectivement raccordés aux données.

Le flag serveur reste `BACKOFFICE_ENABLED=false` sur toute cible qui ne remplit
pas ces conditions.
