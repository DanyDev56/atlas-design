---
id: WSP-CMD-UPDATE-WORKSPACE-BILLING-IDENTITY
title: UpdateWorkspaceBillingIdentity
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../scope.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# UpdateWorkspaceBillingIdentity

## Objectif

Remplacer l'identité de facturation déclarée utilisée comme source des futurs
snapshots.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

Un membre autorisé par `workspace.billing-identity.update`, avec une session
récemment élevée selon la politique Identity.

## Données d'entrée

```text
WorkspaceId
BillingIdentity
ExpectedRevision
UpdateBillingIdentityRequestId
ActorContext
```

## Préconditions

- Workspace `Active` ;
- autorisation critique valide dans le même Workspace ;
- structure complète syntaxiquement valide ;
- révision attendue courante.

## Traitement métier

1. normaliser noms, adresse et identifiants ;
2. vérifier les types et doublons supportés ;
3. remplacer la valeur courante ;
4. incrémenter `BillingIdentityVersion` ;
5. publier la nouvelle version sans données sensibles.

## Résultat attendu

Les futurs consommateurs peuvent lire la nouvelle valeur. Les devis, factures
et autres snapshots existants restent inchangés.

## Invariants concernés

- `WSP-INV-006` ;
- `WSP-INV-007` ;
- `WSP-INV-008` ;
- `WSP-INV-011` ;
- `WSP-INV-013` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-017`.

## Événements produits

- `WorkspaceBillingIdentityUpdated`.

## Erreurs métier

- `InvalidInput` ;
- `Unauthenticated` ;
- `Unauthorized` ;
- `NotFound` ;
- `InvalidState` ;
- `Conflict`.

## Idempotence

`UpdateBillingIdentityRequestId` est obligatoire. Un retry identique retourne la
même `BillingIdentityVersion`. Toute réutilisation avec une autre identité est
refusée.

## Décisions de conception

La commande ne valide ni taux de taxe, ni numérotation, ni aptitude à émettre
une facture. Ces décisions appartiennent à Billing.
