---
id: BIL-DECISIONS
title: Billing Decision Record
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - mission.md
  - scope.md
  - model.md
  - invariants.md
---

# Registre des décisions

| ID | Décision | Conséquence 1.0 |
|---|---|---|
| `BIL-ADR-001` | Billing est propriétaire des documents financiers, pas du Client ni de l'émetteur. | CRM et Workspace sont lus par contrats publics puis snapshotés. |
| `BIL-ADR-002` | Les racines sont Quote, Invoice, CreditNote et DocumentNumberSequence. | Chaque cohérence transactionnelle possède une frontière explicite. |
| `BIL-ADR-003` | Payment est une entité de l'agrégat Invoice. | L'application et le recalcul du solde sont atomiques en 1.0. |
| `BIL-ADR-004` | Le statut documentaire est séparé du règlement, du retard et de la livraison. | Une Invoice émise peut être partielle, en retard et en cours d'envoi sans statut combinatoire. |
| `BIL-ADR-005` | Les snapshots deviennent immuables lors de la finalisation ou de l'émission. | Les documents historiques ne changent pas avec CRM ou Workspace. |
| `BIL-ADR-006` | La numérotation est allouée par séquence, non réutilisable et auditée. | Un échec après allocation laisse une trace plutôt que de recycler un numéro. |
| `BIL-ADR-007` | Les calculs monétaires sont exacts et gouvernés par une politique versionnée. | Les totaux sont reproductibles indépendamment du canal d'affichage. |
| `BIL-ADR-008` | Une Quote acceptée, rejetée, retirée ou expirée est terminale. | Toute renégociation crée une nouvelle Quote. |
| `BIL-ADR-009` | La livraison est asynchrone et distincte du cycle métier. | La demande et la remise au fournisseur produisent des faits différents. |
| `BIL-ADR-010` | Une Quote acceptée mémorise au plus un dépôt et une facture finale. | La double facturation est bloquée transactionnellement. |
| `BIL-ADR-011` | Le dépôt 1.0 est simple : un seul document avant une seule facture finale. | Les acomptes multiples et situations de travaux restent futurs. |
| `BIL-ADR-012` | Un Payment erroné est inversé, jamais édité. | L'historique explique chaque variation de solde. |
| `BIL-ADR-013` | Une correction financière après émission passe par CreditNote. | `CorrectInvoiceMetadata` est limité à une liste non financière. |
| `BIL-ADR-014` | Le retard est une propriété dérivée de l'échéance et du solde. | Le scheduler matérialise le fait sans devenir propriétaire de l'état financier. |
| `BIL-ADR-015` | Le PDF est un artefact d'une version immuable. | Le modèle de domaine, non le fichier, reste la source canonique. |
| `BIL-ADR-016` | Billing 1.0 n'est ni un grand livre ni un moteur bancaire. | Comptabilité, rapprochement et paiement en ligne restent hors scope. |
