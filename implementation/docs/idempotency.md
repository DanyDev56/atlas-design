---
title: Catalogue idempotence — handlers MVP
owner: Engineering
last_updated: 2026-08-22
references:
  - ../SEC-TEST-MATRIX.md
---

# Idempotence des commandes (SEC-TEST-013)

Chaque handler mutateur idempotent accepte un `requestId` (en-tête HTTP
`Idempotency-Key` ou UUID généré côté contrôleur). Le fingerprint SHA-256
couvre les paramètres métier ; une réutilisation de clé avec un fingerprint
différent lève `Idempotency conflict.`

## Identity (`identity.idempotency_keys`)

| Handler | Scope | Fingerprint |
|---|---|---|
| `RegisterUserHandler` | `identity.register_user` | email, displayName |
| `RevokeSessionHandler` | `identity.revoke_session` | actorUserId, sessionId |
| `RemoveMembershipHandler` | `identity.remove_membership` | actorUserId, workspaceId, membershipId |
| `BootstrapFirstWorkspaceHandler` | `onboarding.bootstrap_first_workspace` | userId, workspace name |

## CRM (`crm.idempotency_keys`)

| Handler | Scope | Fingerprint |
|---|---|---|
| `CreateClientHandler` | `crm.create_client` | workspaceId, kind, displayName, profile, billingProfile |
| `CreateOpportunityHandler` | `crm.create_opportunity` | workspaceId, clientId, title, amount, currency |
| `QualifyOpportunityHandler` | `crm.qualify_opportunity` | workspaceId, opportunityId, expectedRevision |
| `AddContactHandler` | `crm.add_contact` | workspaceId, clientId, contact fields |
| `ConfirmHistoricalClientsImportHandler` | `crm.confirm_historical_clients_import` | workspaceId, previewId, packageHash, sourceSystem |
| `WinOpportunityFromQuoteHandler` | `crm.win_opportunity_from_quote` | workspaceId, quoteId, opportunityId |

## Billing (`billing.idempotency_keys`)

| Handler | Scope | Fingerprint |
|---|---|---|
| `CreateQuoteHandler` | `billing.create_quote` | workspaceId, clientId, lines, currency |
| `UpdateQuoteDraftHandler` | `billing.update_quote_draft` | workspaceId, quoteId, revision, lines |
| `SendQuoteHandler` | `billing.send_quote` | workspaceId, quoteId |
| `AcceptQuoteHandler` | `billing.accept_quote` | workspaceId, quoteId, revision |
| `CreateFinalInvoiceFromQuoteHandler` | `billing.create_final_invoice_from_quote` | workspaceId, quoteId |
| `IssueInvoiceHandler` | `billing.issue_invoice` | workspaceId, invoiceId |
| `SendInvoiceHandler` | `billing.send_invoice` | workspaceId, invoiceId |
| `RecordPaymentHandler` | `billing.record_payment` | workspaceId, invoiceId, amount, method |
| `ConfirmHistoricalBillingHistoryImportHandler` | `billing.confirm_historical_billing_import` | workspaceId, previewId, packageHash, sourceSystem |

## Analytics / Health / Advisor / Notifications

| Handler | Store | Scope |
|---|---|---|
| `PublishAnalyticsSnapshotHandler` | analytics | `analytics.publish_snapshot` |
| `EvaluateBusinessHealthHandler` | business_health | `business_health.evaluate` |
| `EvaluateRecommendationsHandler` | advisor | `advisor.evaluate_recommendations` |
| `ProcessAdvisorNotificationSignalHandler` | notifications | `notifications.process_advisor_signal` |

## Comportement attendu

- **Replay sûr** : même `requestId` + même fingerprint → même réponse, sans
  nouvel événement outbox ni double effet.
- **Conflit** : même `requestId` + fingerprint différent → `422 Idempotency conflict.`
- **Réponses normalisées** : les handlers Identity normalisent l'ordre des clés
  JSON après lecture PostgreSQL (`RevokeSessionHandler`, `RemoveMembershipHandler`).

## Tests de référence

- `MvpAcceptanceCrossCuttingTest::test_create_client_idempotency_replays_same_result`
- `RevokeSessionTest::test_revoke_session_handler_is_idempotent`
- `MvpJ3EndToEndAcceptanceTest::test_snapshot_publish_is_idempotent_and_outbox_replay_is_safe`
