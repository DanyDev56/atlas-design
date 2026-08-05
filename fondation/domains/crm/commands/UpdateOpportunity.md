---
id: CRM-CMD-UPDATE-OPPORTUNITY
title: UpdateOpportunity
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# UpdateOpportunity

## Objectif

Modifier les détails ou le Contact courant d'une Opportunity non terminale.

## Agrégat concerné

`Opportunity`.

## Acteur et autorité

Membre autorisé par `crm.opportunities.update`.

## Données d'entrée

```text
WorkspaceId
OpportunityId
OpportunityChanges
ExpectedRevision
UpdateOpportunityRequestId
ActorContext
```

## Préconditions et traitement

- statut `Open` ou `Qualified` ;
- Client actif et inchangé ;
- nouveau Contact éventuel actif dans le même Client ;
- détails résultants valides ;
- révision attendue courante ;
- remplacement immuable des valeurs modifiables.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-011`, `CRM-INV-012`, `CRM-INV-013`,
`CRM-INV-015`, `CRM-INV-020`, `CRM-INV-021`, `CRM-INV-022`,
`CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `OpportunityUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`.

## Idempotence

`UpdateOpportunityRequestId` est obligatoire. Un retry identique retourne la
version initialement produite.
