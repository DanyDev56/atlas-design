---
id: CRM-CMD-ADD-CONTACT
title: AddContact
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

# AddContact

## Objectif

Ajouter une personne joignable à un Client actif.

## Agrégat concerné

`Client`.

## Acteur et autorité

Membre autorisé par `crm.contacts.create`.

## Données d'entrée

```text
WorkspaceId
ClientId
ContactId
ContactProfile
MakePrimary
ExpectedRevision
AddContactRequestId
ActorContext
```

## Préconditions et traitement

- Client actif dans le même Workspace ;
- `ContactId` non utilisé ;
- profil valide ;
- révision attendue courante ;
- ajout en `Active`, puis désignation principale atomique si demandée.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-002`, `CRM-INV-004`, `CRM-INV-007`,
`CRM-INV-008`, `CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`,
`CRM-INV-024`.

## Événements produits

- `ContactAdded` ;
- `ClientPrimaryContactChanged` lorsque `MakePrimary = true`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`AddContactRequestId` est obligatoire. Un retry reproduit le même Contact et la
même décision principale sans événement supplémentaire.
