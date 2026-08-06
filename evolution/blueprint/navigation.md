---
id: BPT-005
title: MVP Navigation
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - modules.md
  - dashboard.md
  - ../roadmap/mvp-scope.md
  - ../../fondation/language/domain-language.md
---

# Navigation du MVP

## Navigation principale

- Dashboard
- CRM
- Billing
- Business Health
- Advisor

L'inbox Notifications est une destination globale accessible depuis l'en-tête.
Settings et le profil utilisateur sont accessibles depuis le sélecteur de
Workspace et le menu du principal.

Analytics n'est pas une destination de niveau 1 au MVP. Ses métriques et
explications sont exposées dans Dashboard et Business Health avec leur source,
période et fraîcheur.

---

## Arborescence

```text
Dashboard

CRM
  Clients
  Contacts
  Opportunities
  Activity

Billing
  Quotes
  Invoices
  Payments
  Credit Notes

Business Health
  Current Assessment
  History
  Explanation

Advisor
  Priority
  Recommendations
  History

Inbox

Settings
  Workspace Profile
  Billing Identity
  Preferences
  Members
  Roles and Permissions
  Notification Preferences

User Profile
  Personal Information
  Email and Security
  Sessions
```

Les libellés d'interface pourront être localisés, mais les concepts métier
canoniques conservent les noms définis dans Product Language.

---

## Règles de navigation

- le Workspace courant est visible sur toutes les surfaces métier ;
- une route transporte des identifiants opaques, jamais une décision
  d'autorisation présumée ;
- le module cible recharge et réautorise toujours la ressource ;
- une Recommendation ouvre une route allowlistée, pas une commande automatique ;
- un élément inaccessible par permission n'est pas affiché, sans que ce masquage
  remplace l'autorisation serveur ;
- les retours, liens profonds, erreurs et états vides conservent le contexte du
  Workspace lorsque celui-ci reste autorisé.

---

## Hors navigation MVP

`Projects`, `Analytics` autonome, `Automation`, `Integrations`, marketplace et
administration d'API ne sont pas des destinations du MVP.
