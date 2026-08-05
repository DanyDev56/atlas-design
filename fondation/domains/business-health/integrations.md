---
id: BHL-INTEGRATIONS
title: Business Health Integrations
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - api.md
  - events.md
  - workflows.md
  - ../analytics/api.md
  - ../analytics/events.md
  - ../workspace/api.md
  - ../identity/api.md
---

# Intégrations

## Identity

Les lectures humaines demandent `business-health.assessments.read`. Les
processeurs et Advisor utilisent exclusivement les capacités SystemActorOnly
prévues. Business Health ne lit ni Membership, ni Role, ni Session dans le
stockage Identity.

## Workspace

Business Health consomme :

```text
getWorkspaceAccessContext(workspaceId)
```

Le contrat confirme la frontière et l'état d'accès. Business Health ne copie ni
profil commercial, ni identité de facturation, ni préférence d'affichage.

## Analytics

Business Health consomme :

```text
AnalyticsSnapshotPublished

getAnalyticsSnapshot(workspaceId, analyticsSnapshotId)
→ AnalyticsSnapshot
```

La lecture exacte exige `analytics.snapshots.consume`. L'événement déclenche le
traitement mais ne remplace pas le contrat de valeur. Business Health vérifie :

- `SnapshotProfileKey = BusinessHealthBaselineV1` et sa version ;
- métriques, définition, période, devise et comparaison attendues ;
- complétude, fraîcheur, génération et watermarks publiés ;
- cohérence entre l'événement et le snapshot relu.

Il ne lit ni AnalyticsFact, ni MetricSeries, ni stockage Analytics privé.

## Advisor

Advisor consomme :

```text
BusinessHealthAssessed

getBusinessHealthAssessment(workspaceId, businessHealthAssessmentId)
→ BusinessHealthAssessment
```

La lecture exacte exige `business-health.assessments.consume`. Advisor peut
transformer une zone d'attention ou un risque en Recommendation, mais ne modifie
pas l'évaluation et conserve ses références de preuve.

## CRM, Billing et Notifications

Business Health ne dépend d'aucun contrat CRM ou Billing direct. En 1.0,
Notifications reçoit la décision en aval via Advisor et ne consomme aucun
BusinessHealthAssessed direct. Business Health ne choisit ni canal,
destinataire ou moment d'envoi.

## Garanties

- aucune base de données partagée ;
- lecture exacte après signal minimal ;
- timeouts, retries avec backoff et dead-letter observable ;
- déduplication par EventId et RequestId déterministe ;
- aucune indisponibilité ne devient un score conservé ou un zéro ;
- aucune commande envoyée vers un domaine source ou consommateur.
