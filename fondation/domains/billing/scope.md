---
id: BIL-SCOPE
title: Billing Scope
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - model.md
  - integrations.md
  - future.md
---

# Périmètre

## Inclus dans Billing 1.0

### Quote

- brouillon et lignes chiffrées ;
- snapshots Client, Opportunity éventuelle et émetteur ;
- numérotation lors de l'envoi ;
- livraison asynchrone ;
- consultation publique ;
- acceptation, rejet, retrait et expiration ;
- politique d'acompte simple optionnelle.

### Invoice

- création autonome ou depuis une Quote acceptée ;
- `DepositInvoice` ou `FinalInvoice` ;
- brouillon modifiable ;
- émission numérotée et immuable ;
- date d'échéance ;
- consultation et livraison ;
- statut de retard calculé ;
- relance manuelle ;
- corrections de métadonnées non financières strictement bornées.

### Payment

- enregistrement manuel dans une seule Invoice ;
- paiements partiels ;
- trop-perçu explicitement qualifié ;
- reversal complet et traçable ;
- recalcul atomique du solde.

### CreditNote

- brouillon correctif d'une seule Invoice émise ;
- émission numérotée et immuable ;
- application au solde de l'Invoice ;
- montant non appliqué explicitement qualifié.

### Documents

- demande de rendu PDF pour Quote, Invoice et CreditNote ;
- artefact immuable lié à la version émise ;
- accès public par preuve bornée ;
- livraison et relance des Quotes et Invoices déléguées à Communication ;
- téléchargement membre d'une CreditNote, sans envoi dédié en 1.0.

---

## Hors périmètre

| Responsabilité | Propriétaire |
|---|---|
| Client, Contact et Opportunity | `CRM` |
| identité et préférences du Workspace | `Workspace` |
| membres, rôles et permissions | `Identity` |
| transport des documents et relances Billing | Communication de `Billing` |
| notifications produit issues d'Advisor | `Notifications` |
| comptabilité, écritures et déclarations | intégration comptable future |
| réception bancaire et rapprochement | intégration bancaire future |
| recommandations de relance | `Advisor` |

---

## Limites MVP

- une seule devise effective par document ;
- devise initiale égale à la préférence Workspace ;
- un seul acompte et une seule Invoice finale par Quote ;
- un Payment appartient à une seule Invoice ;
- une CreditNote appartient à une seule Invoice ;
- aucune Invoice récurrente ;
- aucun paiement en ligne natif ;
- aucune multi-devise, conversion ou rapprochement bancaire ;
- aucune suppression physique ;
- aucune modification financière après émission.

Les extensions sont classées dans [`future.md`](future.md).
