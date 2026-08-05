---
id: ANL-INVARIANTS
title: Analytics Invariants
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - metric-catalog.md
  - aggregates.md
  - value-objects.md
  - processors/README.md
---

# Invariants

## Isolation et faits sources

| ID | Règle |
|---|---|
| `ANL-INV-001` | Tout fait, série, génération, snapshot, permission et référence appartient à un seul `WorkspaceId`. |
| `ANL-INV-002` | Les identifiants Analytics sont stables, uniques et jamais réattribués. |
| `ANL-INV-003` | Un fait provient d'un événement authentique et d'un contrat source supporté ; Analytics n'accepte aucune valeur métier libre. |
| `ANL-INV-004` | Un `SourceEventId` est enregistré au plus une fois ; son rejeu identique est sans effet et un contenu différent est refusé. |
| `ANL-INV-005` | Les versions d'un agrégat source ne régressent jamais ; un trou est attendu, relu ou reconstruit, jamais supposé. |
| `ANL-INV-006` | Plusieurs événements supportés d'une même transaction peuvent partager une version source ; leur EventId reste distinct et leur contribution commune n'est jamais comptée deux fois. |
| `ANL-INV-007` | Un AnalyticsFact est immuable ; correction, changement d'état ou reversal produit un fait source versionné ultérieur. |
| `ANL-INV-008` | Les faits et dimensions excluent données personnelles, notes libres, adresses, preuves publiques et contenu documentaire. |

## Définitions et calculs

| ID | Règle |
|---|---|
| `ANL-INV-009` | Une MetricDefinition publiée est immuable ; tout changement de sens crée une nouvelle version. |
| `ANL-INV-010` | Une MetricKey ou version dépréciée n'est jamais réutilisée avec une autre formule. |
| `ANL-INV-011` | Une observation référence exactement la définition, la génération et le calendrier qui l'ont produite. |
| `ANL-INV-012` | Les calculs monétaires et ratios utilisent une représentation exacte et une politique d'arrondi déterministe. |
| `ANL-INV-013` | Des montants de devises différentes ne sont jamais additionnés, comparés ou convertis implicitement. |
| `ANL-INV-014` | `NoData`, `Unavailable` et zéro sont trois résultats distincts. |
| `ANL-INV-015` | Un ratio exige un dénominateur positif et expose numérateur, dénominateur et échantillon. |
| `ANL-INV-016` | Une moyenne expose son échantillon et n'agrège jamais des moyennes pré-calculées sans pondération canonique. |
| `ANL-INV-017` | Toute période est non ambiguë, semi-ouverte pour un flux et liée à un fuseau et calendrier versionnés. |
| `ANL-INV-018` | Stock, flux, ratio et durée ne peuvent pas être agrégés avec une opération incompatible avec leur nature. |
| `ANL-INV-019` | Seules les dimensions déclarées par la définition sont acceptées ; aucune dimension libre ou personnelle n'est créée. |
| `ANL-INV-020` | Les corrections et reversals recalculent toutes les cellules courantes affectées selon leur instant métier canonique. |

## Projections et snapshots

| ID | Règle |
|---|---|
| `ANL-INV-021` | La clé logique d'une MetricSeries est unique dans un Workspace et une génération. |
| `ANL-INV-022` | Seul le projecteur canonique modifie une série ; aucune correction manuelle de valeur n'est autorisée. |
| `ANL-INV-023` | Une seule ProjectionGeneration est active par Workspace et jeu de définitions. |
| `ANL-INV-024` | Un rebuild écrit dans une génération isolée et ne remplace l'active qu'après rattrapage des watermarks et validation. |
| `ANL-INV-025` | Un checkpoint ne peut avancer qu'après commit durable des faits et cellules correspondants. |
| `ANL-INV-026` | Toute réponse expose disponibilité, fraîcheur, complétude, instant de calcul et watermarks pertinents. |
| `ANL-INV-027` | Un AnalyticsSnapshot contient uniquement des observations de la même génération active, du même `AsOf` et d'un jeu cohérent de watermarks. |
| `ANL-INV-028` | Un snapshot publié est immuable ; une nouvelle publication le remplace sans le réécrire. |
| `ANL-INV-029` | Un snapshot n'est publié que si les métriques obligatoires satisfont leur politique de complétude et de fraîcheur. |

## Autorité et fiabilité

| ID | Règle |
|---|---|
| `ANL-INV-030` | Analytics ne commande ni ne modifie jamais CRM, Billing ou Workspace. |
| `ANL-INV-031` | Une lecture humaine exige un acteur actif et `analytics.metrics.read` dans le même Workspace. |
| `ANL-INV-032` | L'ingestion, le rebuild, la publication et la consommation machine exigent leur capacité SystemActorOnly bornée. |
| `ANL-INV-033` | Chaque processeur possède un RequestId ; un rejeu identique retourne le résultat initial et une réutilisation incompatible échoue. |
| `ANL-INV-034` | État, événements et outbox sont commis atomiquement ; les consommateurs dédupliquent `EventId`. |
| `ANL-INV-035` | Une restriction Workspace refuse les lectures ordinaires sans mélanger, effacer ou republier les séries. |
| `ANL-INV-036` | Analytics ne présente jamais PipelineAmount comme prévision, CollectedAmount comme trésorerie, ni NetInvoicedAmount comme revenu comptable. |
