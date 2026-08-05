---
id: BHL-PUBLIC-CONTRACT
title: Business Health Public Contract
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - health-policy.md
  - invariants.md
  - permissions.md
  - events.md
  - processors/README.md
---

# Contrat public Business Health

Le contrat exprime des lectures et une intention système indépendantes du
transport. Business Health 1.0 n'expose aucune commande métier humaine.

## Lectures utilisateur

```text
getCurrentBusinessHealth(workspaceId)
→ BusinessHealthAssessment | AssessmentUnavailable

getBusinessHealthAssessment(workspaceId, businessHealthAssessmentId)
→ BusinessHealthAssessment

listBusinessHealthAssessments(workspaceId, asOfRange?, cursor?, limit?)
→ BusinessHealthAssessmentSummaryPage

getBusinessHealthExplanation(workspaceId, businessHealthAssessmentId)
→ BusinessHealthExplanation
```

Toutes exigent `business-health.assessments.read`. Une évaluation contient :

```text
BusinessHealthAssessmentId
AnalyticsSnapshotReference
HealthPolicyVersion
AssessmentCurrency?
AssessmentStatus
AssessmentReliability
OverallScore?
HealthBand?
HealthTrend
BaselineAssessmentId?
HealthFactors[4]
HealthRisks[]
PrimaryAttention?
InsufficiencyReasons[]
AssessedAt
```

`getCurrentBusinessHealth` résout l'ActiveHealthPolicyVersion puis la projection
courante de cette version. Il ne mélange jamais deux politiques.

L'historique est trié par `AsOf` décroissant puis
`BusinessHealthAssessmentId`, paginé par curseur stable. Une réponse
`InsufficientData` reste un résultat normal, pas une erreur de transport.

## Contrat fourni à Advisor

```text
getBusinessHealthAssessment(workspaceId, businessHealthAssessmentId)
→ BusinessHealthAssessment

getLatestBusinessHealthAssessment(workspaceId, minimumAsOf?,
                                  acceptedPolicyVersions?)
→ BusinessHealthAssessment | AssessmentUnavailable
```

La lecture machine exige `business-health.assessments.consume`. Advisor vérifie
status, fiabilité, politique et AsOf avant de générer une Recommendation.

## Intention système interne

```text
evaluateBusinessHealth(workspaceId, analyticsSnapshotId,
                       analyticsSnapshotPublishedEventId,
                       healthPolicyVersion,
                       evaluateBusinessHealthRequestId)
```

La fiche [`EvaluateBusinessHealth`](processors/EvaluateBusinessHealth.md) est
normative.

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | identifiant, plage, limite ou version invalide |
| `Unauthenticated` | principal ou workload absent/invalide |
| `Unauthorized` | permission ou capacité absente |
| `NotFound` | évaluation, snapshot ou politique absent/masqué |
| `UnsupportedSnapshotProfile` | profil Analytics incompatible avec la politique |
| `UnsupportedPolicyVersion` | HealthPolicyVersion inconnue ou retirée avant usage |
| `SourceContractMismatch` | contrat Analytics incompatible |
| `Conflict` | unicité ou idempotence incompatible |
| `TemporarilyUnavailable` | contrat source nécessaire temporairement indisponible |

La couverture insuffisante, une comparaison absente ou plusieurs devises
supportées par Analytics produisent une BusinessHealthAssessment
`InsufficientData` explicite ; elles ne sont pas masquées comme une erreur API.

## Versioning

Un changement de seuil ou de poids crée une nouvelle HealthPolicyVersion. Un
changement de sens d'un champ, d'une enum, d'une permission ou d'un événement
exige une version contractuelle explicite.
