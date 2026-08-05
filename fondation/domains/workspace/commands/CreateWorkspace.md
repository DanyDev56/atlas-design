---
id: WSP-CMD-CREATE-WORKSPACE
title: CreateWorkspace
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../workflows.md
---

# CreateWorkspace

## Objectif

Créer l'identité stable d'une activité dans l'état `Provisioning` afin de lancer
son bootstrap.

## Agrégat concerné

Nouvel agrégat `Workspace`.

## Acteur et autorité

- un `User` actif créant son premier Workspace ;
- ou un workflow d'onboarding de confiance agissant pour ce User.

La création est une capacité de parcours et d'offre, pas une permission du
Workspace qui n'existe pas encore. L'identité de l'acteur et son éligibilité
sont vérifiées par les ports publics concernés.

## Données d'entrée

```text
WorkspaceId
InitialProfile
InitialPreferences
RequestedByUserId
CreateWorkspaceRequestId
CorrelationId
```

## Préconditions

- `WorkspaceId` n'est pas déjà affecté ;
- l'acteur est authentifié et éligible ;
- profil et préférences satisfont la readiness locale ;
- la limite d'offre éventuelle est acceptée par son domaine propriétaire.

## Traitement métier

1. normaliser les Value Objects ;
2. créer le Workspace en `Provisioning` ;
3. initialiser une `BillingIdentity` vide valide et toutes les versions à leur
   première valeur ;
4. exposer `AccessState = Restricted` ;
5. enregistrer le fait de création et l'outbox.

La commande ne crée aucun rôle ni membership.

## Résultat attendu

Un Workspace existe mais reste inutilisable jusqu'à `ActivateWorkspace`.

## Invariants concernés

- `WSP-INV-001` ;
- `WSP-INV-003` ;
- `WSP-INV-005` ;
- `WSP-INV-009` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-018`.

## Événements produits

- `WorkspaceCreated`.

## Erreurs métier

- `InvalidInput` ;
- `Unauthenticated` ;
- `Unauthorized` ;
- `Conflict` ;
- `UnsupportedPreference` ;
- `TemporarilyUnavailable`.

## Idempotence

`CreateWorkspaceRequestId` est obligatoire. La même clé et la même empreinte
retournent le même `WorkspaceId`. Une entrée différente avec la même clé produit
`Conflict`.

## Décisions de conception

La création et l'activation sont séparées pour qu'un échec Identity ne rende
jamais un espace partiellement administrable.
