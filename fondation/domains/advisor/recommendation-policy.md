---
id: ADV-RECOMMENDATION-POLICY
title: Recommendation Policy 1.0
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - recommendation-engine.md
  - recommendation-score.md
  - priorities.md
  - actions.md
  - invariants.md
  - ../business-health/health-policy.md
---

# Recommendation Policy 1.0

`RecommendationPolicyVersion = 1.0.0` désigne les règles ci-dessous. Cette
politique est globale, immuable et non configurable par Workspace.

## Éligibilité de la source

Une BusinessHealthAssessment est éligible si :

- elle est la vue courante de son Workspace ;
- son `AssessmentStatus` vaut `Available` ;
- sa fiabilité vaut `Reliable` ou `Limited` ;
- sa HealthPolicyVersion est explicitement supportée ;
- son `AsOf` date d'au plus sept jours au moment de l'évaluation ;
- ses preuves et références contractuelles sont lisibles.

Une source insuffisante, obsolète ou historique produit une
RecommendationEvaluation sans génération. Advisor ne retombe jamais sur une
ancienne évaluation.

## Catalogue des règles

| RuleKey | Déclencheur | RecommendationKey | Validité | ActionDescriptor |
|---|---|---|---:|---|
| `CollectOverdueInvoices` | `OverdueExposureRisk` | `advisor.collect-overdue-invoices` | 7 jours | Billing / `OverdueInvoices` |
| `ReduceClientConcentration` | `ClientConcentrationRisk` | `advisor.reduce-client-concentration` | 30 jours | CRM / `NewOpportunity` |
| `RebuildCommercialPipeline` | `CommercialMomentumRisk` | `advisor.rebuild-commercial-pipeline` | 14 jours | CRM / `NewOpportunity` |
| `RestoreBillingMomentum` | `BillingMomentumRisk` | `advisor.restore-billing-momentum` | 14 jours | Billing / `RecentInvoices` |
| `AddressPrimaryAttention` | aucun risque du même facteur, HealthBand autre que Strong | `advisor.address-primary-attention` | 14 ou 30 jours | route déterminée par FactorKey |

Les quatre premières règles copient exactement la HealthRisk déclenchante.
`AddressPrimaryAttention` est un fallback unique : il exige une
PrimaryAttention, ne duplique jamais une règle de risque du même HealthFactor et
n'est pas créé pour `HealthBand = Strong`.

## Action du fallback

| FactorKey | Module | RouteKey | Capacité requise | Validité |
|---|---|---|---|---:|
| `CommercialMomentum` | CRM | `OpportunityPipeline` | `crm.opportunities.read` | 14 jours |
| `BillingMomentum` | Billing | `RecentInvoices` | `billing.invoices.read` | 14 jours |
| `ReceivablesDiscipline` | Billing | `OutstandingInvoices` | `billing.invoices.read` | 14 jours |
| `ClientDiversification` | CRM | `NewOpportunity` | `crm.opportunities.create` | 30 jours |

## Niveaux issus du risque

| RiskSeverity | ExpectedImpactLevel | Urgency |
|---|---|---|
| `Low` | `Moderate` | `ThisWeek` |
| `Medium` | `Significant` | `ThisWeek` |
| `High` | `Major` | `Today` |
| `Critical` | `Major` | `Immediate` |

Pour le fallback, `Stable`, `Watch`, `AtRisk` et `Critical` donnent
respectivement `Moderate`, `Significant`, `Major` et `Major`. Leur urgence vaut
respectivement `ThisWeek`, `ThisWeek`, `Today` et `Immediate`.

## Confiance

| AssessmentReliability | RecommendationConfidence |
|---|---|
| `Reliable` | `High` |
| `Limited` | `Moderate` |
| `Insufficient` | aucun candidat |

Advisor 1.0 ne produit pas de confiance `Low` : une preuve qui justifierait ce
niveau est rejetée plutôt que transformée en Recommendation.

## Effort

| RuleKey | EstimatedEffort |
|---|---|
| `CollectOverdueInvoices` | `Small` |
| `RestoreBillingMomentum` | `Small` |
| `ReduceClientConcentration` | `Medium` |
| `RebuildCommercialPipeline` | `Medium` |
| `AddressPrimaryAttention` | `Small` pour une vue, `Medium` pour NewOpportunity |

## Rang et limite de publication

Chaque candidat reçoit le score déterministe défini dans
[`recommendation-score.md`](recommendation-score.md). L'ordre total est :

```text
RecommendationPriority descending
RecommendationRankScore descending
ValidUntil ascending
RulePrecedence ascending
RecommendationKey ascending
```

La précédence des règles suit leur ordre dans le catalogue. Seuls les trois
premiers candidats sont publiés. Le premier devient `PrimaryRecommendation`.

## Déduplication et réaffirmation

```text
DeduplicationKey = WorkspaceId + RecommendationPolicyVersion + RecommendationKey

TriggerFingerprint = RuleKey + trigger kind + material severity/band
                     + ActionDescriptor + impact + urgency
                     + confidence + effort
```

- même fingerprint et Recommendation active : ajout d'une EvidenceRevision et
  `RecommendationReaffirmed` ;
- fingerprint matériellement différent : expiration `ContextChanged`, puis
  nouvelle Recommendation ;
- prédicat absent ou candidat sorti du top trois : expiration ;
- fingerprint terminal identique sans prédicat intermédiairement absent :
  candidat supprimé ;
- prédicat redevenu vrai après au moins une évaluation où il était absent, ou
  fingerprint matériellement nouveau : nouvelle Recommendation autorisée.

## Fenêtre de validité

`ValidUntil = SourceAsOf + RuleValidity`. Le temps de traitement ne prolonge
jamais artificiellement une recommandation ancienne. Un ValidUntil déjà atteint
empêche sa génération.
