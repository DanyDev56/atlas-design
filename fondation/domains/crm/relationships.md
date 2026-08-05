---
id: CRM-RELATIONSHIPS
title: CRM Relationships
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - invariants.md
  - integrations.md
---

# Relations

## Cardinalités

```text
Workspace 1 -------- 0..* Client
Client    1 -------- 0..* Contact
Client    1 -------- 0..* Opportunity
Client    1 -------- 0..* Activity
Contact   0..1 ----- 0..* Opportunity
Contact   0..1 ----- 0..* Activity
Opportunity 0..1 --- 0..* Activity
```

CRM ne conserve aucune collection inverse dans Workspace.

---

## Contact principal

Un Client possède zéro ou un `PrimaryContactId`. Cette référence désigne un
Contact actif contenu dans le même agrégat.

Archiver le Contact principal efface atomiquement la référence. L'archivage est
toutefois refusé si une Opportunity non terminale dépend encore de ce Contact.

---

## Opportunity et Quote

Une Quote Billing peut référencer une Opportunity. L'Opportunity ne contient
jamais la Quote.

```text
OpportunityId --reference--> Quote
QuoteAccepted --fact--> orchestration --> WinOpportunity
```

L'orchestrateur conserve corrélation et idempotence. CRM ne lit pas l'état
interne de la Quote.

---

## Activity et timeline

Une Activity CRM représente une interaction saisie ou importée comme fait CRM.
La timeline transverse juxtapose :

- les Activity CRM ;
- les événements Opportunity ;
- les événements Billing ;
- les recommandations et notifications pertinentes.

Cette projection n'acquiert la propriété d'aucun fait source.

---

## Isolement

Toutes les références sont résolues avec `WorkspaceId`. Deux identifiants de
Clients appartenant à des Workspaces différents ne peuvent jamais être reliés,
même si leurs profils sont identiques.
