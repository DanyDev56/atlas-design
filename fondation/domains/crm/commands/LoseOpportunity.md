---
id: CRM-CMD-LOSE-OPPORTUNITY
title: LoseOpportunity
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

# LoseOpportunity

## Objectif

Enregistrer qu'une Opportunity ouverte ou qualifiée est perdue.

## Agrégat concerné

`Opportunity`.

## Acteur et autorité

Membre autorisé par `crm.opportunities.lose`.

## Données d'entrée

```text
WorkspaceId
OpportunityId
LossReasonCode
LossNote?
ExpectedRevision
LoseOpportunityRequestId
ActorContext
```

## Préconditions et traitement

- statut `Open` ou `Qualified` ;
- raison structurée supportée ;
- note éventuelle bornée ;
- révision attendue courante ;
- passage terminal à `Lost`.

`QuoteRejected` seul n'est jamais une précondition suffisante ou un déclencheur
automatique.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-011`, `CRM-INV-013`, `CRM-INV-014`,
`CRM-INV-020`, `CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`,
`CRM-INV-024`.

## Événements produits

- `OpportunityLost`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`LoseOpportunityRequestId` est obligatoire. Un retry identique retourne le
résultat initial ; une nouvelle demande après terminaison est invalide.
