---
id: CRM-CMD-WIN-OPPORTUNITY
title: WinOpportunity
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# WinOpportunity

## Objectif

Enregistrer le résultat gagné d'une Opportunity qualifiée.

## Agrégat concerné

`Opportunity`.

## Acteur et autorité

- membre autorisé par `crm.opportunities.win` ;
- ou orchestrateur autorisé par `crm.opportunities.win-from-quote` après un
  `QuoteAccepted` authentique.

## Données d'entrée

```text
WorkspaceId
OpportunityId
OpportunityWonResult
ExpectedRevision
WinOpportunityRequestId
ActorOrWorkflowContext
```

## Préconditions et traitement

- statut `Qualified` ;
- source manuelle ou Quote acceptée cohérente ;
- `QuoteId` obligatoire pour `AcceptedQuote` ;
- révision attendue courante ;
- passage terminal à `Won` avec source et instant.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-011`, `CRM-INV-013`, `CRM-INV-014`,
`CRM-INV-020`, `CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`,
`CRM-INV-024`.

## Événements produits

- `OpportunityWon`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`, `TemporarilyUnavailable`.

## Idempotence

`WinOpportunityRequestId` est obligatoire. Pour Billing, il est dérivé du couple
`QuoteId`–`OpportunityId`. Le même fait retourne le résultat initial ; un résultat
terminal incompatible est refusé.
