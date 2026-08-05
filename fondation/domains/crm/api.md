---
id: CRM-PUBLIC-CONTRACT
title: CRM Public Contract
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - invariants.md
  - events.md
  - permissions.md
  - ../workspace/api.md
---

# Contrat public CRM

Le contrat exprime des intentions et lectures indépendantes du transport.

## Commandes Client et Contact

```text
createClient(workspaceId, kind, profile, billingProfile?, requestId)
updateClientProfile(workspaceId, clientId, changes, expectedRevision, requestId)
updateClientBillingProfile(workspaceId, clientId, profile, expectedRevision, requestId)
archiveClient(workspaceId, clientId, expectedRevision, requestId)
reactivateClient(workspaceId, clientId, expectedRevision, requestId)

addContact(workspaceId, clientId, profile, expectedRevision, requestId)
updateContact(workspaceId, clientId, contactId, changes, expectedRevision, requestId)
changeClientPrimaryContact(workspaceId, clientId, contactId?, expectedRevision, requestId)
archiveContact(workspaceId, clientId, contactId, expectedRevision, requestId)
reactivateContact(workspaceId, clientId, contactId, expectedRevision, requestId)
```

## Commandes Opportunity

```text
createOpportunity(workspaceId, clientId, contactId?, details, requestId)
updateOpportunity(workspaceId, opportunityId, changes, expectedRevision, requestId)
qualifyOpportunity(workspaceId, opportunityId, expectedRevision, requestId)
winOpportunity(workspaceId, opportunityId, result, expectedRevision, requestId)
loseOpportunity(workspaceId, opportunityId, result, expectedRevision, requestId)
```

## Commandes Activity

```text
recordActivity(workspaceId, clientId, contactId?, opportunityId?, content, requestId)
correctActivity(workspaceId, activityId, correction, expectedRevision, requestId)
removeActivity(workspaceId, activityId, reason, expectedRevision, requestId)
```

---

## Lectures utilisateur

```text
getClient(workspaceId, clientId)
listClients(workspaceId, status?, search?, cursor?)
listClientContacts(workspaceId, clientId, status?)

getOpportunity(workspaceId, opportunityId)
listOpportunities(workspaceId, clientId?, status?, cursor?)
getPipeline(workspaceId)

getActivity(workspaceId, activityId)
listClientActivities(workspaceId, clientId, cursor?)
```

Les lectures appliquent respectivement les permissions `read`. Pagination,
tri et recherche ne modifient aucune règle métier.

---

## Contrats fournis à Billing

### Client courant

```text
getClientBillingContext(workspaceId, clientId)
→
WorkspaceId
ClientId
ClientStatus
ClientKind
ClientProfileVersion
ClientBillingProfileVersion
CurrentDisplayName
CurrentBillingProfile
```

### Opportunity courante

```text
getOpportunityCommercialContext(workspaceId, opportunityId)
→
WorkspaceId
OpportunityId
ClientId
OpportunityStatus
OpportunityVersion
Title
EstimatedAmount?
ContactId?
```

Ces lectures ne promettent pas la conformité d'une Quote. Billing vérifie ses
propres préconditions et copie un snapshot.

---

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | valeur syntaxiquement invalide |
| `Unauthenticated` | principal absent ou invalide |
| `Unauthorized` | permission ou capacité absente |
| `NotFound` | ressource absente ou masquée |
| `InvalidState` | transition impossible |
| `InvariantViolation` | règle absolue menacée |
| `ReferenceConflict` | Contact, Client ou Opportunity incompatible |
| `ActiveOpportunityExists` | archivage Client bloqué |
| `ContactInUse` | archivage Contact bloqué |
| `Conflict` | révision ou idempotence incompatible |
| `TemporarilyUnavailable` | dépendance nécessaire indisponible |

---

## Versioning

Les champs optionnels s'ajoutent de manière compatible. Un changement de sens,
de cycle de vie ou d'enum exige une nouvelle version. Une permission ou un nom
d'événement publié n'est jamais réutilisé.
