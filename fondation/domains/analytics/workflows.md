---
id: ANL-WORKFLOWS
title: Analytics Workflows
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - metric-catalog.md
  - invariants.md
  - processors/README.md
  - events.md
  - integrations.md
---

# Workflows

## Ingestion nominale

1. Analytics reçoit un événement CRM ou Billing supporté.
2. `IngestSourceFact` authentifie l'enveloppe et déduplique `SourceEventId`.
3. Le processeur relit le fait minimal à `AggregateVersion`.
4. Il enregistre l'AnalyticsFact immuable et `AnalyticsFactRecorded`.
5. Les projecteurs recalculent les cellules touchées dans la génération active.
6. Le checkpoint avance seulement après leur commit durable.

Une même transaction Billing peut produire plusieurs événements à la même
version. Chaque EventId utile reste traçable sans compter deux fois la même
contribution de révision.

## Événement dupliqué, en retard ou manquant

- un `SourceEventId` identique retourne le résultat initial ;
- une révision égale avec un autre événement supporté est autorisée ;
- une révision antérieure déjà couverte est ignorée après vérification ;
- un trou de version suspend le stream concerné, relit les versions disponibles
  ou déclenche un rebuild borné ;
- les autres Workspaces et streams continuent indépendamment.

Le retard devient visible dans `DataFreshness`. Il n'est jamais masqué par un
timestamp de calcul récent.

## Correction et reversal

Une correction Opportunity ou un reversal Payment arrive comme nouveau fait.
Le projecteur :

1. conserve l'ancien AnalyticsFact ;
2. remplace la contribution courante de l'entité source ;
3. recalcule tous les buckets affectés par l'instant métier d'origine ;
4. met à jour échantillons, couvertures et explications ;
5. ne touche à aucun AnalyticsSnapshot déjà publié.

## Rebuild sans interruption

1. `StartAnalyticsProjectionRebuild` crée une génération `Building` et fige son
   scope, ses définitions, son calendrier et ses watermarks cibles.
2. Les faits sont rejoués dans cette génération isolée.
3. Le traitement rattrape ensuite les événements survenus pendant le replay.
4. `CompleteAnalyticsProjectionRebuild` vérifie checkpoints, formules,
   cardinalités, devises et contrôles de comparaison.
5. Le pointeur actif bascule atomiquement ; l'ancienne génération devient
   `Superseded`.

Un échec marque la génération `Failed` et conserve l'active précédente.

## Publication vers Business Health

1. le scheduler demande `PublishAnalyticsSnapshot` avec un profil versionné ;
2. Analytics résout toutes les métriques obligatoires au même `AsOf` ;
3. il refuse une donnée insuffisante, trop ancienne ou issue d'une autre
   génération ;
4. le snapshot immuable et `AnalyticsSnapshotPublished` sont commis ;
5. Business Health relit exactement ce snapshot.

Une nouvelle publication supplante logiquement l'ancienne sans la modifier.

## Lecture utilisateur

Après autorisation, l'API résout la définition et la génération active. Elle
retourne la valeur avec période, devise, échantillon, couverture, fraîcheur et
explication. L'interface peut masquer un graphique, mais ne remplace jamais
`NoData` ou `Unavailable` par zéro.

## Changement de préférences Workspace

Un changement de fuseau ou calendrier construit une nouvelle génération pour
les buckets civils concernés. Les métriques `PointInTime` et les instants UTC
restent fondés sur les mêmes faits. Une restriction Workspace bloque les
lectures humaines mais n'efface ni ne mélange les projections.
