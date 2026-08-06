---
id: GOVERNANCE-010
title: Cadence des évaluations et activation des politiques
status: In Review
owner: Product and Engineering
version: 1.0
last_updated: 2026-08-06
---

# Cadence des évaluations et activation des politiques

## Déclencheurs MVP

La cadence est pilotée par événements, avec un filet de sécurité planifié. Elle
n'est pas une règle métier cachée dans un cron.

| Évaluation | Déclencheur principal | Filet de sécurité | Anti-bruit |
|---|---|---|---|
| Analytics Snapshot | changement de fait source | quotidien à 04:00 UTC | coalescence 5 min par Workspace |
| Business Health | snapshot compatible publié | quotidien après snapshot | un assessment par snapshot et version |
| Advisor | assessment courant publié ou action utilisateur | quotidien après Health | rang stable et top 3 |
| Notifications | signal Advisor | reprise toutes les 5 min | préférences, fenêtre et déduplication |

Un Workspace sans changement ne reçoit pas artificiellement un nouvel état. Le
filet de sécurité répare les événements manqués et réévalue seulement si la
fraîcheur contractuelle l'exige. Tous les horaires stockés sont UTC ; la fenêtre
de remise utilise le fuseau du Workspace.

## Cycle de vie d'une politique

`Draft → Validated → Published → Active → Retired`

- une version publiée est immuable et adressable en lecture ;
- une seule version est active par type de politique ;
- `Retired` interdit les nouveaux calculs mais conserve les lectures historiques ;
- changer un seuil, poids, règle ou template sémantique crée une version ;
- la version effectivement utilisée est persistée sur chaque résultat.

## Activation en deux phases

1. **Prepare** : publier la version, valider schéma et contrats, exécuter corpus
   de référence, canary interne puis évaluer le snapshot courant de chaque
   Workspace candidat sans modifier ses lectures courantes.
2. **Activate** : comparer couverture et écarts, obtenir l'approbation Product +
   Engineering, puis basculer atomiquement le pointeur actif. Les nouveaux
   événements capturent la nouvelle version au début du traitement.

L'activation Business Health précède l'activation d'une politique Advisor qui la
supporte. Notifications déclare explicitement les versions Advisor acceptées.
Une combinaison non supportée échoue fermement et devient observable.

## Rollback et reprise

Le rollback rebascule le pointeur vers une version publiée compatible ; il ne
réécrit ni événements ni résultats. Avant la bascule, le snapshot courant doit
avoir été évalué avec la version cible. Les traitements en vol terminent avec la
version capturée ; leur sortie historique reste valide, mais seule la version
active nourrit la projection courante.

Chaque opération utilise `PolicyActivationId`, `ExpectedActiveVersion` et
`RequestId`. Un conflit de concurrence n'est jamais résolu par dernier écrivain.

## Preuves obligatoires

- rapport du corpus de référence et écarts attendus ;
- compatibilité des contrats entrants et sortants ;
- métriques de canary, taux d'erreur et couverture ;
- identité des approbateurs, instant d'activation et motif ;
- procédure et exercice de rollback ;
- journal sans données personnelles.

## Quality gate

Une activation est bloquée si un résultat manque sa version, si un consommateur
ne supporte pas la combinaison, si le snapshot courant n'est pas pré-calculé, ou
si le rollback n'a pas été validé.
