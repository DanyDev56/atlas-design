---
id: CRM-CMD-ARCHIVE-CONTACT
title: ArchiveContact
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

# ArchiveContact

## Objectif

Retirer un Contact de l'usage courant tout en conservant ses références passées.

## Agrégat concerné

`Client`, avec contrôle des Opportunity référentes.

## Acteur et autorité

Membre autorisé par `crm.contacts.archive`.

## Données d'entrée

```text
WorkspaceId
ClientId
ContactId
ArchiveReason
ExpectedRevision
ArchiveContactRequestId
ActorContext
```

## Préconditions et traitement

- Client et Contact actifs ;
- aucune Opportunity non terminale ne référence le Contact ;
- révision attendue courante ;
- passage du Contact à `Archived` ;
- effacement atomique de `PrimaryContactId` s'il le désignait.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-006`, `CRM-INV-007`, `CRM-INV-008`,
`CRM-INV-009`, `CRM-INV-010`, `CRM-INV-021`, `CRM-INV-022`,
`CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ContactArchived` ;
- `ClientPrimaryContactChanged` si nécessaire.

## Erreurs métier

`Unauthorized`, `NotFound`, `InvalidState`, `ContactInUse`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`ArchiveContactRequestId` est obligatoire. Un retry identique retourne le
résultat initial, y compris le changement de Contact principal.
