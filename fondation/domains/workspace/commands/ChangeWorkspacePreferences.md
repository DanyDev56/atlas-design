---
id: WSP-CMD-CHANGE-WORKSPACE-PREFERENCES
title: ChangeWorkspacePreferences
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# ChangeWorkspacePreferences

## Objectif

Remplacer les préférences principales utilisées comme valeurs par défaut.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

Un membre autorisé par `workspace.preferences.change` dans le même Workspace.

## Données d'entrée

```text
WorkspaceId
WorkspacePreferences
ExpectedRevision
ChangePreferencesRequestId
ActorContext
```

## Préconditions

- Workspace `Active` ;
- autorisation contextualisée valide ;
- locale, fuseau, devise et pays supportés ;
- révision attendue courante.

## Traitement métier

1. canonicaliser chaque valeur ;
2. vérifier le catalogue de référence versionné ;
3. remplacer toutes les préférences comme un Value Object ;
4. incrémenter `PreferencesVersion` ;
5. publier le fait sans réécriture externe.

## Résultat attendu

Les usages futurs disposent des nouveaux défauts. Les faits et documents
existants conservent leurs valeurs effectives.

## Invariants concernés

- `WSP-INV-003` ;
- `WSP-INV-006` ;
- `WSP-INV-008` ;
- `WSP-INV-009` ;
- `WSP-INV-011` ;
- `WSP-INV-013` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-017`.

## Événements produits

- `WorkspacePreferencesChanged`.

## Erreurs métier

- `InvalidInput` ;
- `Unauthenticated` ;
- `Unauthorized` ;
- `NotFound` ;
- `InvalidState` ;
- `UnsupportedPreference` ;
- `Conflict` ;
- `TemporarilyUnavailable`.

## Idempotence

`ChangePreferencesRequestId` est obligatoire. La même clé et les mêmes valeurs
canoniques retournent le résultat initial ; une autre valeur produit `Conflict`.

## Décisions de conception

Changer la devise par défaut ne convertit aucun montant et n'active pas le
multi-devises.
