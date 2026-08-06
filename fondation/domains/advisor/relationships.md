---
id: ADV-RELATIONSHIPS
title: Advisor Relationships
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - aggregates.md
  - integrations.md
  - ../business-health/api.md
---

# Relations

## Cardinalités internes

```text
RecommendationPolicy       1 -------- 0..* RecommendationEvaluation
RecommendationEvaluation   1 -------- 5    CandidateDecision
CandidateDecision          1 -------- 0..1 Recommendation
Recommendation             1 -------- 1..* RecommendationEvidenceRevision
Recommendation             1 -------- 1    RecommendationAction
RecommendationRuleState    1 -------- 0..1 active Recommendation
AdvisorOverview            1 -------- 0..3 generated Recommendation
```

## Business Health

```text
BusinessHealthAssessed
  -> EvaluateRecommendations
  -> getBusinessHealthAssessment(exact id)
  -> RecommendationEvaluation
```

Advisor copie les observations minimales et références nécessaires dans une
EvidenceRevision. Il ne modifie pas la BusinessHealthAssessment et ne traverse
pas ses références pour lire Analytics.

## CRM et Billing

RecommendationAction référence un Module, une RouteKey et une capacité publique.
Elle ne référence aucun stockage et ne constitue aucune commande.

```text
User opens RecommendationAction
  -> client resolves allowlisted route
  -> target domain authorizes user
  -> user may submit target domain command
```

## Notifications

Notifications consomme uniquement `AdvisorOverviewChanged` puis relit la version
exacte avec une capacité système. Évaluations et mutations terminales passent
par cette convergence unique ; Advisor ne choisit ni canal, ni destinataire, ni
cadence.

## Identity et Workspace

`WorkspaceId` isole toutes les racines. Identity résout les capacités Advisor et
celles requises par l'action. Workspace fournit l'état d'accès public ; Advisor
ne copie ni Membership, Role ou profil d'activité.
