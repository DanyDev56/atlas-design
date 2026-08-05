---
id: BHL-AGGREGATES
title: Business Health Aggregates
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - relationships.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Vue d'ensemble

| Agrégat | Racine | Cohérence garantie |
|---|---|---|
| Business Health Assessment | `BusinessHealthAssessment` | snapshot, politique, facteurs, score, risques et explication cohérents |

`HealthPolicy` est un catalogue global versionné. `CurrentBusinessHealth` est une
projection de lecture et non un agrégat métier.

## BusinessHealthAssessment

La clé naturelle est :

```text
(WorkspaceId, AnalyticsSnapshotId, HealthPolicyVersion)
```

La racine est créée en une seule transaction. Elle :

1. authentifie la cause Analytics et charge le snapshot exact ;
2. vérifie profil, devise, fraîcheur et politique ;
3. fige les AssessmentEvidence ;
4. calcule chaque composant puis chaque HealthFactor ;
5. calcule OverallScore, HealthBand et fiabilité si la couverture le permet ;
6. résout la baseline compatible, les tendances et la PrimaryAttention ;
7. détecte les HealthRisk étayés ;
8. publie `BusinessHealthAssessed` atomiquement.

La racine ne reçoit ensuite aucune mutation. Un rejeu identique retourne la
même évaluation.

## Concurrence et ordre courant

Plusieurs snapshots ou versions de politique peuvent être évalués en parallèle.
L'unicité naturelle empêche les doublons. Chaque projection courante est bornée
par `(WorkspaceId, HealthPolicyVersion)` et compare le tuple
`(AsOf, SourcePublishedAt, BusinessHealthAssessmentId)` dans une opération
atomique et monotone.

Une évaluation plus ancienne reste consultable mais ne remplace pas une vue
courante plus récente. Une évaluation `InsufficientData` plus récente devient
la vue courante : Atlas doit montrer la perte de lisibilité, pas conserver
silencieusement un score périmé.

## Réévaluation

Changer une règle exige une nouvelle HealthPolicyVersion. La réévaluation d'un
ancien snapshot crée une nouvelle racine et conserve la version antérieure.
Elle n'affecte que la projection de sa propre politique. Le pointeur global de
politique active bascule seulement après évaluation du snapshot courant selon la
nouvelle version. Business Health n'édite ni le snapshot, ni la politique déjà
publiée.
