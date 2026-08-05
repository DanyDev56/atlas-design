---
id: CRM-CMD-RECORD-ACTIVITY
title: RecordActivity
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

# RecordActivity

## Objectif

Enregistrer une interaction commerciale passée.

## Agrégat concerné

Nouvel agrégat `Activity`.

## Acteur et autorité

Membre autorisé par `crm.activities.record`.

## Données d'entrée

```text
WorkspaceId
ActivityId
ClientId
ContactId?
OpportunityId?
ActivityContent
RecordActivityRequestId
ActorContext
```

## Préconditions et traitement

- Client actif dans le même Workspace ;
- références optionnelles compatibles ;
- `OccurredAt` non futur ;
- type et résumé valides ;
- création en `Recorded` avec première révision.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-002`, `CRM-INV-016`, `CRM-INV-017`,
`CRM-INV-021`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ActivityRecorded`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `ReferenceConflict`, `Conflict`.

## Idempotence

`RecordActivityRequestId` est obligatoire. Même clé et même fait retournent la
même Activity sans doubler l'historique.
