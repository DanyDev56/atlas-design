---
id: BIL-AGGREGATES
title: Billing Aggregates
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - relationships.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Vue d'ensemble

| Agrégat | Racine | Contenu |
|---|---|---|
| Quote | `Quote` | lignes, snapshots, accès public, résumé de facturation |
| Invoice | `Invoice` | lignes, Payments, applications de CreditNote, solde |
| CreditNote | `CreditNote` | lignes correctives et application |
| Number Sequence | `DocumentNumberSequence` | prochaine allocation et historique |

---

## Quote

Quote garantit son contenu, sa réponse terminale et l'unicité du plan de
facturation simple. Après `SendQuote`, son contenu est figé ; seule la livraison,
la consultation, la réponse et le résumé de facturation évoluent.

---

## Invoice

Payment est contenu dans Invoice parce qu'il appartient à une seule créance en
1.0. `RecordPayment` et `ReversePayment` modifient atomiquement :

- l'entité Payment ;
- le solde ;
- le statut de règlement ;
- les événements correspondants.

Cette frontière empêche une Invoice temporairement payée sans trace du Payment.

---

## CreditNote

CreditNote reste un agrégat distinct car il possède un numéro et une immutabilité
juridique propres. `ApplyCreditNote` est l'exception multi-agrégats : CreditNote
et Invoice sont verrouillées et commises atomiquement.

`IssueCreditNote` sérialise également l'émission sur l'`InvoiceId` source afin
que deux CreditNotes concurrentes ne puissent pas dépasser son total brut.

---

## DocumentNumberSequence

Les commandes d'envoi ou d'émission allouent un numéro avec la séquence dans une
transaction ou une garantie d'unicité équivalente. Un numéro réservé n'est
jamais réutilisé, même après une panne.

---

## Coordination Quote–Invoice

`CreateDepositInvoiceFromQuote` et `CreateFinalInvoiceFromQuote` modifient Quote
et créent Invoice atomiquement. À défaut de transaction locale, une réservation
durable empêche toute seconde création et le workflow possède une reprise
idempotente.

Les frontières restent dans Billing ; aucun agrégat CRM ou Workspace n'est
inclus dans la transaction.
