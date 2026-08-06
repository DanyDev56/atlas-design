---
id: CRM-COMMANDS
title: CRM Commands
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Commands

Une commande CRM exprime une intention métier unique. Elle est autorisée,
validée et commise atomiquement avec ses Domain Events.

## Catalogue

### Client

- [`CreateClient`](CreateClient.md)
- [`UpdateClientProfile`](UpdateClientProfile.md)
- [`UpdateClientBillingProfile`](UpdateClientBillingProfile.md)
- [`ArchiveClient`](ArchiveClient.md)
- [`ReactivateClient`](ReactivateClient.md)
- [`ImportHistoricalClients`](ImportHistoricalClients.md)

### Contact

- [`AddContact`](AddContact.md)
- [`UpdateContact`](UpdateContact.md)
- [`ChangeClientPrimaryContact`](ChangeClientPrimaryContact.md)
- [`ArchiveContact`](ArchiveContact.md)
- [`ReactivateContact`](ReactivateContact.md)

### Opportunity

- [`CreateOpportunity`](CreateOpportunity.md)
- [`UpdateOpportunity`](UpdateOpportunity.md)
- [`QualifyOpportunity`](QualifyOpportunity.md)
- [`WinOpportunity`](WinOpportunity.md)
- [`LoseOpportunity`](LoseOpportunity.md)

### Activity

- [`RecordActivity`](RecordActivity.md)
- [`CorrectActivity`](CorrectActivity.md)
- [`RemoveActivity`](RemoveActivity.md)

---

## Conventions communes

- toute commande porte `WorkspaceId` ;
- toute mutation existante vérifie `ExpectedRevision` ;
- chaque commande possède un RequestId spécialisé ;
- une permission Identity porte le même Workspace ;
- les références externes sont relues avant commit ;
- un refus ne produit aucun événement de réussite ;
- une nouvelle intention après un état terminal crée une nouvelle entité.

---

## Évolution

Une nouvelle commande doit représenter une intention absente du catalogue,
référencer ses invariants, permission, événements, concurrence et idempotence.
