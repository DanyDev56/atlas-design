---
id: CRM-VALUE-OBJECTS
title: CRM Value Objects
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - entities.md
  - invariants.md
  - ../workspace/value-objects.md
---

# Value Objects

Les valeurs CRM sont immuables, normalisées et validées à la construction.

## Identifiants

| Valeur | Rôle |
|---|---|
| `ClientId` | identifie un Client |
| `ContactId` | identifie un Contact |
| `OpportunityId` | identifie une Opportunity |
| `ActivityId` | identifie une Activity |
| `WorkspaceId` | référence externe et frontière d'isolation |
| `QuoteId` | référence Billing optionnelle dans un résultat |
| `CRMRequestId` | racine des clés d'idempotence |
| `ImportRunId` | identifie une intention d'import historique |

Ces types ne sont jamais interchangeables.

---

## HistoricalImportProvenance

```text
HistoricalImportProvenance
├── ImportRunId
├── SourceSystem
├── ExternalIdHash
├── SourceOccurredAt
├── ImportedAt
└── CanonicalRecordHash
```

L'identifiant externe est chiffré ou hashé selon le besoin de relecture et n'est
jamais publié. La provenance distingue une ligne importée d'une création Atlas
sans modifier son cycle Client courant.

---

## ClientKind

```text
Individual | Organization
```

Cette distinction adapte la saisie sans créer deux modèles de Client.

---

## ClientProfile

```text
ClientProfile
├── DisplayName
├── LegalName?
├── Description?
├── EmailAddress?
├── PhoneNumber?
├── WebsiteUrl?
└── PostalAddress?
```

`DisplayName` est obligatoire mais non unique. Les canaux sont normalisés sans
être considérés comme des identifiants métier.

---

## ClientBillingProfile

```text
ClientBillingProfile
├── BillingName?
├── BillingAddress?
├── BillingEmail?
├── RegistrationIdentifiers[]
└── TaxIdentifiers[]
```

CRM garantit la structure courante. Billing détermine si elle est suffisante et
valide pour un document donné, puis en conserve un snapshot.

---

## ContactProfile

```text
ContactProfile
├── GivenName
├── FamilyName
├── JobTitle?
├── EmailAddress?
├── PhoneNumber?
└── PreferredChannel?
```

Au moins un nom est requis. Une coordonnée peut être absente lorsque l'Activity
est enregistrée pour mémoire, mais les workflows de communication peuvent
exiger un canal.

---

## OpportunityDetails

```text
OpportunityDetails
├── Title
├── Description?
├── EstimatedAmount?
├── ExpectedDecisionDate?
└── NextAction?
```

`EstimatedAmount` est un `Money` positif ou nul et non un engagement. La devise
est figée à la création ou lors d'un remplacement explicite ; un changement de
préférence Workspace ne la convertit pas.

---

## QualificationContext

```text
QualifiedAt
QualifiedBy
Rationale?
```

L'instant et l'acteur sont déterminés par le système. La justification
optionnelle reste bornée et n'est pas exposée dans l'événement public.

---

## OpportunityResult

Pour `Won` :

```text
WonAt
Source: Manual | AcceptedQuote
QuoteId?
```

Pour `Lost` :

```text
LostAt
LossReasonCode
LossNote?
```

Les notes libres restent bornées et ne sont pas présentes dans les événements
publics.

---

## ActivityContent

```text
ActivityKind: Note | Call | Meeting | Email
Summary
OccurredAt
```

Une Activity décrit un fait passé. Une action future ou un rappel appartient à
un workflow de tâche, notification ou automatisation.

---

## ClientBillingContext

Lecture versionnée fournie à Billing :

```text
ClientBillingContext
├── WorkspaceId
├── ClientId
├── ClientStatus
├── ClientKind
├── ClientProfileVersion
├── ClientBillingProfileVersion
├── CurrentDisplayName
└── CurrentBillingProfile
```

La valeur n'est pas elle-même un snapshot historique tant que Billing ne l'a pas
copiée dans son agrégat.
