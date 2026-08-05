---
id: BIL-GLOSSARY
title: Billing Glossary
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - model.md
  - value-objects.md
  - ../../language/glossary.md
---

# Glossaire Billing

| Terme | Définition |
|---|---|
| `Quote` | proposition commerciale chiffrée adressée à un Client |
| `Invoice` | document émis représentant une créance |
| `DepositInvoice` | Invoice d'acompte issue d'une Quote acceptée |
| `FinalInvoice` | Invoice soldant la part facturable restante d'une Quote |
| `Payment` | règlement enregistré et appliqué à une seule Invoice |
| `CreditNote` | document correctif diminuant ou annulant tout ou partie d'une Invoice |
| `DueDate` | date à partir de laquelle un solde impayé devient en retard |
| `OutstandingBalance` | montant de la créance restant après Payments et CreditNotes appliqués |
| `ClientSnapshot` | copie immuable des données Client utilisées par le document |
| `IssuerSnapshot` | copie immuable de l'identité de facturation Workspace |

## Termes à éviter

- Proposal pour Quote ;
- Bill pour Invoice ;
- Receipt pour Payment ;
- Cancellation pour une correction financière directe d'Invoice.

Une Invoice émise est corrigée par `CreditNote`, jamais éditée ou supprimée.
