---
id: CRM-CMD-IMPORT-HISTORICAL-CLIENTS
title: ImportHistoricalClients
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../../../../evolution/blueprint/historical-import.md
---

# ImportHistoricalClients

## Objectif

Matérialiser dans CRM un manifest confirmé de Clients historiques sans simuler
leur création opérationnelle.

## Agrégat concerné

Nouvel agrégat `ClientHistoryImportRun`, puis nouveaux agrégats `Client`
référencés par son manifest.

## Acteur et autorité

Membre autorisé par `crm.clients.import-history`, après step-up lorsque la
politique de sécurité l'exige.

## Données d'entrée

```text
WorkspaceId
ImportRunId
SourceSystem
SourceExportedAt
CanonicalPackageReference
PackageHash
ClientCount
ExpectedRevision = 0
ImportHistoricalClientsRequestId
ActorContext
```

Le manifest référence des lignes canoniques :

```text
HistoricalClientRecord
  ExternalId
  ClientKind
  ClientStatus: Active | Archived
  ClientProfile
  ClientBillingProfile?
  SourceCreatedAt
  CanonicalRecordHash
```

## Préconditions et traitement

- Workspace actif, package confirmé, scanné et dans sa durée de rétention ;
- manifest uniquement composé de Clients du même Workspace ;
- profils structurellement valides et états `Active | Archived` supportés ;
- identité stable par système source, type et identifiant externe ;
- traitement reprenable par checkpoint, sans visibilité courante avant la
  validation finale du manifest ;
- provenance, date source et hash de ligne conservés sur chaque Client importé.

La commande ne produit jamais `ClientCreated`. Elle ne fusionne pas un doublon
probable : la prévisualisation exige un choix avant confirmation.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-002`, `CRM-INV-004`, `CRM-INV-006`,
`CRM-INV-021`–`CRM-INV-024`, `CRM-INV-026`–`CRM-INV-028`.

## Événements produits

- `ClientHistoryImportRequested` ;
- `ClientHistoryImportCompleted` après validation de tous les checkpoints.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `ImportValidationFailed`,
`Conflict`, `TemporarilyUnavailable`.

## Concurrence et idempotence

`ExpectedRevision = 0` protège la création du run.
`ImportHistoricalClientsRequestId` et `PackageHash` sont obligatoires. Un rejeu
identique reprend ou retourne le run initial ; un contenu différent sous le même
RequestId, ImportRunId ou identifiant externe produit `Conflict`.
