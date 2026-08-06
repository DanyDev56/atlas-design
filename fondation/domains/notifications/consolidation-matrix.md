---
id: NTF-CONSOLIDATION
title: Notifications Consolidation Matrix
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - mission.md
  - scope.md
  - notification-policy.md
  - lifecycle.md
  - model.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - relationships.md
  - invariants.md
  - decision-record.md
  - permissions.md
  - events.md
  - api.md
  - integrations.md
  - workflows.md
  - future.md
  - glossary.md
  - commands/README.md
  - processors/README.md
---

# Matrice de consolidation

## Sources canoniques

| Source | Présente | Normalisée | Cohérente | Complète 1.0 |
|---|---:|---:|---:|---:|
| Mission et scope | Oui | Oui | Oui | Oui |
| NotificationPolicy et cycles de vie | Oui | Oui | Oui | Oui |
| Modèle, entités et agrégats | Oui | Oui | Oui | Oui |
| Value Objects et relations | Oui | Oui | Oui | Oui |
| Invariants et décisions | Oui | Oui | Oui | Oui |
| Permissions, commandes et processeurs | Oui | Oui | Oui | Oui |
| Domain Events | Oui | Oui | Oui | Oui |
| API, intégrations et workflows | Oui | Oui | Oui | Oui |
| Glossaire et futur | Oui | Oui | Oui | Oui |

---

## Traçabilité des commandes

| Commande | Autorité | Capacité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `MarkNotificationRead` | Recipient User | `notifications.inbox.mark-read` | 001–003, 011–013, 031–037, 049–058 | `NotificationMarkedRead` | `MarkNotificationReadRequestId` |
| `MarkNotificationUnread` | Recipient User | `notifications.inbox.mark-read` | 001–003, 011–013, 031–037, 049–058 | `NotificationMarkedUnread` | `MarkNotificationUnreadRequestId` |
| `ChangeNotificationPreferences` | User | `notifications.preferences.change` | 001–003, 011–020, 049–058 | `NotificationPreferenceChanged` | `ChangeNotificationPreferencesRequestId` |

## Traçabilité des processeurs

| Processeur | Autorité | Capacité | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `ProcessAdvisorNotificationSignal` | Workload Notifications | `notifications.signals.process` | 001–058 | `NotificationPlanCompleted`, `NotificationCreated`, `NotificationSuperseded`, `NotificationResolved`, `NotificationExpired`, `NotificationDeliveryRequested`, `NotificationDeliverySuppressed`, `NotificationDeliveryCancelled` | `ProcessAdvisorNotificationSignalRequestId` |
| `DispatchNotificationDelivery` | Worker Notifications | `notifications.deliveries.dispatch` | 001–003, 014–030, 038–058 | `NotificationDeliveryAccepted`, `NotificationDeliveryRetryScheduled`, `NotificationDeliveryFailed`, `NotificationDeliverySuppressed`, `NotificationDeliveryCancelled` | `DispatchNotificationDeliveryRequestId` + `ProviderIdempotencyKey` |
| `RecordNotificationDeliveryOutcome` | Provider adapter | `notifications.deliveries.record-outcome` | 001–003, 038–058 | `NotificationDeliveryConfirmed`, `NotificationDeliveryFailed` | `ProviderOutcomeId` + `RecordNotificationDeliveryOutcomeRequestId` |
| `ExpireNotification` | Scheduler ou workload | `notifications.items.expire` | 001–003, 031–039, 044–058 | `NotificationExpired`, `NotificationDeliveryCancelled` | `ExpireNotificationRequestId` |

Les numéros abrégés désignent `NTF-INV-nnn`. Les fiches constituent les sources
normatives complètes.

---

## Couverture NotificationPolicy 1.0

| Signal Advisor | Décision possible |
|---|---|
| `AdvisorOverviewChanged` | Created, Unchanged, Superseded, Resolved, Expired, AllChannelsSuppressed ou SourceIgnored selon ConvergenceKind |
| `RecommendationGenerated` | Aucune planification |

| Canal | Default | Conditions additionnelles |
|---|---|---|
| InApp | Enabled | audience autorisée |
| Email | Disabled | opt-in ImportantOnly, High/Critical, endpoint vérifié et fréquence disponible |

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Source | AdvisorOverview stabilisé, versionné et sérialisé par AdvisorOverviewChanged |
| Audience | Identity, permissions Advisor read et Notifications inbox read |
| Inbox | activée par défaut, personnelle, état lu indépendant |
| Email | désactivé par défaut, opt-in, High/Critical uniquement |
| Confidentialité | endpoint opaque et contenu externe minimal |
| Déduplication | un plan par source/politique, un actif par thread |
| Remplacement | nouvelle racine ; l'ancienne devient Superseded |
| Fréquence | une Accepted par 24 h, sauf une escalade High vers Critical |
| Livraison | Pending, Dispatching, Accepted puis preuve Delivered ou Failed |
| Action | navigation allowlistée, aucune exécution métier |
| Frontières | communications Identity et documents Billing exclus |
| Télémétrie | affichage, clic et conversion hors domaine |

---

## Quality gates Notifications 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Source, lecture exacte et événement stabilisé sont définis.
- [x] Un événement Advisor livré hors ordre ne peut pas faire régresser le topic.
- [x] Audience, permissions et isolation personnelle sont explicites.
- [x] Defaults, opt-in Email et préférences futures sont distingués.
- [x] Déduplication, thread et remplacement sont déterministes.
- [x] La fréquence Email et l'escalade Critical sont bornées.
- [x] NotificationStatus, ReadState et DeliveryStatus restent orthogonaux.
- [x] Accepted et Delivered possèdent preuves et événements distincts.
- [x] Endpoint, contenu, événement et audit excluent l'adresse brute.
- [x] Les trois commandes possèdent autorité, concurrence et idempotence.
- [x] Les quatre processeurs possèdent causalité, reprise et idempotence.
- [x] Tous les Domain Events possèdent un producteur tracé.
- [x] Toutes les capacités utilisées figurent dans le catalogue.
- [x] Les contrats Advisor, Identity, Workspace et fournisseur sont explicites.
- [x] Identity sensible, Billing documentaire et télémétrie restent exclus.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-notifications-docs.sh
```

Notifications 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable`
exige une implémentation, des tests de politique, des tests fournisseur et une
validation produit des contenus et consentements.
