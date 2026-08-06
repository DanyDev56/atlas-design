---
id: BIL-PERMISSIONS
title: Billing Permissions
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - scope.md
  - invariants.md
  - commands/README.md
  - ../identity/permissions.md
---

# Permissions

Billing définit les capacités ; Identity les enregistre, les affecte aux rôles
et résout leur efficacité dans un Workspace.

## Quote

| Clé | Intention |
|---|---|
| `billing.quotes.read` | Consulter les Quotes et leurs artefacts. |
| `billing.quotes.create` | Créer une Quote Draft. |
| `billing.quotes.update-draft` | Modifier une Quote Draft. |
| `billing.quotes.send` | Finaliser et demander l'envoi d'une Quote. |
| `billing.quotes.withdraw` | Retirer une Quote en cours d'envoi ou envoyée. |

## Invoice

| Clé | Intention |
|---|---|
| `billing.invoices.read` | Consulter les Invoices, soldes et artefacts. |
| `billing.invoices.create` | Créer une Invoice Draft, autonome ou issue d'une Quote. |
| `billing.invoices.update-draft` | Modifier une Invoice Draft. |
| `billing.invoices.discard` | Abandonner une Invoice Draft. |
| `billing.invoices.issue` | Émettre légalement une Invoice. |
| `billing.invoices.correct-metadata` | Corriger une métadonnée non financière autorisée. |
| `billing.invoices.send` | Demander la livraison d'une Invoice émise. |
| `billing.invoices.remind` | Demander manuellement une relance. |

## Payment

| Clé | Intention |
|---|---|
| `billing.payments.read` | Consulter les Payments d'une Invoice. |
| `billing.payments.record` | Enregistrer et appliquer un Payment manuel. |
| `billing.payments.reverse` | Inverser un Payment erroné. |

## CreditNote

| Clé | Intention |
|---|---|
| `billing.credit-notes.read` | Consulter les CreditNotes et leurs artefacts. |
| `billing.credit-notes.create` | Créer une CreditNote Draft. |
| `billing.credit-notes.update-draft` | Modifier une CreditNote Draft. |
| `billing.credit-notes.discard` | Abandonner une CreditNote Draft. |
| `billing.credit-notes.issue` | Émettre une CreditNote. |
| `billing.credit-notes.apply` | Appliquer une CreditNote à son Invoice source. |

## Historique initial

| Clé | Intention |
|---|---|
| `billing.history.import` | Confirmer un import historique financier borné, critique et audité. |

Les permissions de mutation impliquent la permission `read` de leur ressource.
Le rôle owner actif conserve toutes les permissions Billing actives selon la
politique produit du Workspace.

`billing.history.import` exige la lecture des Clients mappés, une
prévisualisation confirmée et un step-up selon la politique de sécurité. Elle
n'accorde aucune capacité d'émission ou de communication.

## Capacités SystemActorOnly

| Clé | Usage |
|---|---|
| `billing.quotes.confirm-sent` | Confirmer la remise d'une Quote au fournisseur de livraison. |
| `billing.quotes.record-view` | Matérialiser une ouverture publique vérifiée. |
| `billing.quotes.expire` | Expirer les Quotes arrivées à échéance. |
| `billing.invoices.confirm-sent` | Confirmer la remise d'une Invoice au fournisseur. |
| `billing.invoices.record-view` | Matérialiser une ouverture publique vérifiée. |
| `billing.invoices.mark-overdue` | Matérialiser le passage en retard. |
| `billing.analytics-facts.read` | Lire une révision financière minimale pour Analytics. |

Ces clés ne peuvent pas être accordées à un rôle de Workspace.

## Autorités intrinsèques

Voir, accepter ou rejeter une Quote publique et voir une Invoice publique ne
reposent pas sur un rôle. Elles exigent une `PublicDocumentProof` valide et ne
donnent accès qu'à la capacité, au document et à la durée encodés.
