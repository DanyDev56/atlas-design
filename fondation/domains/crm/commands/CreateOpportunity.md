---
id: CRM-CMD-CREATE-OPPORTUNITY
title: CreateOpportunity
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../integrations.md
---

# CreateOpportunity

## Objectif

Enregistrer une vente potentielle avec un Client actif.

## Agrégat concerné

Nouvel agrégat `Opportunity`.

## Acteur et autorité

Membre autorisé par `crm.opportunities.create`.

## Données d'entrée

```text
WorkspaceId
OpportunityId
ClientId
ContactId?
OpportunityDetails
CreateOpportunityRequestId
ActorContext
```

## Préconditions et traitement

- Client actif dans le même Workspace ;
- Contact optionnel actif et rattaché au Client ;
- détails et montant estimé valides ;
- devise effective explicite ou fournie par Workspace ;
- création en `Open` avec versions initiales.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-002`, `CRM-INV-004`, `CRM-INV-011`,
`CRM-INV-012`, `CRM-INV-013`, `CRM-INV-015`, `CRM-INV-021`,
`CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `OpportunityCreated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `ReferenceConflict`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`CreateOpportunityRequestId` est obligatoire. Même clé et même entrée retournent
la même Opportunity ; une autre empreinte est refusée.
