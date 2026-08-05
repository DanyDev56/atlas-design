---
id: BIL-FUTURE
title: Billing Future Extensions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - decision-record.md
---

# Extensions futures

Ces capacités sont plausibles mais ne modifient aucun contrat Billing 1.0 :

- paiement en ligne et liens de paiement ;
- synchronisation bancaire et rapprochement ;
- exports et connecteurs comptables ;
- facturation récurrente et abonnements ;
- facturation électronique réglementée ;
- taxes internationales et multi-devise ;
- paiements répartis entre plusieurs Invoices ;
- acomptes multiples, jalons et situations de travaux ;
- remboursements et compte de crédit Client ;
- révisions négociées d'une Quote déjà envoyée ;
- relances automatiques configurables ;
- consolidation juridique multi-entités.

Chaque extension devra définir ses agrégats, invariants, permissions,
événements, migrations et interaction avec les documents 1.0 déjà immuables.
