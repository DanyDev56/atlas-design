---
id: CRM-CMD-CREATE-CLIENT
title: CreateClient
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

# CreateClient

## Objectif

Créer une contrepartie commerciale active dans un Workspace.

## Agrégat concerné

Nouvel agrégat `Client`.

## Acteur et autorité

Membre autorisé par `crm.clients.create`.

## Données d'entrée

```text
WorkspaceId
ClientId
ClientKind
ClientProfile
ClientBillingProfile?
CreateClientRequestId
ActorContext
```

## Préconditions et traitement

- Workspace et autorisation actifs ;
- identifiant non affecté ;
- profil valide ;
- profil administratif vide valide lorsqu'il est omis ;
- normalisation, création en `Active`, initialisation des versions et commit.

Un doublon probable produit un signal non bloquant, jamais un refus automatique.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-002`, `CRM-INV-004`, `CRM-INV-021`,
`CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ClientCreated`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`CreateClientRequestId` est obligatoire. Même clé et même empreinte retournent
le même Client ; une entrée différente produit `Conflict`.
