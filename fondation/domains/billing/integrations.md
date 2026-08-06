---
id: BIL-INTEGRATIONS
title: Billing Integrations
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - api.md
  - events.md
  - workflows.md
  - ../crm/api.md
  - ../workspace/api.md
  - ../identity/api.md
  - ../analytics/integrations.md
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

Analytics consomme les événements supportés puis relit la révision exacte par :

```text
getQuoteAnalyticsFact(workspaceId, quoteId, aggregateVersion)
getInvoiceAnalyticsFact(workspaceId, invoiceId, aggregateVersion)
getPaymentAnalyticsFact(workspaceId, invoiceId, paymentId, aggregateVersion)
getCreditNoteAnalyticsFact(workspaceId, creditNoteId, aggregateVersion)
getBillingHistoryImportManifest(workspaceId, importRunId, manifestVersion)
```

La capacité `billing.analytics-facts.read` est SystemActorOnly. Les faits
excluent données personnelles, contenu de document, références de paiement et
preuves publiques. Advisor consomme les analyses en aval au lieu de recalculer
les agrégats Billing. Aucun de ces domaines ne mute Billing.

Analytics attend `BillingHistoryImportCompleted` et la completion CRM corrélée
avant de reconstruire une génération bornée depuis le manifest. Les agrégats en
cours d'import ne sont pas exposés à sa génération active.

## Hors 1.0

Banque, prestataire de paiement, comptabilité et réseau de facturation
électronique n'ont aucun contrat opérationnel Billing 1.0. L'import CSV initial
est un transfert contrôlé, pas un connecteur synchronisé.

## Garanties

- aucune base partagée entre bounded contexts ;
- appels bornés, retries avec backoff et circuit breaker ;
- outbox transactionnelle et consommateurs idempotents ;
- données personnelles minimales dans les messages ;
- aucune dépendance indisponible ne transforme une donnée non vérifiée en fait.
