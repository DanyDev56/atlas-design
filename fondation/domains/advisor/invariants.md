---
id: ADV-INVARIANTS
title: Advisor Invariants
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - model.md
  - recommendation-policy.md
  - recommendation-lifecycle.md
  - aggregates.md
  - value-objects.md
  - commands/README.md
  - processors/README.md
---

# Invariants

## Isolation, source et politique

| ID | Règle |
|---|---|
| `ADV-INV-001` | Toute évaluation, Recommendation, preuve, permission et référence appartient à un seul WorkspaceId. |
| `ADV-INV-002` | Les identifiants Advisor sont stables, uniques et jamais réattribués. |
| `ADV-INV-003` | Une RecommendationPolicy publiée est globale, immuable et versionnée ; un Workspace ne change aucune règle 1.0. |
| `ADV-INV-004` | Toute RecommendationEvaluation référence une BusinessHealthAssessment exacte, authentique et du même Workspace. |
| `ADV-INV-005` | Seule la BusinessHealthAssessment courante de la HealthPolicyVersion supportée peut modifier les recommandations actives. |
| `ADV-INV-006` | Une source doit être Available, Reliable ou Limited, et âgée d'au plus sept jours. |
| `ADV-INV-007` | Une source inéligible produit un rapport explicite sans RecommendationGenerated ni fallback ancien. |
| `ADV-INV-008` | Advisor 1.0 ne lit directement ni Analytics, CRM, Billing, ni leur stockage privé. |
| `ADV-INV-009` | Chaque EvidenceRevision conserve la source exacte, les observations copiées et les EvidenceReferences transitives utilisées. |
| `ADV-INV-010` | Il existe au plus une RecommendationEvaluation par `(WorkspaceId, BusinessHealthAssessmentId, RecommendationPolicyVersion)`. |

## Génération et priorité

| ID | Règle |
|---|---|
| `ADV-INV-011` | Chaque source est évaluée contre exactement les cinq RuleKey de RecommendationPolicy 1.0. |
| `ADV-INV-012` | À entrées, politique et horloge métier identiques, les CandidateDecision sont identiques. |
| `ADV-INV-013` | Un Workspace possède au plus trois Recommendation Generated et AdvisorOverview en expose au plus trois. |
| `ADV-INV-014` | Il existe au plus une Recommendation Generated par DeduplicationKey. |
| `ADV-INV-015` | Si AdvisorOverview n'est pas vide, sa première entrée est l'unique PrimaryRecommendation. |
| `ADV-INV-016` | TriggerFingerprint et DeduplicationKey utilisent uniquement leurs champs canoniques versionnés. |
| `ADV-INV-017` | Un fingerprint terminal identique n'est pas régénéré tant que son prédicat n'a pas disparu ou matériellement changé. |
| `ADV-INV-018` | La disparition d'un prédicat est matérialisée par une évaluation plus récente avant d'autoriser un nouvel épisode identique. |
| `ADV-INV-019` | RecommendationRankScore applique des décimaux exacts et un unique arrondi moitié vers le haut. |
| `ADV-INV-020` | RecommendationPriority découle exclusivement du score et des seuils de la même RecommendationPolicyVersion. |
| `ADV-INV-021` | Une donnée absente, inconnue ou insuffisante n'est jamais remplacée par une valeur favorable ou défavorable inventée. |

## Contenu, action et explication

| ID | Règle |
|---|---|
| `ADV-INV-022` | Toute Recommendation possède exactement une RecommendationAction principale. |
| `ADV-INV-023` | ActionKind, Module, RouteKey, paramètres et capacités appartiennent à l'allowlist de la politique. |
| `ADV-INV-024` | L'action expose les RequiredCapabilities exactes sans les accorder ni les contourner. |
| `ADV-INV-025` | Advisor n'appelle, ne relaie et n'exécute aucune commande CRM, Billing, Workspace ou Business Health. |
| `ADV-INV-026` | Toute Recommendation explique WhyNow, observations, règle, rang, confiance, impact et limites. |
| `ADV-INV-027` | RecommendationConfidence décrit la qualité de justification et n'est jamais formulée comme probabilité. |
| `ADV-INV-028` | ExpectedImpact reste qualitatif en 1.0 ; aucun gain monétaire, causal ou temporel chiffré n'est promis. |
| `ADV-INV-029` | Une EvidenceRevision est immuable, numérotée sans trou et ne supprime aucune révision antérieure. |
| `ADV-INV-030` | RecommendationReaffirmed exige le même TriggerFingerprint et une source strictement plus récente. |
| `ADV-INV-031` | Une réaffirmation calcule ValidUntil depuis SourceAsOf et ne prolonge jamais une source ancienne depuis l'heure de traitement. |

## Cycle de vie

| ID | Règle |
|---|---|
| `ADV-INV-032` | RecommendationStatus appartient à Generated, Completed, Dismissed ou Expired. |
| `ADV-INV-033` | Generated est le seul état actif et le seul état réaffirmable. |
| `ADV-INV-034` | Completed, Dismissed et Expired sont terminaux, exclusifs et jamais réactivés. |
| `ADV-INV-035` | Completed exige UserConfirmedActionCompleted et ne prouve ni mutation source ni outcome. |
| `ADV-INV-036` | Dismissed exige un DismissalReason structuré ; aucun texte libre n'est accepté en 1.0. |
| `ADV-INV-037` | Expired exige un ValidUntil atteint ou une ExpirationReason causée par une source, un rang ou une politique plus récente. |
| `ADV-INV-038` | Displayed, Opened, Clicked et Ignored ne sont ni états ni Domain Events Advisor. |
| `ADV-INV-039` | Toutes les transitions et EvidenceRevisions restent historisées. |

## Ordre, autorité et fiabilité

| ID | Règle |
|---|---|
| `ADV-INV-040` | Le dernier SourceOrder traité ne régresse jamais pour une même politique ; AdvisorOverviewVersion augmente à chaque convergence Eligible appliquée et ne régresse jamais dans un Workspace. |
| `ADV-INV-041` | Une source historique est enregistrée mais ne réaffirme, ne génère et n'expire aucune Recommendation courante. |
| `ADV-INV-042` | Une nouvelle ActiveRecommendationPolicyVersion n'est activée qu'après évaluation courante et expiration explicite de l'ancienne politique. |
| `ADV-INV-043` | Lecture, completion et dismissal exigent un acteur actif, le même Workspace et la capacité Advisor exacte. |
| `ADV-INV-044` | Évaluation, expiration et consommation machine exigent leur capacité SystemActorOnly bornée. |
| `ADV-INV-045` | Toute mutation d'une Recommendation existante exige ExpectedRevision ; une révision obsolète ne gagne jamais. |
| `ADV-INV-046` | Chaque commande et processeur possède un RequestId ; rejeu identique retourne le résultat initial et réutilisation incompatible échoue. |
| `ADV-INV-047` | Mutation d'agrégat, Domain Events et outbox sont commis atomiquement. |
| `ADV-INV-048` | Les consommateurs dédupliquent EventId et les process managers reprennent sans doubler une décision. |
| `ADV-INV-049` | Un Workspace restreint bloque les lectures et décisions humaines sans effacer l'historique ni interrompre l'expiration de sécurité. |
| `ADV-INV-050` | Advisor 1.0 ne présente aucun texte génératif, benchmark, prédiction ou décision autonome comme règle canonique. |
