---
id: LANG-003
title: Naming Rules
status: Stable
owner: Product
version: 1.4
last_updated: 2026-08-05
---

# Règles de nommage

## Concepts métier

Toujours :

Nom

Jamais :

Verbe

---

## Entités

Singulier

Exemple :

Client

Invoice

Workspace

---

## Collections

Pluriel.

clients

workspaces

quotes

---

## Événements

NomEntité + VerbeAuPassé

Exemple :

InvoiceIssued

QuoteAccepted

PaymentRecorded

CreditNoteIssued

AnalyticsSnapshotPublished

BusinessHealthAssessed

RecommendationGenerated

RecommendationCompleted

RecommendationDismissed

RecommendationExpired

---

## API

Toujours :

/clients

/workspaces

/payments

Jamais :

/company

/deals

/bills

---

## Classes

PascalCase.

---

## Variables

camelCase.

---

## Tables SQL

snake_case pluriel.

---

## Colonnes

snake_case singulier.
