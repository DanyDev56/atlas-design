---
id: ANL-EVENTS
title: Analytics Domain Events
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - invariants.md
  - processors/README.md
  - integrations.md
---

# Domain Events

Les événements Analytics décrivent l'avancement durable des faits, projections
et publications. Une variation ordinaire de chaque cellule n'est pas publiée.

## Enveloppe commune

| Champ | Description |
|---|---|
| `EventId` | identifiant unique de déduplication |
| `EventName` | nom canonique |
| `SchemaVersion` | version du contrat |
| `OccurredAt` | instant du fait |
| `AggregateType`, `AggregateId` | racine Analytics concernée |
| `AggregateVersion` | révision après commit |
| `WorkspaceId` | frontière d'isolation |
| `CorrelationId`, `CausationId` | chaîne de traitement |
| `ActorReference` | workload auditable minimal |
| `Data` | charge utile minimale sans valeur personnelle |

## Catalogue

| Événement | Producteur | Visibilité | Fait minimum |
|---|---|---|---|
| `AnalyticsFactRecorded` | `IngestSourceFact` | interne | Une révision source supportée a été normalisée. |
| `AnalyticsProjectionRebuildStarted` | `StartAnalyticsProjectionRebuild` | interne | Une génération isolée est en construction. |
| `AnalyticsProjectionRebuilt` | `CompleteAnalyticsProjectionRebuild` | interne | La génération validée est devenue active. |
| `AnalyticsProjectionRebuildFailed` | `CompleteAnalyticsProjectionRebuild` | interne | La génération a échoué à la validation sans remplacer l'active. |
| `AnalyticsSnapshotPublished` | `PublishAnalyticsSnapshot` | public | Un snapshot cohérent et immuable est disponible. |

## Contrat public de snapshot

`AnalyticsSnapshotPublished` contient seulement :

```text
AnalyticsSnapshotId
SnapshotProfileKey
SnapshotProfileVersion
AsOf
GenerationId
DefinitionSetVersion
ReportingCalendarVersion
FreshnessSummary
CompletenessSummary
```

Les valeurs sont relues par `AnalyticsSnapshotId` avec
`analytics.snapshots.consume`. Elles ne sont pas dupliquées dans l'événement.

## Publication

- état, événements et outbox sont atomiques ;
- les consommateurs dédupliquent `EventId` ;
- l'ordre suit la révision de chaque agrégat Analytics ;
- une commande refusée ne publie aucun événement de réussite ;
- une reconstruction ne simule jamais de nouveaux événements CRM ou Billing.
