---
id: CRM-CMD-CHANGE-CLIENT-PRIMARY-CONTACT
title: ChangeClientPrimaryContact
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

# ChangeClientPrimaryContact

## Objectif

Remplacer ou effacer la désignation du Contact principal.

## Agrégat concerné

`Client`.

## Acteur et autorité

Membre autorisé par `crm.contacts.change-primary`.

## Données d'entrée

```text
WorkspaceId
ClientId
NewPrimaryContactId?
ExpectedRevision
ChangePrimaryContactRequestId
ActorContext
```

## Préconditions et traitement

- Client actif ;
- nouveau Contact actif et contenu dans le Client lorsqu'il existe ;
- valeur différente de la référence courante ;
- révision attendue courante ;
- remplacement atomique de `PrimaryContactId`.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-007`, `CRM-INV-008`, `CRM-INV-009`,
`CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ClientPrimaryContactChanged`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`.

## Idempotence

`ChangePrimaryContactRequestId` est obligatoire. Un retry identique retourne la
désignation produite sans second événement.
