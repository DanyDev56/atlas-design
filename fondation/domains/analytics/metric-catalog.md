---
id: ANL-METRIC-CATALOG
title: Analytics Metric Catalog
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - mission.md
  - scope.md
  - model.md
  - value-objects.md
  - invariants.md
  - integrations.md
---

# Catalogue des métriques

Chaque clé et version possède une formule immuable. Une modification de
sémantique crée une nouvelle `MetricDefinitionVersion`.

## Commercial

| MetricKey | Nature | Définition 1.0 | Utilité |
|---|---|---|---|
| `analytics.pipeline.open-amount` | stock | Somme des `EstimatedAmount` des Opportunity `Open` ou `Qualified` à `AsOf`, par devise. | Quantifier le potentiel commercial déclaré. |
| `analytics.quotes.pending-amount` | stock | Somme brute des Quote `Sent` sans réponse terminale à `AsOf`, par devise. | Voir la valeur commerciale en attente. |
| `analytics.quotes.acceptance-rate` | ratio de période | `Accepted / (Accepted + Rejected + Expired)` selon l'instant terminal dans la période. Les Quote `Withdrawn` sont exclues. | Mesurer l'issue des offres effectivement exposées. |
| `analytics.quotes.average-response-time` | durée de période | Moyenne de `RespondedAt - SentAt` pour les Quote `Accepted` ou `Rejected` dans la période. | Comprendre la vitesse de décision Client. |

`analytics.pipeline.open-amount` expose la proportion d'Opportunity actives
possédant une estimation. Il n'est ni pondéré, ni présenté comme une prévision.

## Facturation et encaissement

| MetricKey | Nature | Définition 1.0 | Utilité |
|---|---|---|---|
| `analytics.billing.net-invoiced-amount` | flux | Somme des Invoice émises dans la période moins les CreditNote émises dans la même période, par devise. | Suivre le volume net facturé sans le nommer revenu comptable. |
| `analytics.billing.collected-amount` | flux | Somme des `AmountApplied` des Payment actifs, bucketée par `ReceivedAt`, par devise. | Suivre les encaissements enregistrés. |
| `analytics.receivables.outstanding-amount` | stock | Somme des `OutstandingBalance` positifs des Invoice émises à `AsOf`, par devise. | Connaître les créances restantes. |
| `analytics.receivables.overdue-amount` | stock | Somme des soldes positifs dont `DueDate < AsOf`, par devise. | Quantifier le retard de paiement. |
| `analytics.receivables.overdue-count` | stock | Nombre d'Invoice contribuant au montant en retard. | Donner un volume actionnable avec le montant. |
| `analytics.receivables.due-soon-amount` | stock | Somme des soldes positifs avec `AsOf <= DueDate <= AsOf + 7 jours civils`, par devise. | Anticiper les échéances proches. |

Une inversion de Payment retire sa contribution du bucket historique d'origine
dans la génération courante. Un snapshot publié auparavant reste inchangé.

## Comportement de paiement et concentration

| MetricKey | Nature | Définition 1.0 | Utilité |
|---|---|---|---|
| `analytics.payments.average-time-to-payment` | durée de période | Moyenne de `PaidAt - IssuedAt` pour les Invoice dont le solde est devenu nul par un Payment dans la période. | Mesurer le délai d'encaissement observé. |
| `analytics.payments.on-time-rate` | ratio de période | Invoice dont le solde est devenu nul par Payment au plus tard à `DueDate`, divisées par les Invoice soldées par Payment dans la période. | Détecter une dégradation du comportement de paiement. |
| `analytics.clients.top-collection-share` | ratio de période | Plus grand encaissement cumulé par `ClientId` divisé par les encaissements totaux positifs de la période, par devise. | Mesurer la dépendance d'encaissement au premier Client. |

Une Invoice dont le solde devient nul par CreditNote ne contribue pas aux deux
métriques de paiement. Une Invoice rouverte après reversal cesse d'y contribuer
jusqu'à ce qu'un Payment rende à nouveau son solde nul.

## Périodes supportées

| WindowKind | Définition |
|---|---|
| `CalendarMonth` | mois civil dans le `ReportingCalendar` du Workspace |
| `Rolling30Days` | `[AsOf - 30 jours civils dans le ReportingCalendar, AsOf)` |
| `Rolling90Days` | même règle sur quatre-vingt-dix jours civils |
| `Rolling365Days` | même règle sur trois-cent-soixante-cinq jours civils |
| `PointInTime` | dernier état dont `EffectiveAt <= AsOf`, sans agrégation de flux |

Les intervalles de flux sont toujours `[StartInclusive, EndExclusive)`. Une
comparaison utilise la période immédiatement précédente, de même durée, même
fuseau, même définition, mêmes dimensions et même devise.

## Interprétation obligatoire

- une valeur monétaire est toujours accompagnée de `CurrencyCode` ;
- un ratio expose numérateur, dénominateur et taille d'échantillon ;
- une moyenne expose taille d'échantillon et politique d'exclusion ;
- un stock expose `AsOf` ;
- une absence de dénominateur retourne `NoData`, jamais `0 %` ;
- `CollectedAmount` n'est ni un solde bancaire ni une trésorerie réelle ;
- `NetInvoicedAmount` n'est ni un revenu comptable ni un bénéfice.

## SnapshotProfile 1.0

Le profil requis par Business Health est identifié sans valeur implicite :

```text
SnapshotProfileKey = BusinessHealthBaselineV1
SnapshotProfileVersion = 1.0.0
RequiredCompleteness = Complete
CurrentLagThreshold = PT1H
MaximumAcceptedLag = PT24H
```

`BusinessHealthBaselineV1@1.0.0` publie les 13 MetricKeys selon ce profil
immuable :

| Fenêtre | MetricKeys |
|---|---|
| `PointInTime` | `analytics.pipeline.open-amount`, `analytics.quotes.pending-amount`, `analytics.receivables.outstanding-amount`, `analytics.receivables.overdue-amount`, `analytics.receivables.overdue-count`, `analytics.receivables.due-soon-amount` |
| `Rolling30Days` | `analytics.billing.net-invoiced-amount`, `analytics.billing.collected-amount` |
| `Rolling90Days` | `analytics.quotes.acceptance-rate`, `analytics.quotes.average-response-time`, `analytics.payments.average-time-to-payment`, `analytics.payments.on-time-rate` |
| `Rolling365Days` | `analytics.clients.top-collection-share` |

Les valeurs monétaires restent séparées par devise. Une population réellement
vide, observée avec des watermarks complets, peut être `Complete` et `NoData`.

### Fraîcheur du profil

Chaque source requise expose un watermark `CompleteThrough`. Pour un snapshot :

```text
SourceLag = max(PT0S, AsOf - CompleteThrough)
SnapshotLag = max(SourceLag des sources CRM et Billing requises)
```

- `Current` signifie `SnapshotLag <= PT1H` ;
- `Lagging` signifie `PT1H < SnapshotLag <= PT24H` ;
- un watermark requis absent ou un `SnapshotLag > PT24H` refuse la publication
  avec `StaleData` ;
- une génération en reconstruction ou indisponible conserve respectivement
  `Rebuilding` ou `Unavailable` et ne publie pas ce profil.

Le profil autorise donc un snapshot `Lagging` mais borné afin que Business
Health rende visible la perte de fraîcheur. `CurrentLagThreshold` et
`MaximumAcceptedLag` sont versionnés avec le profil et ne peuvent pas être
élargis par un consommateur.

Chaque SnapshotMetric inclut un `PeriodComparison` lorsque la baseline est
disponible :

- les flux, ratios et durées utilisent la période adjacente immédiatement
  précédente, de même durée ;
- les stocks utilisent le même instant civil trente jours plus tôt ;
- définition, dimensions, devise et calendrier restent identiques ;
- une baseline absente ou non comparable produit `NotComparable`, jamais un
  delta inventé.
