---
id: BLUEPRINT-010
title: Boucle Business Health, Advisor et Notifications
status: In Review
owner: Product
version: 1.0
last_updated: 2026-08-06
---

# Boucle de décision

## Chaîne canonique

```text
AnalyticsSnapshotPublished
  → Business Health évalue et publie BusinessHealthAssessed
  → Advisor évalue et publie RecommendationCreated/Updated
  → Notifications planifie depuis le signal Advisor
  → NotificationCreated puis résultat de remise
```

Cette chaîne est asynchrone, au moins une fois et convergente. Aucun domaine ne
lit la base privée de son prédécesseur. Chaque consommateur conserve son curseur,
la version du contrat source et une clé d'idempotence.

## Responsabilités non négociables

| Domaine | Décide | Ne décide jamais |
|---|---|---|
| Business Health | score, couverture, facteurs, risques et attention | action utilisateur ou canal |
| Advisor | priorité, explication et action principale | score de santé ou remise |
| Notifications | visibilité, consentement, fréquence et canal | contenu métier ou priorité Advisor |

Le Dashboard compose des lectures publiques ; il ne reconstruit aucun calcul.

## Corrélation et convergence

`CausationId` référence l'événement immédiatement consommé et `CorrelationId`
reste celui du snapshot source. Les identifiants des Workspace et versions de
politique accompagnent chaque transition. Un événement rejoué produit le même
assessment, la même évaluation Advisor et le même plan de notification.

Les publications hors ordre peuvent enrichir l'historique, mais les projections
courantes avancent uniquement selon l'ordre canonique de leur domaine. Une
recommandation devenue obsolète est résolue par Advisor ; Notifications expire
alors les remises encore ouvertes au lieu d'inventer une priorité compensatoire.

## Dégradation explicite

- snapshot incomplet : Business Health expose couverture et facteurs inconnus ;
- politique incompatible : dead letter observable, aucune bascule implicite ;
- Advisor indisponible : la santé reste consultable ;
- Notifications indisponible : la recommandation reste accessible dans Advisor ;
- e-mail en échec : l'inbox conserve la notification et le résultat de remise.

## SLO et observabilité

| Mesure | Cible MVP |
|---|---|
| snapshot → assessment | p95 inférieur à 2 min |
| assessment → recommandation | p95 inférieur à 1 min |
| recommandation prioritaire → inbox | p95 inférieur à 1 min |
| événements sans `CorrelationId` | 0 |
| doublons visibles après rejeu | 0 |

Les tableaux de bord techniques segmentent par domaine, version de contrat et
version de politique. Les charges métier et données personnelles sont exclues
des logs.

## Scénarios contractuels

1. Même événement reçu deux fois : une seule sortie visible à chaque étape.
2. Ancien assessment reçu après le courant : historique enrichi, courant stable.
3. Couverture insuffisante : santé prudente, aucune recommandation non fondée.
4. Recommandation résolue avant remise : notification ouverte expirée.
5. Refus d'e-mail : inbox possible, aucun contournement du consentement.
