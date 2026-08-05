---
id: CRM-CMD-REMOVE-ACTIVITY
title: RemoveActivity
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

# RemoveActivity

## Objectif

Retirer logiquement une Activity erronée ou devenue inappropriée des lectures
ordinaires.

## Agrégat concerné

`Activity`.

## Acteur et autorité

Membre autorisé par `crm.activities.remove`.

## Données d'entrée

```text
WorkspaceId
ActivityId
RemovalReason
ExpectedRevision
RemoveActivityRequestId
ActorContext
```

## Préconditions et traitement

- Activity `Recorded` ;
- raison structurée et auditée ;
- révision attendue courante ;
- passage terminal à `Removed` sans suppression des révisions.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-006`, `CRM-INV-018`, `CRM-INV-019`,
`CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ActivityRemoved`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`RemoveActivityRequestId` est obligatoire. Le retry identique retourne le
résultat initial ; une nouvelle demande sur `Removed` est invalide.
