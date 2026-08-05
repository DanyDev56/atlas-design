---
id: BIL-CONSOLIDATION
title: Billing Consolidation Matrix
status: In Review
owner: Product
version: 1.1.0
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
| `CreateQuote` | Membre autorisé | `billing.quotes.create` | 001–008, 010, 035, 037–038 | `QuoteCreated` | `CreateQuoteRequestId` |
| `UpdateQuoteDraft` | Membre autorisé | `billing.quotes.update-draft` | 001, 004–006, 010–011, 035–038 | `QuoteDraftUpdated` | `UpdateQuoteDraftRequestId` |
| `SendQuote` | Membre autorisé | `billing.quotes.send` | 001, 004–013, 032–038 | `QuoteSendRequested` | `SendQuoteRequestId` |
| `ConfirmQuoteSent` | Système | `billing.quotes.confirm-sent` | 001, 010, 034, 036–038 | `QuoteSent` | `ConfirmQuoteSentRequestId` |
| `RecordQuoteViewed` | Passerelle publique | `billing.quotes.record-view` | 001, 010, 013, 033, 036–038 | `QuoteViewed` | `RecordQuoteViewedRequestId` |
| `AcceptQuote` | Preuve publique | Capacité intrinsèque | 001, 010, 013–016, 033, 036–038 | `QuoteAccepted` | `AcceptQuoteRequestId` |
| `RejectQuote` | Preuve publique | Capacité intrinsèque | 001, 010, 013, 033, 036–038 | `QuoteRejected` | `RejectQuoteRequestId` |
| `WithdrawQuote` | Membre autorisé | `billing.quotes.withdraw` | 001, 010, 013, 033, 035–038 | `QuoteWithdrawn` | `WithdrawQuoteRequestId` |
| `ExpireQuote` | Scheduler | `billing.quotes.expire` | 001, 010, 013–014, 033, 036–038 | `QuoteExpired` | `ExpireQuoteRequestId` |
| `CreateInvoice` | Membre autorisé | `billing.invoices.create` | 001–008, 017, 035, 037–038 | `InvoiceCreated` | `CreateInvoiceRequestId` |
| `CreateDepositInvoiceFromQuote` | Membre autorisé | `billing.invoices.create` | 001, 004–008, 015–017, 035–038 | `InvoiceCreated` | `CreateDepositInvoiceFromQuoteRequestId` |
| `CreateFinalInvoiceFromQuote` | Membre autorisé | `billing.invoices.create` | 001, 004–008, 015–017, 035–038 | `InvoiceCreated` | `CreateFinalInvoiceFromQuoteRequestId` |
| `UpdateInvoiceDraft` | Membre autorisé | `billing.invoices.update-draft` | 001, 004–006, 016–018, 035–038 | `InvoiceDraftUpdated` | `UpdateInvoiceDraftRequestId` |
| `DiscardInvoiceDraft` | Membre autorisé | `billing.invoices.discard` | 001, 003, 016–018, 035–038 | `InvoiceDiscarded` | `DiscardInvoiceDraftRequestId` |
| `IssueInvoice` | Membre autorisé | `billing.invoices.issue` | 001, 004–009, 017–022, 032, 035–038 | `InvoiceIssued` | `IssueInvoiceRequestId` |
| `CorrectInvoiceMetadata` | Membre autorisé | `billing.invoices.correct-metadata` | 001, 003, 008, 018, 032, 035–038 | `InvoiceMetadataCorrected` | `CorrectInvoiceMetadataRequestId` |
| `SendInvoice` | Membre autorisé | `billing.invoices.send` | 001, 018, 032–038 | `InvoiceDeliveryRequested` | `SendInvoiceRequestId` |
| `ConfirmInvoiceSent` | Système | `billing.invoices.confirm-sent` | 001, 018, 034, 036–038 | `InvoiceSent` | `ConfirmInvoiceSentRequestId` |
| `RecordInvoiceViewed` | Passerelle publique | `billing.invoices.record-view` | 001, 017, 033, 036–038 | `InvoiceViewed` | `RecordInvoiceViewedRequestId` |
| `MarkInvoiceOverdue` | Scheduler | `billing.invoices.mark-overdue` | 001, 020–022, 031, 036–038 | `InvoiceOverdue` | `MarkInvoiceOverdueRequestId` |
| `RequestInvoiceReminder` | Membre autorisé | `billing.invoices.remind` | 001, 018, 020–022, 032, 034–038 | `InvoiceReminderRequested` | `RequestInvoiceReminderRequestId` |
| `RecordPayment` | Membre autorisé | `billing.payments.record` | 001–005, 020–026, 031, 035–038 | `PaymentRecorded`, `PaymentAppliedToInvoice`, `InvoiceBalanceChanged`, `InvoiceSettled`, `InvoicePaid` | `RecordPaymentRequestId` |
| `ReversePayment` | Membre autorisé | `billing.payments.reverse` | 001, 003, 020–026, 031, 035–038 | `PaymentReversed`, `PaymentApplicationReversed`, `InvoiceBalanceChanged`, `InvoiceSettlementReopened` | `ReversePaymentRequestId` |
| `CreateCreditNote` | Membre autorisé | `billing.credit-notes.create` | 001–008, 027–030, 035, 037–038 | `CreditNoteCreated` | `CreateCreditNoteRequestId` |
| `UpdateCreditNoteDraft` | Membre autorisé | `billing.credit-notes.update-draft` | 001, 004–006, 027–030, 035–038 | `CreditNoteDraftUpdated` | `UpdateCreditNoteDraftRequestId` |
| `DiscardCreditNoteDraft` | Membre autorisé | `billing.credit-notes.discard` | 001, 003, 028, 035–038 | `CreditNoteDiscarded` | `DiscardCreditNoteDraftRequestId` |
| `IssueCreditNote` | Membre autorisé | `billing.credit-notes.issue` | 001, 004–009, 027–030, 032, 035–038 | `CreditNoteIssued` | `IssueCreditNoteRequestId` |
| `ApplyCreditNote` | Membre autorisé | `billing.credit-notes.apply` | 001, 004, 020–022, 027–031, 035–038 | `CreditNoteAppliedToInvoice`, `InvoiceBalanceChanged`, `InvoiceSettled` | `ApplyCreditNoteRequestId` |

Les numéros abrégés désignent `BIL-INV-nnn`. Les fiches constituent la source
normative complète ; un événement conditionnel n'est publié que si son fait se
produit réellement.

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Documents | Quote, Invoice et CreditNote distincts |
| Paiement | entité interne à Invoice |
| Numérotation | séquences uniques, auditées et non réutilisables |
| Historique | snapshots CRM et Workspace figés |
| Invoice | statut documentaire séparé du règlement et du retard |
| Quote | réponse terminale ; renégociation par nouvelle Quote |
| Dépôt | au plus un dépôt puis une finale par Quote |
| Correction financière | CreditNote, jamais édition de l'Invoice émise |
| Livraison | asynchrone et distincte de la réception humaine |
| PDF | artefact d'une version immuable |
| Règlement erroné | inversion complète, jamais édition |
| Comptabilité et banque | hors scope 1.0 |

---

## Quality gates Billing 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Les quatre agrégats et leurs frontières sont documentés.
- [x] Les cycles Quote, Invoice, Payment et CreditNote sont fermés.
- [x] Les calculs, arrondis, snapshots et numéros possèdent des invariants.
- [x] Toutes les commandes figurent dans le catalogue et dans une fiche.
- [x] Chaque commande documente autorité, concurrence et idempotence.
- [x] Tous les Domain Events possèdent un producteur tracé.
- [x] Toutes les permissions utilisées figurent dans le catalogue.
- [x] Les contrats CRM et Workspace sont symétriques.
- [x] Les contrats Analytics exposent des révisions financières minimales et sans PII.
- [x] L'émission est distincte de la livraison et de l'ouverture.
- [x] Les paiements partiels, trop-perçus, inversions et avoirs sont couverts.
- [x] Les références inter-workspaces sont refusées.
- [x] Les extensions futures ne contaminent pas le contrat 1.0.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-billing-docs.sh
```

Billing 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable` exige une
implémentation et des tests de domaine conformes.
