---
id: BLUEPRINT-011
title: Registre des RouteKeys
status: In Review
owner: Product and Frontend
version: 1.0
last_updated: 2026-08-06
references:
  - ../../fondation/decisions/ADR-003-route-keys.md
  - navigation.md
---

# Registre des RouteKeys

Le registre est exhaustif pour le MVP. Une clé est stable, en anglais, en
`lowercase.dot.case` et ne décrit ni framework ni URL. `Parameters` contient les
seuls paramètres admis ; le shell les valide avant autorisation et résolution.

| RouteKey | Parameters | Fallback | Owner | Status |
|---|---|---|---|---|
| `home` | — | `home` | Dashboard | Active |
| `customers` | — | `home` | CRM | Active |
| `customers.list` | — | `customers` | CRM | Active |
| `customers.detail` | `clientId` | `customers.list` | CRM | Active |
| `customers.opportunities` | `clientId?` | `customers` | CRM | Active |
| `sales` | — | `home` | Billing | Active |
| `sales.quotes` | — | `sales` | Billing | Active |
| `sales.quote-detail` | `quoteId` | `sales.quotes` | Billing | Active |
| `sales.invoices` | — | `sales` | Billing | Active |
| `sales.invoice-detail` | `invoiceId` | `sales.invoices` | Billing | Active |
| `sales.payments` | — | `sales` | Billing | Active |
| `sales.credit-notes` | — | `sales` | Billing | Active |
| `advice` | — | `home` | Advisor | Active |
| `advice.current` | — | `advice` | Advisor | Active |
| `advice.history` | — | `advice` | Advisor | Active |
| `advice.detail` | `recommendationId` | `advice.current` | Advisor | Active |
| `more` | — | `home` | Shell | Active |
| `more.analytics` | — | `more` | Analytics | Active |
| `more.notifications` | — | `more` | Notifications | Active |
| `more.settings.workspace` | — | `more` | Workspace | Active |
| `more.settings.members` | — | `more` | Identity | Active |
| `more.settings.roles` | — | `more` | Identity | Active |
| `more.settings.integrations` | — | `more` | Integrations | Active |

## Gouvernance

Une pull request ajoutant une RouteKey précise propriétaire, paramètres,
fallback, permission et écran mobile. Un renommage conserve un alias pendant au
moins une version publiée. Une clé inconnue est journalisée sans paramètres puis
résolue vers `home` ; elle ne provoque jamais une redirection externe.
