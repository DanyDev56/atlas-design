---
id: BPT-003
title: MVP Business Lifecycle
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - README.md
  - user-journeys.md
  - ../roadmap/mvp-acceptance.md
  - ../../fondation/domain-map/dependencies.md
  - ../../fondation/domains/crm/events.md
  - ../../fondation/domains/billing/events.md
  - ../../fondation/domains/analytics/events.md
  - ../../fondation/domains/business-health/events.md
  - ../../fondation/domains/advisor/events.md
  - ../../fondation/domains/notifications/events.md
---

# Cycle métier du MVP

## Chaîne principale

```text
CRM
  OpportunityQualified
        |
        v
Billing
  QuoteAccepted ----------------------> CRM OpportunityWon
        |
        v
  InvoiceIssued -> PaymentRecorded -> InvoicePaid
        |
        v
Analytics
  AnalyticsFactRecorded -> AnalyticsSnapshotPublished
        |
        v
Business Health
  BusinessHealthAssessed
        |
        v
Advisor
  AdvisorOverviewChanged -> RecommendationEvaluationCompleted
        |
        v
Notifications
  NotificationPlanCompleted -> NotificationCreated?
```

Le `?` est intentionnel : un plan valide peut décider de ne créer aucune
notification. De même, une évaluation Advisor valide peut ne produire aucune
Recommendation et une évaluation Business Health peut conclure à
`InsufficientData`.

## Chemin de démarrage avec historique

```text
CRM ClientHistoryImportCompleted
        +
Billing BillingHistoryImportCompleted
        |
        v
Analytics isolated rebuild -> validated generation -> snapshot
        |
        v
Business Health -> Advisor -> Notifications?
```

Les événements de completion ne simulent aucun fait opérationnel. Analytics
relit les manifests et ne bascule sa génération qu'après validation complète.

---

## Sémantique des transitions

- CRM et Billing possèdent les décisions commerciales et financières ;
- Analytics transforme leurs faits versionnés en mesures, sans les modifier ;
- la fraîcheur Analytics mesure l'avancement du traitement des événements
  connus, pas l'ancienneté de la dernière activité ;
- les états et fenêtres dépendants du temps sont recalculés à l'`AsOf`, même en
  l'absence de nouvel événement métier ;
- Business Health interprète un snapshot exact, sans recalculer ses métriques ;
- Advisor propose une action, sans l'exécuter ;
- Advisor reconstruit l'overview après toute évaluation appliquée ou mutation
  terminale ;
- Notifications décide d'une diffusion depuis `AdvisorOverviewChanged`, sans
  modifier la Recommendation ;
- chaque consommateur déduplique l'événement source et relit la révision exacte
  par un contrat public lorsque son invariant l'exige.

La chaîne est asynchrone après les transactions locales. Une interface affiche
donc la dernière version prouvée et sa fraîcheur, jamais une cohérence immédiate
fictive. Tant qu'un événement source attendu n'est pas traité, aucune nouvelle
vue complète n'est publiée et la dernière évaluation valide reste courante. Un
Workspace calme dont le backlog est vide reste, lui, une source courante.

---

## Boucle d'action

Une `RecommendationAction` pointe vers une route allowlistée de CRM ou Billing.
Après navigation, le domaine cible recharge, réautorise et exécute sa propre
intention. Les nouveaux événements retournent ensuite dans Analytics :

```text
RecommendationAction
  -> commande du domaine propriétaire
  -> nouveaux faits
  -> nouveau snapshot
  -> nouvelle évaluation
```

Cette boucle termine la proposition de valeur du MVP. `Automation` n'en fait
pas partie et reste post-MVP.
