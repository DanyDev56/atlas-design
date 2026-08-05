---
id: CRM-INTEGRATIONS
title: CRM Integrations
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - api.md
  - events.md
  - workflows.md
  - ../identity/api.md
  - ../workspace/api.md
  - ../billing/README.md
---

# Intégrations

## Identity

CRM demande une décision `authorize` pour toute intention humaine. La décision
porte le `UserId`, le `WorkspaceId`, la permission exacte et les versions
évaluées.

CRM ne lit ni Membership, ni Role, ni Session dans le stockage Identity.

---

## Workspace

CRM utilise :

- le contexte d'accès, directement ou au travers de la décision Identity ;
- `DefaultCurrency` lors de la saisie initiale d'une estimation ;
- `TimeZone` pour présenter les dates, jamais pour altérer les instants stockés.

Les valeurs effectives sont figées dans les Value Objects CRM. Un changement de
préférence n'entraîne aucune conversion rétroactive.

`WorkspaceAccessStateChanged` invalide l'accès ordinaire sans archiver les
données CRM.

---

## Billing

Billing consomme :

- `getClientBillingContext` ;
- `getOpportunityCommercialContext` lorsqu'une Quote part d'une Opportunity ;
- les événements Client et Opportunity nécessaires à ses projections.

CRM consomme les faits Billing uniquement par des workflows explicites. En 1.0,
`QuoteAccepted` peut causer `WinOpportunity` via une capacité système bornée.

`QuoteRejected` ne cause pas automatiquement `OpportunityLost` : une vente peut
continuer avec une proposition révisée.

---

## Analytics, Business Health et Advisor

Ces domaines consomment événements et read models pour calculer :

- taux de transformation ;
- valeur et âge du pipeline ;
- inactivité Client ;
- concentration et récurrence ;
- besoins de relance.

Ils ne modifient jamais CRM. Une recommandation exécutée demande une commande
publique avec l'autorité de l'utilisateur.

---

## Communication et calendrier

Les synchronisations e-mail et calendrier sont futures. Un adaptateur ne peut
pas transformer silencieusement tout message en Activity.

Une Activity importée devra conserver une référence externe, une déduplication
et une preuve de consentement ou de configuration.

---

## Garanties

- aucun accès direct au stockage d'un autre domaine ;
- outbox transactionnelle ;
- consommateurs idempotents ;
- corrélation et causalité de bout en bout ;
- retries bornés et observables ;
- données personnelles minimisées dans les événements ;
- refus par défaut lorsque le Workspace ou l'autorisation est incertain.
