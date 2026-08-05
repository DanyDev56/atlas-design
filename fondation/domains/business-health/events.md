---
id: BHL-EVENTS
title: Business Health Domain Events
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

Business Health publie un fait durable après chaque évaluation. Il ne publie pas
un événement séparé par facteur, risque ou variation de score.

## Enveloppe commune

| Champ | Description |
|---|---|
| `EventId` | identifiant unique de déduplication |
| `EventName` | nom canonique |
| `SchemaVersion` | version du contrat |
| `OccurredAt` | instant du fait |
| `AggregateType`, `AggregateId` | BusinessHealthAssessment concernée |
| `AggregateVersion` | révision après commit, toujours initiale en 1.0 |
| `WorkspaceId` | frontière d'isolation |
| `CorrelationId`, `CausationId` | chaîne depuis le snapshot Analytics |
| `ActorReference` | workload auditable minimal |
| `Data` | charge utile minimale sans preuve détaillée |

## Catalogue

| Événement | Producteur | Visibilité | Fait minimum |
|---|---|---|---|
| `BusinessHealthAssessed` | `EvaluateBusinessHealth` | public | Un snapshot a été évalué par une politique déterminée. |

## Contrat public

`BusinessHealthAssessed` contient seulement :

```text
BusinessHealthAssessmentId
AnalyticsSnapshotId
HealthPolicyVersion
AsOf
AssessmentStatus
AssessmentReliability
OverallScore?
HealthBand?
HealthTrend
PrimaryAttentionFactorKey?
RiskSeveritySummary
```

Advisor relit l'évaluation exacte avec
`business-health.assessments.consume`. Les facteurs, preuves et explications ne
sont pas dupliqués dans l'événement.

## Publication

- évaluation, événement et outbox sont atomiques ;
- les consommateurs dédupliquent `EventId` ;
- l'ordre est propre à chaque BusinessHealthAssessment immuable ;
- la projection courante est indépendante de l'ordre de livraison des événements ;
- une évaluation `InsufficientData` publie aussi l'événement ;
- un traitement refusé ne publie aucun événement de réussite.
