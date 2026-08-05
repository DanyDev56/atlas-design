---
id: CRM-CMD-QUALIFY-OPPORTUNITY
title: QualifyOpportunity
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

# QualifyOpportunity

## Objectif

Confirmer qu'une Opportunity ouverte représente une possibilité commerciale
réelle pouvant progresser vers une proposition.

## Agrégat concerné

`Opportunity`.

## Acteur et autorité

Membre autorisé par `crm.opportunities.qualify`.

## Données d'entrée

```text
WorkspaceId
OpportunityId
QualificationContext
ExpectedRevision
QualifyOpportunityRequestId
ActorContext
```

## Préconditions et traitement

- statut `Open` ;
- Client toujours actif ;
- titre et contexte commercial valides ;
- Contact référencé toujours actif lorsqu'il existe ;
- révision attendue courante ;
- passage à `Qualified` avec instant et acteur.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-011`, `CRM-INV-012`, `CRM-INV-013`,
`CRM-INV-014`, `CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`,
`CRM-INV-024`.

## Événements produits

- `OpportunityQualified`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`.

## Idempotence

`QualifyOpportunityRequestId` est obligatoire. Même clé retourne la transition
initiale ; une nouvelle clé après qualification est invalide.
