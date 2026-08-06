---
id: REF-001
title: MVP Reference Fixtures
status: In Review
owner: Product and Engineering
version: 1.1.0
last_updated: 2026-08-06

references:
  - ../blueprint/implementation-plan.md
  - ../roadmap/mvp-acceptance.md
  - ../governance/quality-gates.md
  - ../../fondation/domains/analytics/metric-catalog.md
  - ../../fondation/domains/business-health/health-policy.md
  - ../../fondation/domains/advisor/recommendation-policy.md
  - ../../fondation/domains/advisor/recommendation-score.md
  - ../../fondation/domains/notifications/notification-policy.md
---

# Fixtures de référence du MVP

## Objectif

[`mvp-v1.json`](mvp-v1.json) est l'oracle exécutable de la chaîne :

```text
faits CRM et Billing normalisés
  -> Analytics
  -> Business Health
  -> Advisor
  -> Notifications
```

Il fixe des entrées déterministes et les décisions exactes attendues. Une
implémentation peut adapter les entrées à ses ports techniques, mais elle ne
peut pas modifier les résultats sans faire évoluer la politique propriétaire
et la fixture dans le même changement.

Ces données sont entièrement synthétiques. Elles ne contiennent ni secret, ni
adresse de livraison, ni donnée Client réelle.

## Contrat de la fixture

Le document JSON possède sa propre `fixture_schema_version`. Cette version ne
remplace aucune version de domaine.

Chaque cas contient :

- un identifiant stable `FIX-001` à `FIX-010` et le scénario obligatoire qu'il
  prouve ;
- un `source_fact_summary` normalisé, exprimé en unités mineures et en secondes ;
- les treize observations Analytics attendues, avec état, unité et comparaison ;
- les composants, facteurs, risques et score Business Health attendus ;
- l'éligibilité, l'ordre et le rang des Recommendations Advisor ;
- la décision de planification et l'état final Notifications.

`source_fact_summary` n'est pas un nouveau contrat public CRM ou Billing. C'est
une représentation compacte des faits immuables nécessaires aux formules :

- `null` signifie que la population observée est vide et doit produire
  `NoData` ;
- `0` signifie qu'une population ou un flux connu produit une valeur nulle ;
- `current` et `baseline` suivent les fenêtres et comparaisons du catalogue
  Analytics ;
- les ratios conservent numérateur et dénominateur ;
- les durées conservent somme exacte en secondes et taille d'échantillon ;
- les collections annuelles conservent le total par Client synthétique afin de
  rendre la concentration recalculable.

Le fuseau est `UTC`, le calendrier de reporting est versionné `1.0.0`, la
devise est `EUR` et `AsOf` vaut `2026-06-30T00:00:00Z` dans le jeu 1.0.

## Versions canoniques

| Contrat | Version de la fixture |
|---|---|
| Définitions Analytics | `1.0` |
| Document du catalogue Analytics | `1.2.0` |
| SnapshotProfileKey | `BusinessHealthBaselineV1` |
| SnapshotProfileVersion | `1.0.0` |
| CurrentLagThreshold | `PT1H` |
| MaximumAcceptedLag | `PT24H` |
| HealthPolicyVersion | `1.0.0` |
| RecommendationPolicyVersion | `1.0.0` |
| NotificationPolicyVersion | `1.0.0` |

Les fixtures utilisent un retard source de trente minutes pour les snapshots
`Current`. `FIX-007` utilise deux heures : le snapshot reste publiable, mais son
état `Lagging` impose `InsufficientData` à Business Health. Un retard supérieur
à vingt-quatre heures empêcherait la publication Analytics et relève d'un test
de refus distinct, pas d'une évaluation métier.

## Catalogue des cas

| Fixture | Scénario | Résultat déterminant |
|---|---|---|
| `FIX-001` | activité vide | treize `NoData`, puis `InsufficientData` |
| `FIX-002` | opportunité sans devis | pipeline `Started`, couverture globale insuffisante |
| `FIX-003` | devis accepté sans paiement | pipeline `Stopped`, risque commercial observé mais source Advisor rejetée |
| `FIX-004` | paiement partiel | score 24 `AtRisk`, deux Recommendations `Critical` |
| `FIX-005` | facture soldée | score 74 `Stable`, concentration critique |
| `FIX-006` | client dominant | score 82 `Strong`, Recommendation de concentration classée 89 |
| `FIX-007` | données trop anciennes | snapshot `Lagging`, évaluation insuffisante |
| `FIX-008` | données suffisantes sans Recommendation | score 100 `Strong`, overview Advisor vide |
| `FIX-009` | Recommendation `High` éligible à l'email | fallback classé 71, InApp et Email accepté |
| `FIX-010` | destinataire révoqué avant dispatch | Notification résolue, Email annulé, aucun appel fournisseur |

## Règles d'évolution

- une correction de données sans changement de sens incrémente la version
  patch de la fixture ;
- un ajout rétrocompatible de champ ou de cas incrémente la version minor ;
- un changement de structure ou d'interprétation incrémente la version major ;
- un résultat métier modifié référence la nouvelle version de politique ;
- un snapshot doré ne remplace jamais les preuves structurées de ce fichier ;
- `scripts/check-mvp-reference-fixtures.sh` doit passer avant toute revue.

## Portée de la vérification

Le checker recalcule les treize observations depuis les résumés de faits,
vérifie les composants et facteurs Business Health, le score global, la
PrimaryAttention, les rangs Advisor et les décisions Notifications. Les futurs
tests d'adaptateur devront en plus prouver que les événements réels CRM/Billing
sont convertis vers ces mêmes entrées sans perte ni double comptage.
