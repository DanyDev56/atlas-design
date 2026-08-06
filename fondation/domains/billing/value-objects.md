---
id: BIL-VALUE-OBJECTS
title: Billing Value Objects
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - entities.md
  - invariants.md
  - ../crm/value-objects.md
  - ../workspace/value-objects.md
---

# Value Objects

## Identifiants

`QuoteId`, `InvoiceId`, `PaymentId`, `CreditNoteId`, `DocumentArtifactId`,
`WorkspaceId`, `ClientId`, `OpportunityId` et les RequestId
spécialisés sont des types distincts et non réutilisables.

`ImportRunId` identifie une intention d'import historique et ne peut être
substitué à aucun identifiant de document.

---

## Money

```text
Money
├── MinorUnits
└── CurrencyCode
```

Les calculs utilisent des entiers d'unités mineures ou une représentation
décimale exacte. Aucun flottant binaire n'est admis. Les opérations entre
devises différentes sont refusées.

---

## DocumentLine

```text
DocumentLine
├── LineId
├── Description
├── Quantity
├── Unit
├── UnitPrice
├── Discount?
├── TaxTreatment
└── LineTotals
```

Quantity est positive. Discount est borné. TaxTreatment contient le code, le
taux et la version de politique effectivement appliqués.

---

## DocumentTotals

```text
NetAmount
DiscountAmount
TaxBreakdown[]
TaxAmount
GrossAmount
CurrencyCode
RoundingAdjustment
```

Les totaux sont calculés, jamais acceptés aveuglément depuis un client API.

---

## DocumentNumber

Numéro canonique composé d'une série, d'une période et d'une valeur allouée. Il
est unique dans `(WorkspaceId, DocumentType, Series, Period, Value)` et jamais
réutilisé. La séquence est monotone dans
`(WorkspaceId, DocumentType, Series, Period)`.

Un document importé conserve séparément un `HistoricalDocumentNumber` exact,
affiché comme son numéro source. Cette valeur ne réserve aucun nombre dans
`DocumentNumberSequence` et porte toujours sa provenance pour éviter toute
confusion avec une émission Atlas.

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

La provenance est immuable. L'identifiant externe n'est jamais publié dans les
événements ou contrats Analytics.

---

## ClientSnapshot, OpportunitySnapshot et IssuerSnapshot

Copies immuables des profils courants CRM et Workspace, avec :

- identifiants sources ;
- versions sources ;
- noms et adresses effectifs ;
- identifiants déclarés requis ;
- empreinte du snapshot.

Les snapshots contiennent uniquement les données nécessaires au document.

`OpportunitySnapshot` est optionnel. Il conserve l'identifiant, la version, le
titre et la causalité commerciale utiles, jamais l'ensemble du dossier CRM.

---

## QuoteValidity et DepositPolicy

`QuoteValidity` fixe `ValidUntil` dans un fuseau et une date non ambigus.

`DepositPolicy` 1.0 contient soit un pourcentage borné, soit un montant fixe
strictement inférieur ou égal au total de la Quote. Une seule `DepositInvoice`
peut en résulter.

---

## DueDatePolicy

Détermine la `DueDate` depuis `IssuedAt`, un nombre de jours et les règles
calendaires supportées. La date effective est figée dans l'Invoice.

---

## PaymentAllocation

```text
AmountReceived
AmountApplied
UnappliedAmount
OverpaymentDisposition?
```

`AmountReceived = AmountApplied + UnappliedAmount`. Toute valeur non appliquée
exige `RefundDue` ou `ClientCredit`.

La même enum `RemainderDisposition` qualifie le reliquat d'une CreditNote
partiellement appliquée.

---

## PublicDocumentProof

Preuve opaque, bornée au document, à une intention et à une échéance. Seule une
empreinte non réversible est persistée. Le secret brut n'apparaît jamais dans un
événement, log ou audit.

---

## DocumentArtifactReference

Référence un rendu immuable par source, version, format, empreinte et instant de
création. Un nouveau rendu crée une nouvelle référence ; il n'écrase jamais un
artefact déjà communiqué.
