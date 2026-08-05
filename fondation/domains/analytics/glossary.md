---
id: ANL-GLOSSARY
title: Analytics Glossary
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - metric-catalog.md
  - model.md
  - value-objects.md
  - ../../language/glossary.md
---

# Glossaire Analytics

| Terme | Définition |
|---|---|
| `AnalyticsFact` | observation normalisée et immuable d'une révision source |
| `MetricDefinition` | formule, unité, sources et politiques versionnées d'une métrique |
| `MetricSeries` | suite d'observations partageant définition, génération et dimensions |
| `MetricObservation` | valeur calculée pour une période ou un instant précis |
| `ProjectionGeneration` | ensemble cohérent de séries produit par une version de projection |
| `AnalyticsSnapshot` | ensemble immuable de métriques cohérentes publié à un consommateur |
| `DataFreshness` | retard et watermarks décrivant l'actualité de la projection |
| `DataCompleteness` | couverture des données requises par une formule |
| `ObservationPeriod` | période et calendrier exacts d'une observation |
| `DimensionSet` | dimensions bornées autorisées par une MetricDefinition |
| `ReportingCalendar` | fuseau et règles civiles versionnés du Workspace |
| `SourceWatermark` | dernière position source durablement projetée |

## Termes à éviter

- KPI sans MetricDefinition explicite ;
- Revenue pour `NetInvoicedAmount` ou `CollectedAmount` ;
- Cash Balance ou Treasury sans source bancaire ;
- Forecast pour `PipelineAmount` non pondéré ;
- Real Time sans garantie de fraîcheur mesurée ;
- Empty ou Zero pour `NoData`.
