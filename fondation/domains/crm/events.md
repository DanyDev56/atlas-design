---
id: CRM-EVENTS
title: CRM Domain Events
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - invariants.md
  - commands/README.md
  - integrations.md
---

# Domain Events

Les événements CRM décrivent des faits déjà commis. Ils ne contiennent aucune
commande implicite ni donnée personnelle inutile.

## Enveloppe commune

| Champ | Description |
|---|---|
| `EventId` | identifiant unique |
| `EventName` | nom canonique |
| `SchemaVersion` | version du contrat |
| `OccurredAt` | instant du fait |
| `AggregateType` | Client, Opportunity, Activity ou ClientHistoryImportRun |
| `AggregateId` | identifiant de racine |
| `AggregateVersion` | révision après commit |
| `WorkspaceId` | contexte d'isolation |
| `CorrelationId` | workflow transversal |
| `CausationId` | commande ou événement causal |
| `ActorReference` | acteur auditable |
| `Data` | charge utile minimale |

Les adresses, téléphones, notes libres et identifiants fiscaux restent hors des
événements publics. Un consommateur autorisé relit la version nécessaire.

---

## Client

| Événement | Producteur | Fait minimum |
|---|---|---|
| `ClientCreated` | `CreateClient` | Client actif créé. |
| `ClientProfileUpdated` | `UpdateClientProfile` | Nouvelle version du profil commercial. |
| `ClientBillingProfileUpdated` | `UpdateClientBillingProfile` | Nouvelle version du profil administratif. |
| `ClientArchived` | `ArchiveClient` | Client retiré de l'usage courant. |
| `ClientReactivated` | `ReactivateClient` | Client archivé redevenu actif. |
| `ClientHistoryImportRequested` | `ImportHistoricalClients` | Run confirmé, hashé et accepté pour traitement. |
| `ClientHistoryImportCompleted` | `ImportHistoricalClients` | Manifest validé et Clients historiques rendus visibles. |

Les événements d'import contiennent seulement `ImportRunId`, version du
manifest, compteurs et hash. Ils n'exposent ni package, ni profil Client, ni
identifiant externe source. L'import ne fabrique aucun `ClientCreated`.

## Contact

| Événement | Producteur | Fait minimum |
|---|---|---|
| `ContactAdded` | `AddContact` | Contact actif ajouté au Client. |
| `ContactUpdated` | `UpdateContact` | Profil du Contact modifié. |
| `ClientPrimaryContactChanged` | `AddContact`, `ChangeClientPrimaryContact`, `ArchiveContact` | Référence principale remplacée ou effacée. |
| `ContactArchived` | `ArchiveContact` | Contact retiré de l'usage courant. |
| `ContactReactivated` | `ReactivateContact` | Contact archivé redevenu actif. |

`AddContact` et `ArchiveContact` produisent
`ClientPrimaryContactChanged` uniquement lorsque la désignation principale est
effectivement modifiée.

## Opportunity

| Événement | Producteur | Fait minimum |
|---|---|---|
| `OpportunityCreated` | `CreateOpportunity` | Opportunity créée en `Open`. |
| `OpportunityUpdated` | `UpdateOpportunity` | Détails courants modifiés. |
| `OpportunityQualified` | `QualifyOpportunity` | Réalité commerciale confirmée. |
| `OpportunityWon` | `WinOpportunity` | Vente potentielle gagnée. |
| `OpportunityLost` | `LoseOpportunity` | Vente potentielle perdue avec une raison. |

## Activity

| Événement | Producteur | Fait minimum |
|---|---|---|
| `ActivityRecorded` | `RecordActivity` | Interaction commerciale enregistrée. |
| `ActivityCorrected` | `CorrectActivity` | Nouvelle révision créée. |
| `ActivityRemoved` | `RemoveActivity` | Activity retirée logiquement. |

---

## Publication

- état et événement sont atomiques ;
- l'outbox publie après commit ;
- l'ordre par agrégat suit `AggregateVersion` ;
- les consommateurs dédupliquent `EventId` ;
- une commande refusée ne produit aucun événement de réussite ;
- une correction produit un nouveau fait sans écraser le précédent.
