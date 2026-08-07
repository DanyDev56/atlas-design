---
title: Runbook — Sauvegarde et restauration PostgreSQL
owner: Engineering
last_updated: 2026-08-07
references:
  - ../SEC-TEST-MATRIX.md
  - ../../fondation/security/mvp-threat-model.md
---

# Sauvegarde et restauration PostgreSQL

Objectif : prouver **SEC-TEST-023** et réduire **SEC-T25** avant toute beta avec
données réelles.

## Prérequis

- Stack Docker démarrée : `make up`
- Schémas module : `identity`, `workspace`, `crm`, `billing`, `analytics`,
  `business_health`, `advisor`, `notifications`, `platform`

## Sauvegarde

```bash
make backup
# ou
./implementation/scripts/backup-postgres.sh
```

Produit dans `implementation/backups/` :

- `atlas-<timestamp>.dump` — archive PostgreSQL (format custom)
- `atlas-<timestamp>.manifest.json` — métadonnées (version Postgres, schémas)

**RPO cible (beta)** : 24 h — à resserrer en production.

**RTO cible (beta)** : 4 h — inclut validation canary ci-dessous.

Les dumps locaux ne remplacent pas une copie hors site (SEC-GAP-006).

## Restauration

```bash
make restore BACKUP=implementation/backups/atlas-20260807T120000Z.dump
# ou
./implementation/scripts/restore-postgres.sh implementation/backups/atlas-....dump
```

La restauration :

1. termine les connexions actives sur `atlas` ;
2. exécute `pg_restore --clean --if-exists`.

## Canary SEC-TEST-023 (répétition release)

```bash
make verify-restore
# ou avec un dump précis :
./implementation/scripts/verify-restore-canary.sh implementation/backups/atlas-....dump
```

Étapes automatisées :

1. sauvegarde de l'état courant ;
2. restauration du dump cible ;
3. vérification des schémas module ;
4. `migrate:status` + tests architecture / oracle fixtures ;
5. restauration de l'état pré-canary.

Gate : canary vert avant toute rehearsal de release ou migration risquée.

## Après restauration

- Vérifier `php artisan migrate:status` — appliquer les migrations en attente si
  le dump est antérieur au code déployé (forward-fix documenté).
- Ne pas mélanger un dump d'un autre environnement sans contrôle d'isolation
  Workspace (SEC-T07).

## Incidents

| Symptôme | Action |
|---|---|
| `pg_restore` échoue (objets verrouillés) | Arrêter l'app (`docker compose stop app`), relancer restore |
| Schéma manquant post-restore | Recréer volume (`make down-clean && make up`) puis restore sur dump valide |
| Oracle fixtures en échec | Ne pas basculer le trafic ; investiguer corruption ou version de politique |
