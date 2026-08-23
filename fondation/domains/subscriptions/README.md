---
id: SUB-README-001
title: Subscriptions Domain
status: Draft
owner: Product and Engineering
version: 0.1.0
last_updated: 2026-08-23

references:
  - scope.md
  - ../../decisions/ADR-003-subscriptions-context-ownership.md
  - ../../product/pricing-strategy.md
---

# Subscriptions

`Subscriptions` porte la relation commerciale entre Atlas et un Workspace. Il
ne porte jamais la facturation que ce Workspace adresse à ses propres clients.

Le premier incrément comprend un catalogue candidat versionné, un Trial de
30 jours, une projection d'Entitlements et un gateway factice. Le paiement
réel, les webhooks, la résiliation, le portail et l'enforcement restent fermés.

Le périmètre détaillé est défini dans [`scope.md`](scope.md).
