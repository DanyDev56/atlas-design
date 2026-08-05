---
id: CRM-CMD-UPDATE-CLIENT-BILLING-PROFILE
title: UpdateClientBillingProfile
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../scope.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# UpdateClientBillingProfile

## Objectif

Remplacer les données administratives courantes fournies aux futurs usages
Billing.

## Agrégat concerné

`Client`.

## Acteur et autorité

Membre autorisé par `crm.clients.update-billing-profile`.

## Données d'entrée

```text
WorkspaceId
ClientId
ClientBillingProfile
ExpectedRevision
UpdateClientBillingProfileRequestId
ActorContext
```

## Préconditions et traitement

- Client actif et contextualisé ;
- adresses et identifiants structurellement valides ;
- absence de doublon de type déclaré ;
- révision attendue courante ;
- remplacement et incrément de `ClientBillingProfileVersion`.

CRM ne décide pas si le profil suffit pour émettre un document.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-004`, `CRM-INV-020`, `CRM-INV-021`,
`CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ClientBillingProfileUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`UpdateClientBillingProfileRequestId` est obligatoire. Le retry identique
retourne la version initiale sans modifier les snapshots Billing.
