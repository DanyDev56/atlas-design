---
id: BIL-INTEGRATIONS
title: Billing Integrations
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - api.md
  - events.md
  - workflows.md
  - ../crm/api.md
  - ../workspace/api.md
  - ../identity/api.md
---

# Intégrations

## Identity

Toute intention humaine passe par l'autorisation Identity dans le même
Workspace. Les preuves publiques sont validées par Billing et ne deviennent
jamais des permissions de rôle.

## CRM

Billing consomme exclusivement :

```text
getClientBillingContext(workspaceId, clientId)
getOpportunityCommercialContext(workspaceId, opportunityId)
```

Les valeurs courantes deviennent `ClientSnapshot` et `OpportunitySnapshot`
avant finalisation. Après `QuoteAccepted`, un orchestrateur peut appeler
`WinOpportunity` avec la capacité `crm.opportunities.win-from-quote`. Les deux
faits restent distincts et un échec CRM ne révoque jamais l'acceptation.

## Workspace

Billing consomme :

```text
getWorkspaceBillingIdentity(workspaceId)
getWorkspacePreferences(workspaceId)
getWorkspaceAccessContext(workspaceId)
```

L'identité courante devient `IssuerSnapshot`. Une restriction bloque les
intentions ordinaires sans effacer les documents ni empêcher les accès légaux
explicitement autorisés.

## Communication et rendu

Billing place une demande versionnée dans son outbox. Communication choisit le
canal et rapporte la remise au fournisseur ; il ne décide ni de l'émission, ni
de l'acceptation, ni du règlement.

Le service de rendu reçoit un modèle immuable et retourne un
`ArtifactReference` avec hash. Une nouvelle présentation ne modifie pas le fait
financier source.

## Horloge et scheduler

Le scheduler propose `ExpireQuote` et `MarkInvoiceOverdue` avec une preuve
d'horloge. Billing relit l'agrégat et reste seul juge des préconditions.

## Analytics et Advisor

Ils consomment notamment `QuoteSent`, `QuoteViewed`, `QuoteAccepted`,
`QuoteRejected`, `InvoiceIssued`, `InvoiceOverdue`, `InvoiceBalanceChanged`,
`InvoiceSettled` et `PaymentRecorded`. Ils ne mutent aucun agrégat Billing.

## Hors 1.0

Banque, prestataire de paiement, comptabilité et réseau de facturation
électronique n'ont aucun contrat opérationnel Billing 1.0.

## Garanties

- aucune base partagée entre bounded contexts ;
- appels bornés, retries avec backoff et circuit breaker ;
- outbox transactionnelle et consommateurs idempotents ;
- données personnelles minimales dans les messages ;
- aucune dépendance indisponible ne transforme une donnée non vérifiée en fait.
