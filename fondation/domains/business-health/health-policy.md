---
id: BHL-HEALTH-POLICY
title: Business Health Policy 1.0
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - scope.md
  - model.md
  - value-objects.md
  - invariants.md
  - ../analytics/metric-catalog.md
---

# Health Policy 1.0

`BusinessHealthBaselineV1@1.0.0` désigne le profil Analytics requis.
`HealthPolicyVersion = 1.0.0` désigne les règles ci-dessous. Les seuils sont une
politique produit initiale, jamais un benchmark sectoriel ou une garantie.

Toutes les preuves utilisées par un composant doivent être `Complete` et
`Current`. Un snapshot `Lagging`, même encore publiable par Analytics, produit
`InsufficientData` avec `StaleOrIncompleteSnapshot` ; aucun ancien score n'est
réutilisé.

## Principes de calcul

- les calculs intermédiaires utilisent des décimaux exacts ;
- seul le score final d'un composant, facteur ou ensemble est arrondi à l'entier
  le plus proche, moitié vers le haut ;
- une donnée absente ou non comparable rend le composant indisponible ;
- un composant indisponible n'est jamais noté zéro ;
- les poids disponibles sont renormalisés uniquement après satisfaction de la
  couverture minimale ;
- toutes les bornes de score sont incluses du côté explicitement indiqué.

## Facteurs

| FactorKey | Poids global | Couverture minimale | Composants |
|---|---:|---:|---|
| `CommercialMomentum` | 30 | 40 % | acceptation Quote 60 %, évolution pipeline 40 % |
| `BillingMomentum` | 20 | 50 % | évolution facturé net 50 %, évolution encaissé 50 % |
| `ReceivablesDiscipline` | 35 | 60 % | charge échue 60 %, paiement à temps 40 % |
| `ClientDiversification` | 15 | 100 % | concentration du premier Client 100 % |

Les huit MetricKeys interprétées sont :

```text
analytics.pipeline.open-amount
analytics.quotes.acceptance-rate
analytics.billing.net-invoiced-amount
analytics.billing.collected-amount
analytics.receivables.outstanding-amount
analytics.receivables.overdue-amount
analytics.payments.on-time-rate
analytics.clients.top-collection-share
```

La couverture d'un facteur est la somme des poids originaux de ses composants
disponibles. Son `FactorScore` est la moyenne pondérée renormalisée de ces seuls
composants si la couverture minimale est atteinte.

## CommercialMomentum

### analytics.quotes.acceptance-rate — Rolling90Days

Le composant exige au moins trois Quotes décidées.

| Taux | Score |
|---:|---:|
| `>= 70 %` | 100 |
| `>= 50 %` et `< 70 %` | 80 |
| `>= 30 %` et `< 50 %` | 55 |
| `>= 15 %` et `< 30 %` | 30 |
| `< 15 %` | 10 |

### analytics.pipeline.open-amount — PointInTime et évolution

| Évolution comparable | Score |
|---|---:|
| `Started` | 80 |
| `Stopped` | 0 |
| hausse `>= 10 %` | 100 |
| hausse de `0 %` à `< 10 %` | 80 |
| baisse de `< 10 %` | 60 |
| baisse de `10 %` à `< 30 %` | 35 |
| baisse `>= 30 %` | 10 |
| courant et baseline tous deux nuls | 10 |

`NotComparable`, une baseline absente ou une devise incompatible rendent le
composant indisponible.

## BillingMomentum

`analytics.billing.net-invoiced-amount` et
`analytics.billing.collected-amount`, tous deux en Rolling30Days, appliquent chacun
la même grille :

| Évolution comparable | Score |
|---|---:|
| `Started` | 80 |
| `Stopped` | 0 |
| hausse `>= 10 %` | 100 |
| hausse de `0 %` à `< 10 %` | 80 |
| baisse de `< 10 %` | 60 |
| baisse de `10 %` à `< 30 %` | 35 |
| baisse `>= 30 %` | 10 |
| courant et baseline tous deux nuls | 20 |

Un montant net négatif ou une comparaison `NotComparable` rend le composant
indisponible. Une activité nulle n'est pas assimilée à une dynamique saine.

## ReceivablesDiscipline

### analytics.receivables.overdue-amount / outstanding-amount

| Charge échue | Score |
|---|---:|
| aucun Outstanding et aucun Overdue | 100 |
| Overdue nul avec Outstanding positif | 100 |
| ratio `> 0 %` et `<= 10 %` | 80 |
| ratio `> 10 %` et `<= 25 %` | 55 |
| ratio `> 25 %` et `<= 50 %` | 25 |
| ratio `> 50 %` | 0 |

Overdue positif avec Outstanding nul est incohérent et rend le composant
indisponible.

### analytics.payments.on-time-rate — Rolling90Days

Le composant exige au moins trois factures soldées dans l'échantillon.

| Taux | Score |
|---:|---:|
| `>= 90 %` | 100 |
| `>= 75 %` et `< 90 %` | 80 |
| `>= 50 %` et `< 75 %` | 50 |
| `>= 25 %` et `< 50 %` | 25 |
| `< 25 %` | 0 |

## ClientDiversification

### analytics.clients.top-collection-share — Rolling365Days

| Part du premier Client | Score |
|---:|---:|
| `<= 35 %` | 100 |
| `> 35 %` et `<= 50 %` | 75 |
| `> 50 %` et `<= 70 %` | 45 |
| `> 70 %` et `<= 90 %` | 20 |
| `> 90 %` | 5 |

Une absence d'encaissement donnant `NoData` ne prouve aucune diversification et
rend le facteur indisponible.

## OverallScore et HealthBand

Un OverallScore exige au moins 70 % des poids globaux originaux disponibles.
Il est la moyenne pondérée renormalisée des FactorScore disponibles.

| Score | HealthBand |
|---:|---|
| 80–100 | `Strong` |
| 60–79 | `Stable` |
| 40–59 | `Watch` |
| 20–39 | `AtRisk` |
| 0–19 | `Critical` |

## Fiabilité

| AssessmentReliability | Condition |
|---|---|
| `Reliable` | OverallScore disponible, tous les facteurs et composants requis complets et courants |
| `Limited` | OverallScore disponible avec une couverture inférieure à 100 % |
| `Insufficient` | OverallScore indisponible ou snapshot incompatible |

## HealthTrend

La baseline est l'évaluation `Available` la plus proche, strictement antérieure,
avec même Workspace, politique et devise, distante d'au plus 45 jours.

| Delta de score | HealthTrend |
|---:|---|
| `>= +5` | `Improving` |
| `<= -5` | `Declining` |
| entre les deux | `Stable` |
| aucune baseline compatible | `Unknown` |

La même règle s'applique aux tendances de facteur.

## PrimaryAttention

Pour chaque facteur disponible :

```text
DeficitContribution = OriginalFactorWeight * (100 - FactorScore) / 100
```

La PrimaryAttention est le facteur ayant la contribution la plus élevée. Une
égalité est départagée par poids original décroissant, puis `FactorKey`
lexicographique. Elle est absente si l'OverallScore est indisponible.

## HealthRisk

| RiskKey | Déclencheur | Sévérité |
|---|---|---|
| `OverdueExposureRisk` | Overdue positif | `Low` jusqu'à 10 %, `Medium` jusqu'à 25 %, `High` jusqu'à 50 %, sinon `Critical` |
| `ClientConcentrationRisk` | part premier Client `> 50 %` | `Medium` jusqu'à 70 %, `High` jusqu'à 90 %, sinon `Critical` |
| `CommercialMomentumRisk` | pipeline en baisse d'au moins 10 % ou `Stopped`, ou acceptation `< 30 %` avec échantillon suffisant | `Medium` pour un signal modéré, `High` pour `Stopped`, baisse d'au moins 30 % ou deux signaux |
| `BillingMomentumRisk` | facturé net ou encaissé en baisse d'au moins 10 % ou `Stopped` | `Medium` pour un signal modéré, `High` pour un signal fort, `Critical` si les deux sont forts |

Un signal fort est `Stopped` ou une baisse d'au moins 30 %. Un risque n'est créé
que si ses preuves sont disponibles ; preuve insuffisante ne signifie pas
absence de risque.
