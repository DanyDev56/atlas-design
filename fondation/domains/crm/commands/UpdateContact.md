---
id: CRM-CMD-UPDATE-CONTACT
title: UpdateContact
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

# UpdateContact

## Objectif

Modifier le profil courant d'un Contact actif.

## Agrégat concerné

`Client` contenant le Contact.

## Acteur et autorité

Membre autorisé par `crm.contacts.update`.

## Données d'entrée

```text
WorkspaceId
ClientId
ContactId
ContactChanges
ExpectedRevision
UpdateContactRequestId
ActorContext
```

## Préconditions et traitement

- Client et Contact actifs dans le même Workspace ;
- profil résultant valide ;
- changements non vides ;
- révision Client courante ;
- remplacement du profil et incrément des versions.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-007`, `CRM-INV-009`, `CRM-INV-020`,
`CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ContactUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`UpdateContactRequestId` est obligatoire. Même clé et mêmes changements
retournent la version existante.
