---
id: ANL-RELATIONSHIPS
title: Analytics Relationships
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - metric-catalog.md
  - integrations.md
---

# Relations

## Cardinalités internes

```text
SourceFactStream     1 -------- 0..* AnalyticsFact
MetricDefinition     1 -------- 0..* MetricSeries
ProjectionGeneration 1 -------- 0..* MetricSeries
MetricSeries         1 -------- 0..* MetricObservation
AnalyticsSnapshot    1 -------- 1..* SnapshotMetric
```

## Domaines sources

```text
CRM Event -------- signal --------> Analytics
CRM Fact -------- exact version --> AnalyticsFact

Billing Event ---- signal --------> Analytics
Billing Fact ----- exact version --> AnalyticsFact
```

Analytics conserve les identifiants nécessaires à la causalité mais jamais les
agrégats sources. Une suppression de projection ne supprime aucun fait CRM ou
Billing.

## Workspace

`WorkspaceId` isole faits, séries et snapshots. `ReportingCalendar` dérive des
préférences Workspace versionnées. Analytics ne copie ni identité légale, ni
profil d'affichage.

## Business Health

```text
AnalyticsSnapshotPublished
  -> Business Health reads exact snapshot
  -> BusinessHealthAssessed
```

Business Health ne relit pas les tables de projection privées et ne redéfinit
pas les formules Analytics.

## ClientId

`ClientId` est une dimension technique autorisée pour la concentration. Le nom
courant reste résolu par CRM au moment de l'affichage autorisé ; il n'entre pas
dans les faits ou séries Analytics.
