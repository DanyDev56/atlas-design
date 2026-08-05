---
id: CRM-CONSOLIDATION
title: CRM Consolidation Matrix
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - scope.md
  - model.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - relationships.md
  - invariants.md
  - decision-record.md
  - permissions.md
  - events.md
  - api.md
  - integrations.md
  - workflows.md
  - future.md
  - glossary.md
  - commands/README.md
---

# Matrice de consolidation

## Sources canoniques

| Source | Présente | Normalisée | Cohérente | Complète 1.0 |
|---|---:|---:|---:|---:|
| Mission et scope | Oui | Oui | Oui | Oui |
| Modèle, entités et agrégats | Oui | Oui | Oui | Oui |
| Value Objects et relations | Oui | Oui | Oui | Oui |
| Invariants et décisions | Oui | Oui | Oui | Oui |
| Permissions et commandes | Oui | Oui | Oui | Oui |
| Domain Events | Oui | Oui | Oui | Oui |
| API et intégrations | Oui | Oui | Oui | Oui |
| Workflows, glossaire et futur | Oui | Oui | Oui | Oui |

---

## Traçabilité des commandes

| Commande | Autorité | Permission | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `CreateClient` | Membre autorisé | `crm.clients.create` | 001, 002, 004, 021, 023, 024 | `ClientCreated` | `CreateClientRequestId` |
| `UpdateClientProfile` | Membre autorisé | `crm.clients.update-profile` | 001, 004, 020–024 | `ClientProfileUpdated` | `UpdateClientProfileRequestId` |
| `UpdateClientBillingProfile` | Membre autorisé | `crm.clients.update-billing-profile` | 001, 004, 020–024 | `ClientBillingProfileUpdated` | `UpdateClientBillingProfileRequestId` |
| `ArchiveClient` | Membre autorisé | `crm.clients.archive` | 001, 003, 005, 006, 021–024 | `ClientArchived` | `ArchiveClientRequestId` |
| `ReactivateClient` | Membre autorisé | `crm.clients.reactivate` | 001, 003, 004, 006, 021–024 | `ClientReactivated` | `ReactivateClientRequestId` |
| `AddContact` | Membre autorisé | `crm.contacts.create` | 001, 002, 004, 007, 008, 021–024 | `ContactAdded`, `ClientPrimaryContactChanged` | `AddContactRequestId` |
| `UpdateContact` | Membre autorisé | `crm.contacts.update` | 001, 007, 009, 020–024 | `ContactUpdated` | `UpdateContactRequestId` |
| `ChangeClientPrimaryContact` | Membre autorisé | `crm.contacts.change-primary` | 001, 007–009, 021–024 | `ClientPrimaryContactChanged` | `ChangePrimaryContactRequestId` |
| `ArchiveContact` | Membre autorisé | `crm.contacts.archive` | 001, 006–010, 021–024 | `ContactArchived`, `ClientPrimaryContactChanged` | `ArchiveContactRequestId` |
| `ReactivateContact` | Membre autorisé | `crm.contacts.reactivate` | 001, 007–009, 021–024 | `ContactReactivated` | `ReactivateContactRequestId` |
| `CreateOpportunity` | Membre autorisé | `crm.opportunities.create` | 001, 002, 004, 011–013, 015, 021, 023, 024 | `OpportunityCreated` | `CreateOpportunityRequestId` |
| `UpdateOpportunity` | Membre autorisé | `crm.opportunities.update` | 001, 011–013, 015, 020–024 | `OpportunityUpdated` | `UpdateOpportunityRequestId` |
| `QualifyOpportunity` | Membre autorisé | `crm.opportunities.qualify` | 001, 011–014, 021–024 | `OpportunityQualified` | `QualifyOpportunityRequestId` |
| `WinOpportunity` | Membre ou workflow Billing | `crm.opportunities.win`, `crm.opportunities.win-from-quote` | 001, 011, 013, 014, 020–024 | `OpportunityWon` | `WinOpportunityRequestId` |
| `LoseOpportunity` | Membre autorisé | `crm.opportunities.lose` | 001, 011, 013, 014, 020–024 | `OpportunityLost` | `LoseOpportunityRequestId` |
| `RecordActivity` | Membre autorisé | `crm.activities.record` | 001, 002, 016, 017, 021, 023, 024 | `ActivityRecorded` | `RecordActivityRequestId` |
| `CorrectActivity` | Membre autorisé | `crm.activities.correct` | 001, 016–019, 021–024 | `ActivityCorrected` | `CorrectActivityRequestId` |
| `RemoveActivity` | Membre autorisé | `crm.activities.remove` | 001, 006, 018, 019, 021–024 | `ActivityRemoved` | `RemoveActivityRequestId` |

Les numéros abrégés désignent `CRM-INV-nnn`. Les fiches constituent la source
normative complète.

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Contrepartie potentielle | `Client`, sans Lead ni Prospect distinct |
| Agrégats | Client avec Contacts, Opportunity, Activity |
| Pipeline | projection fixe par statut |
| Client | `Active`, `Archived` |
| Contact | `Active`, `Archived` |
| Opportunity | `Open`, `Qualified`, `Won`, `Lost` |
| Résultats Opportunity | terminaux |
| Activity | fait CRM passé, distinct de la timeline transverse |
| Suppression | aucune suppression physique métier |
| Doublons | détection non bloquante, pas d'unicité heuristique |
| Billing | snapshots détenus par Billing |
| Advisor | analyses et recommandations hors CRM |

---

## Quality gates CRM 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Les quatre concepts CRM possèdent une définition unique.
- [x] Les trois agrégats et leurs frontières sont documentés.
- [x] Les transitions de cycle de vie sont fermées.
- [x] Toutes les commandes figurent dans le catalogue.
- [x] Chaque commande référence invariants, permission et idempotence.
- [x] Tous les Domain Events possèdent un producteur tracé.
- [x] Toutes les permissions utilisées figurent dans le catalogue.
- [x] Pipeline ne contourne aucune commande Opportunity.
- [x] Le contrat Billing repose sur des snapshots non rétroactifs.
- [x] QuoteAccepted et OpportunityWon restent deux faits distincts.
- [x] Activity ne duplique pas les événements des autres domaines.
- [x] Les références inter-workspaces sont refusées.
- [x] Les extensions futures sont hors du contrat 1.0.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-crm-docs.sh
```

CRM 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable` exige une
implémentation et des tests de domaine conformes.
