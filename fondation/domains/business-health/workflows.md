---
id: BHL-WORKFLOWS
title: Business Health Workflows
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - health-policy.md
  - invariants.md
  - processors/README.md
  - events.md
  - integrations.md
---

# Workflows

## Évaluation nominale

1. Business Health reçoit `AnalyticsSnapshotPublished`.
2. `EvaluateBusinessHealth` authentifie l'enveloppe et déduplique EventId.
3. Il dérive `EvaluateBusinessHealthRequestId` du snapshot et de la politique.
4. Il relit le snapshot exact avec `analytics.snapshots.consume`.
5. Il vérifie profil, Workspace, contrat, fraîcheur et devises.
6. Il fige les AssessmentEvidence et évalue les composants puis facteurs.
7. Il calcule score, bande, fiabilité, risques et PrimaryAttention.
8. Il résout la baseline comparable et les tendances.
9. Il crée l'évaluation et `BusinessHealthAssessed` dans la même transaction.
10. Il avance la projection courante de cette HealthPolicyVersion si son tuple
    d'ordre est supérieur.

## Données insuffisantes

Si le snapshot est authentique mais sa couverture ne permet pas un OverallScore :

1. les preuves disponibles et leurs limites sont conservées ;
2. les quatre facteurs décrivent leur état et leur couverture ;
3. l'évaluation vaut `InsufficientData` et la fiabilité `Insufficient` ;
4. score, bande et PrimaryAttention restent absents ;
5. `BusinessHealthAssessed` est publié normalement ;
6. la vue courante avance si cette évaluation est la plus récente.

Cette branche évite qu'un ancien score semble encore actuel.

## Événement dupliqué ou livraison concurrente

- un EventId déjà traité retourne le résultat initial ;
- la clé naturelle snapshot/politique empêche deux évaluations distinctes ;
- une réutilisation incompatible du RequestId échoue ;
- deux traitements concurrents convergent vers la même évaluation ;
- la projection courante compare atomiquement son tuple d'ordre.

## Snapshot tardif

Un snapshot valide plus ancien crée son évaluation historique. Il peut servir de
baseline à une évaluation future, mais ne remplace pas une CurrentBusinessHealth
plus récente. L'ordre de livraison ne change donc pas le résultat courant.

## Nouvelle politique

Une HealthPolicyVersion nouvelle n'altère aucune évaluation. Pour un snapshot
donné, elle produit un nouvel identifiant, une nouvelle racine et une projection
courante distincte. Les tendances ne traversent jamais les versions de politique.

Le pointeur ActiveHealthPolicyVersion bascule seulement lorsque le snapshot
courant possède une évaluation selon la nouvelle version. Un backfill historique
est une opération contrôlée future, pas une commande utilisateur 1.0.

## Lecture utilisateur

Après autorisation, l'API retourne l'évaluation courante avec son AsOf, sa
fiabilité et son explication. L'interface :

- affiche `Unknown` plutôt que Stable sans baseline ;
- montre les facteurs indisponibles et la couverture effective ;
- distingue risques observés et données insuffisantes ;
- présente les seuils comme une lecture Atlas, pas comme une norme universelle.

## Consommation par Advisor

Advisor reçoit l'événement puis relit l'évaluation exacte. Une évaluation
`InsufficientData` ou trop ancienne ne produit aucune Recommendation en
politique Advisor 1.0 : elle invalide l'overview courant si elle est la source
la plus récente. Le Dashboard peut proposer les saisies ou imports manquants
comme aide d'onboarding, jamais comme Recommendation Advisor.
