---
id: RUN-021
title: Back-office Operations and Subscriptions
status: In Review
owner: Engineering and Operations
version: 0.2.0
last_updated: 2026-08-24

references:
  - backoffice-access.md
  - backup-restore.md
  - subscription-webhooks.md
  - ../../evolution/blueprint/backoffice-implementation-plan.md
---

# Exploitation et abonnements dans le back-office

## Portée livrée

`/backoffice/runtime` expose en lecture seule les métriques HTTP RED agrégées,
les derniers heartbeats de l'API, du worker Outbox et du scheduler, une sonde
PostgreSQL réalisée à la lecture, les états d'alerte, ainsi que le registre des
sauvegardes et restaurations canary.

`/backoffice/subscriptions` expose les abonnements récurrents et l'inbox des
webhooks. Les UUID Workspace et Subscription, références Stripe, payloads,
empreintes, motifs d'échec et données bancaires ne sont jamais chargés. Des
références HMAC locales `WS-*`, `SUB-*` et `WH-*` permettent uniquement la
corrélation dans cet environnement.

La page exige `operations.subscriptions.read`. Le runtime et la continuité sont
inclus dans `operations.dashboard.read`, car ils ne révèlent pas davantage que
les cartes de la vue générale. Toute lecture API est réautorisée et auditée.

## Séparation Sandbox et Live

Chaque nouvel abonnement et webhook conserve l'environnement déterminé au
moment de l'ingestion :

- gateway `fake` ou clé Stripe `sk_test_*` : `Sandbox` ;
- clé Stripe `sk_live_*` : `Live` ;
- toute configuration non reconnue : `Unknown`.

Les lignes antérieures à la migration restent `Unknown`. Elles ne sont jamais
reclassées automatiquement à partir de la configuration courante. L'interface
affiche cet état et propose un filtre explicite ; elle ne fusionne pas
silencieusement les résultats live et sandbox.

## Heartbeats

- l'API enregistre au plus un heartbeat toutes les 30 secondes lorsqu'elle
  traite une requête API ou `/up` ;
- le worker enregistre son signal dans chaque cycle de traitement ;
- le scheduler exécute `atlas:operations:heartbeat scheduler` chaque minute ;
- un signal est considéré courant pendant 120 secondes puis devient `Stale` ;
- l'absence d'une ligne vaut `NotCollected`, jamais `Healthy`.

Le heartbeat API prouve une activité récente, pas une disponibilité historique.
La projection RED complète ce signal avec des buckets minute conservés 30 jours
par défaut. Elle n'est pas un remplacement des traces Jaeger ni d'un backend de
métriques à grande échelle.

Diagnostic manuel autorisé :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operations:heartbeat api
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operations:heartbeat worker
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operations:heartbeat scheduler
```

## HTTP RED et confidentialité

`HttpRedMetricsMiddleware` enregistre après chaque route API : méthode, gabarit
Laravel, classe `1xx` à `5xx`, volume, somme et maximum des durées. Le gabarit
`/api/workspaces/{workspaceId}/summary` est conservé ; l'UUID réellement appelé,
la query string, le corps, l'utilisateur, le Workspace et le correlation ID ne
le sont jamais.

La carte générale utilise cinq minutes. Le détail permet 5 min, 15 min, 1 h ou
24 h. Une fenêtre vide vaut `NoData`. La rétention est appliquée par
`atlas:operations:evaluate-alerts` et vaut 30 jours par défaut.

## Alertes externes

Le scheduler exécute l'évaluateur chaque minute pour :

- taux 5xx au-dessus du seuil, seulement après un volume minimum ;
- heartbeat API, worker ou scheduler périmé ;
- webhook d'abonnement `Failed` ou `Deferred` ;
- dernier backup en échec ou plus ancien que la fenêtre, et canary en échec.

Un état `Firing` persiste tant que la condition tient. Une notification est
envoyée à l'ouverture, à la résolution et après le délai de rappel, jamais à
chaque évaluation. Une source illisible conserve son dernier état au lieu de le
remplacer par un faux succès. Un rôle jamais observé et une sauvegarde jamais
exécutée restent visibles mais ne déclenchent pas d'alerte de démarrage.

Configuration :

```dotenv
OPERATIONS_ALERT_WEBHOOK_URL=https://incident-relay.example.test/atlas
OPERATIONS_ALERT_REPEAT_MINUTES=60
OPERATIONS_HTTP_MINIMUM_REQUESTS=20
OPERATIONS_HTTP_ERROR_RATE_THRESHOLD_PERCENT=10
OPERATIONS_RUNTIME_STALE_SECONDS=180
OPERATIONS_BACKUP_STALE_SECONDS=90000
```

Le récepteur doit accepter un POST JSON `{alert, state, environment, context}`.
Les webhooks Slack ou Teams directs exigent généralement un adaptateur de
format ; utiliser un relais d'incident ou un endpoint compatible. Tester la
configuration avec :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operations:evaluate-alerts
```

L'écran distingue « En cours », « Saine » et « Non notifiée ». Ne jamais placer
de secret dans l'URL du webhook si le fournisseur propose un en-tête ou un
relais de secrets ; la variable reste hors Git dans tous les cas.

## Sauvegardes et restauration canary

`backup-postgres.sh` enregistre `Backup/Succeeded` avec la taille du dump, ou
`Backup/Failed` lorsque PostgreSQL reste joignable pour écrire la preuve.
`verify-restore-canary.sh` applique la même règle pour `RestoreCanary`. Le
registre ne contient ni chemin local, ni commande, ni sortie brute, ni message
d'erreur.

Une panne empêchant aussi l'écriture PostgreSQL ne peut naturellement pas être
enregistrée dans ce registre ; elle doit être couverte par l'alerte externe du
job. Le back-office montrera alors une donnée périmée, pas un faux succès.

## Recette minimale

```bash
./implementation/scripts/run-tests.sh \
  tests/Unit/Operations/OperationsOverviewQueryHandlerTest.php \
  tests/Integration/Operations/OperatorOverviewTest.php \
  tests/Integration/Operations/OperationsAlertsTest.php \
  tests/Integration/Messaging/OutboxWorkerCommandTest.php \
  tests/Feature/Api/Subscriptions/RecurringBillingWebhookTest.php

make backup
make web-check
```

Vérifier sur `/backoffice` puis dans les deux registres : provenance et
fraîcheur visibles, séparation d'environnement, pagination serveur, absence de
références fournisseur et passage à `Stale` lorsque worker ou scheduler est
réellement arrêté.

## Reste à livrer pour fermer l'incrément 4

- historique PostgreSQL détaillé et backend de métriques adapté au scale ;
- réconciliation fournisseur ciblée, d'abord en lecture seule ;
- exercice reçu de chaque famille d'alerte sur l'environnement candidat ;
- preuve de sauvegarde hors site et canary sur la cible OCI.
