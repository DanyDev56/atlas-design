---
id: ADV-ACTIONS
title: Recommendation Actions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - recommendation-policy.md
  - value-objects.md
  - integrations.md
---

# Actions

Une Recommendation possède exactement une `RecommendationAction` principale.
En 1.0, cette action ouvre un parcours autorisé ; elle n'appelle jamais une
commande CRM ou Billing au nom de l'utilisateur.

## ActionKind

```text
OpenFilteredView
StartCreateFlow
OpenBusinessHealthFactor
```

## Allowlist 1.0

| Module | RouteKey | ActionKind | Capacité requise |
|---|---|---|---|
| Billing | `OverdueInvoices` | `OpenFilteredView` | `billing.invoices.read` |
| Billing | `OutstandingInvoices` | `OpenFilteredView` | `billing.invoices.read` |
| Billing | `RecentInvoices` | `OpenFilteredView` | `billing.invoices.read` |
| CRM | `OpportunityPipeline` | `OpenFilteredView` | `crm.opportunities.read` |
| CRM | `NewOpportunity` | `StartCreateFlow` | `crm.opportunities.create` |
| BusinessHealth | `FactorDetails` | `OpenBusinessHealthFactor` | `business-health.assessments.read` |

`RouteKey` est une identité de navigation stable, pas une URL libre. Le client
Atlas la résout vers son transport courant.

## Autorisation au moment de l'action

1. l'interface résout la RouteKey allowlistée ;
2. Identity évalue la capacité requise pour l'acteur courant ;
3. le domaine cible relit son propre état ;
4. toute mutation éventuelle utilise sa commande publique, sa permission, sa
   révision et son RequestId ;
5. Advisor ne reçoit ni jeton délégué ni droit implicite.

Une Recommendation peut rester lisible si l'acteur n'a pas la capacité cible.
L'action est alors indisponible avec une explication ; la permission Advisor ne
contourne jamais celle du domaine cible.

## Formulation

Le libellé décrit ce que l'utilisateur peut faire, par exemple « Examiner et
relancer les factures en retard ». Il ne prétend jamais que la relance a été
envoyée, qu'un Client répondra ou qu'un gain sera obtenu.
