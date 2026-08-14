---
title: Runbook — Rôles d'exécution
owner: Engineering
last_updated: 2026-08-14
references:
  - ../../fondation/decisions/ADR-001-mvp-application-topology.md
  - ../docker-compose.yml
  - outbox-incident.md
---

# Rôles d'exécution

Le profil Compose `runtime` matérialise les trois rôles prévus par
l'architecture avec la même image applicative :

| Service | Processus | Responsabilité |
|---|---|---|
| `api` | `php artisan serve` | HTTP et SPA, exposés sur `http://localhost:8080` |
| `worker` | `php artisan atlas:outbox:work` | Traitement continu et idempotent de l'outbox |
| `scheduler` | `php artisan schedule:work` | Déclenchement des tâches planifiées |

Ce profil sert à la répétition locale et staging de la topologie. Le serveur
HTTP Artisan n'est pas le serveur de production cible ; la plateforme choisie
devra exécuter le même artefact derrière son serveur HTTP managé.

## Démarrage et arrêt

Depuis la racine du dépôt :

```bash
make up-runtime
make logs-runtime
make stop-runtime
```

Le profil démarre PostgreSQL si nécessaire, sans lancer le conteneur de
développement `app`. L'API utilise le port `8080` afin de pouvoir cohabiter
avec `make serve` sur le port `8000`.

Par défaut, les processus démarrent avec `APP_ENV=staging` et
`APP_DEBUG=false`. Un test strictement local peut les surcharger :

```bash
RUNTIME_APP_ENV=local RUNTIME_APP_DEBUG=true make up-runtime
```

## Vérifications

```bash
curl --fail http://localhost:8080/up
docker compose -f implementation/docker-compose.yml --profile runtime ps
docker compose -f implementation/docker-compose.yml --profile runtime exec worker \
  php artisan atlas:outbox:process --batch=100
```

Les trois rôles partagent le code, la configuration de base et PostgreSQL,
mais publient des noms OpenTelemetry distincts : `atlas-api`, `atlas-worker`
et `atlas-scheduler`.

## Arrêt gracieux du worker

Le worker termine son lot courant lorsqu'il reçoit `SIGINT`, `SIGTERM` ou
`SIGQUIT`. Compose lui accorde 30 secondes avant de forcer l'arrêt. Les options
opérationnelles sont :

```text
--batch=100       nombre maximal de messages par cycle
--sleep=2         attente en secondes lorsque l'outbox est vide
--max-cycles=0    zéro pour tourner en continu, valeur positive pour un test borné
```

Les erreurs de consumer utilisent un backoff exponentiel borné. Après le nombre
maximal d'essais, le message passe en dead-letter sans arrêter le worker ni
bloquer les événements suivants. La procédure de diagnostic et la commande de
remise en file figurent dans le runbook incident.

En cas de backlog, suivre le runbook
[`outbox-incident.md`](outbox-incident.md).
