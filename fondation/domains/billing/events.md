# Domain Events

Les événements Billing décrivent des faits métier passés. Ils ne représentent
ni des commandes, ni de simples notifications techniques.

## Quote

| Événement | Signification |
|---|---|
| `QuoteCreated` | Un devis a été créé. |
| `QuoteSent` | Le devis a été envoyé au client. |
| `QuoteViewed` | Le devis a été consulté par son destinataire. |
| `QuoteAccepted` | Le devis a été accepté. |
| `QuoteRejected` | Le devis a été refusé. |
| `QuoteExpired` | La durée de validité du devis est terminée. |

---

## Invoice

| Événement | Signification |
|---|---|
| `InvoiceCreated` | Une facture brouillon a été créée. |
| `InvoiceIssued` | La facture a été émise et son contenu financier est devenu immuable. |
| `InvoiceViewed` | La facture a été consultée par son destinataire. |
| `InvoicePaid` | Le solde restant dû de la facture est devenu nul. |
| `InvoiceOverdue` | La facture possède encore un solde après sa date d'échéance. |

`InvoicePaid` est une conséquence de l'affectation des paiements. Il ne remplace
pas l'événement qui enregistre chaque paiement.

---

## Payment

| Événement | Signification |
|---|---|
| `PaymentRecorded` | Un paiement a été enregistré dans Billing. |

`PaymentReceived` est réservé à un éventuel fait provenant d'un système
bancaire ou d'un prestataire de paiement. La réception externe ne devient un fait
Billing qu'après validation et enregistrement sous la forme `PaymentRecorded`.

---

## Credit Note

| Événement | Signification |
|---|---|
| `CreditNoteCreated` | Un avoir a été créé. |
| `CreditNoteApplied` | Tout ou partie d'un avoir a été appliqué à une créance. |
