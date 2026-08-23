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
l'architecture avec la même image applicative immuable `atlas-app:runtime` :

| Service | Processus | Responsabilité |
|---|---|---|
| `api` | `php artisan serve` | HTTP et SPA, exposés sur `http://localhost:8080` |
| `worker` | `php artisan atlas:outbox:work` | Traitement continu et idempotent de l'outbox |
| `scheduler` | `php artisan schedule:work` | Déclenchement des tâches planifiées |

Ce profil sert à la répétition locale et staging de la topologie. Le serveur
HTTP Artisan n'est pas le serveur de production cible ; la plateforme choisie
devra exécuter le même artefact derrière son serveur HTTP managé.

## Artefacts de développement et runtime

Le Dockerfile multi-stage publie deux cibles distinctes :

| Cible | Usage | Contenu |
|---|---|---|
| `development` | boucle locale et CI Pest | Composer, Node, Git, jq, ripgrep et bind mount `/workspace` |
| `runtime` | rehearsal et promotion OCI | code, vendor `--no-dev`, assets Vite, OPcache et extensions d'exécution |

La cible `runtime` :

- ne dépend d'aucun fichier de l'hôte et n'a aucun mount ;
- exclut tests, Composer, Node et dépendances de développement ;
- s'exécute avec l'utilisateur non privilégié `www-data` ;
- garde le code et les assets en lecture seule pour ce processus ; seuls
  `storage` et `bootstrap/cache` sont inscriptibles ;
- exige que `APP_KEY` soit injectée à l'exécution ;
- utilise le même digest pour les rôles API, worker et scheduler.

## Démarrage et arrêt

Depuis la racine du dépôt :

```bash
make runtime-build
export RUNTIME_APP_KEY="base64:$(openssl rand -base64 32)"
make runtime-smoke
make up-runtime
make logs-runtime
make stop-runtime
```

Le profil démarre PostgreSQL si nécessaire, sans lancer le conteneur de
développement `app`. L'API utilise le port `8080` afin de pouvoir cohabiter
avec `make serve` sur le port `8000`.

Par défaut, les processus démarrent avec `APP_ENV=staging` et
`APP_DEBUG=false`. `make up-runtime` et `make runtime-smoke` refusent de
démarrer sans `RUNTIME_APP_KEY`. Cette clé est une configuration d'exécution :
elle ne doit jamais être ajoutée au dépôt ni à l'image.

Le serveur HTTP utilise `artisan serve --no-reload` afin que son processus PHP
conserve toutes les variables injectées par l'orchestrateur. Le rechargement du
fichier `.env` n'est pas nécessaire dans une image runtime immuable.

Les routes et jetons de développement restent explicitement désactivés dans ce
profil. `RUNTIME_TRUST_PROXIES=true` ne doit être défini que lorsque l'origine
est isolée derrière le reverse proxy retenu. Si ce proxy ne transmet pas un
schéma HTTPS exploitable, `RUNTIME_FORCE_HTTPS=true` force les URL générées par
Laravel en HTTPS ; cette option exige une origine publique réellement servie en
HTTPS.

Un test strictement local peut surcharger l'environnement :

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

Le script `implementation/scripts/smoke-runtime.sh` automatise les preuves
suivantes : migration, santé `/up`, UID non-root, même image et absence de mount
pour les trois rôles, code non inscriptible, présence de `vendor` et du manifest
Vite, absence des toolchains de build, cycle worker borné et chargement du
scheduler. Le job CI `runtime-smoke` l'exécute sur chaque push et pull request.

Les trois rôles partagent le code, la configuration de base et PostgreSQL,
mais publient des noms OpenTelemetry distincts : `atlas-api`, `atlas-worker`
et `atlas-scheduler`.

## Livraison différée

La construction et la répétition locale de l'image sont livrées. Sa publication
et son déploiement sont volontairement différés pendant la prochaine tranche
UI/UX. Ils ne doivent pas être considérés comme réalisés tant que les preuves
suivantes ne sont pas réunies :

- [ ] publier l'image dans le registre retenu et l'adresser par son digest
  immuable (`repository@sha256:…`), relié au commit, au SBOM et à la provenance ;
- [ ] déployer ce même digest en staging pour les rôles API, worker et scheduler ;
- [ ] exécuter les migrations via un job ponctuel avant la bascule applicative ;
- [ ] répéter un rollback vers le digest précédent et documenter la stratégie de
  compatibilité de base de données (rollback vérifié ou forward-fix) ;
- [ ] conserver les résultats de migration, smoke test et rollback comme preuves
  de la répétition staging.

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
