---
title: Billing Domain
status: Draft
owner: Product
last_updated: 2026-08-05
---

# Billing

## Mission

Le domaine Billing est responsable de tout ce qui concerne la transformation d'une prestation en revenu.

Il couvre :

- les devis ;
- les factures ;
- les acomptes ;
- les paiements ;
- les avoirs ;
- les échéances.

Billing n'est pas responsable :

- des clients ;
- des opportunités ;
- des recommandations ;
- de la comptabilité.

Ces domaines communiquent via leurs contrats publics.

---

## Objectifs

Le domaine doit permettre :

- d'être payé rapidement ;
- d'éviter les erreurs administratives ;
- de garantir une traçabilité parfaite ;
- d'alimenter les autres domaines en données fiables.

---

## Contrats CRM consommés

Lorsqu'un document part d'un Client ou d'une Opportunity, Billing consomme :

```text
getClientBillingContext(workspaceId, clientId)
getOpportunityCommercialContext(workspaceId, opportunityId)
```

Billing valide la complétude requise puis copie un `ClientSnapshot` dans son
propre agrégat. Une modification ultérieure du Client ou de l'Opportunity ne
réécrit jamais un devis ou une facture existante.

`QuoteAccepted` peut déclencher une orchestration demandant `WinOpportunity` à
CRM. Billing ne modifie jamais directement l'Opportunity.
