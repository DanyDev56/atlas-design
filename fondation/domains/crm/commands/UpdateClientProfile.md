---
id: CRM-CMD-UPDATE-CLIENT-PROFILE
title: UpdateClientProfile
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

# UpdateClientProfile

## Objectif

Remplacer les informations commerciales courantes d'un Client actif.

## Agrégat concerné

`Client`.

## Acteur et autorité

Membre autorisé par `crm.clients.update-profile`.

## Données d'entrée

```text
WorkspaceId
ClientId
ProfileChanges
ExpectedRevision
UpdateClientProfileRequestId
ActorContext
```

## Préconditions et traitement

- Client actif dans le même Workspace ;
- changements non vides ;
- profil résultant valide ;
- révision attendue courante ;
- remplacement immuable et incrément de `ClientProfileVersion`.

Une absence de changement métier ne produit aucun événement.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-004`, `CRM-INV-020`, `CRM-INV-021`,
`CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ClientProfileUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`UpdateClientProfileRequestId` est obligatoire. Un retry identique retourne la
version produite ; une autre empreinte est refusée.
