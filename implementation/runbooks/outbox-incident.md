---
title: Runbook — Incident outbox backlog
owner: Engineering
last_updated: 2026-08-14
references:
  - ../SEC-TEST-MATRIX.md
  - observability.md
---

# Incident outbox backlog

## Symptômes

- Logs `Outbox backlog above threshold` avec `outbox.pending_count` ≥ seuil
- Logs `Outbox message scheduled for retry` ou `Outbox message dead-lettered`
- Délais croissants sur effets asynchrones (analytics, notifications, advisor…)
- Spans Jaeger `outbox.process_pending` avec batch traité = 0 alors que pending > 0

## Seuil par défaut

| Variable | Défaut | Description |
|---|---|---|
| `OUTBOX_BACKLOG_WARNING_THRESHOLD` | `25` | Alerte log warning après chaque cycle de traitement |
| `OUTBOX_BACKLOG_ALERT_WEBHOOK_URL` | *(vide)* | POST JSON optionnel sur alerte backlog |
| `OUTBOX_MAX_ATTEMPTS` | `5` | Nombre d'échecs avant passage en dead-letter |
| `OUTBOX_RETRY_BASE_SECONDS` | `5` | Délai initial du backoff exponentiel |
| `OUTBOX_RETRY_MAX_SECONDS` | `300` | Plafond du backoff exponentiel |

Champs structurés émis :

- `outbox.pending_count`
- `outbox.processed_in_batch`
- `outbox.oldest_pending_age_seconds` (si messages en attente)
- `outbox.backlog_warning_threshold`
- `outbox.dead_letter_count`

## Diagnostic rapide

```sql
SELECT COUNT(*) FILTER (WHERE failed_at IS NULL) AS pending,
       COUNT(*) FILTER (WHERE failed_at IS NOT NULL) AS dead_lettered,
       MIN(created_at) FILTER (WHERE failed_at IS NULL) AS oldest_pending,
       MAX(created_at) FILTER (WHERE failed_at IS NULL) AS newest_pending
FROM platform.outbox_messages
WHERE dispatched_at IS NULL;
```

```sql
SELECT event_type,
       attempts,
       available_at,
       last_error,
       failed_at
FROM platform.outbox_messages
WHERE dispatched_at IS NULL
ORDER BY created_at;
```

Vérifier que le worker outbox tourne :

```bash
php artisan atlas:outbox:work --batch=100 --sleep=2
# cycle ponctuel de diagnostic :
php artisan atlas:outbox:process --batch=100
# ou POST /api/dev/outbox/process en dev
```

## Actions

1. **Confirmer le worker** — processus `atlas:outbox:work` actif.
2. **Identifier le type d'événement bloquant** — requête par `event_type`, `attempts` et `last_error`.
3. **Consulter les logs applicatifs** — erreurs consumer, exceptions inbox.
4. **Augmenter temporairement le batch** si pic transitoire : `atlas:outbox:process --batch=500`.
5. **Escalade** si pending ne diminue pas après 15 min ou si `oldest_pending_age_seconds` > 3600.

## Message en dead-letter

Un message atteint automatiquement `failed_at` après `OUTBOX_MAX_ATTEMPTS` et
n'est plus revendiqué par le worker. Les messages suivants continuent d'être
traités. `last_error` ne conserve que le type d'exception, jamais son message.

Après correction ou neutralisation de la cause, remettre uniquement l'événement
concerné en file :

```bash
php artisan atlas:outbox:retry 00000000-0000-4000-8000-000000000000
```

La commande remet `attempts` à zéro. Vérifier ensuite les logs, l'inbox du
consumer et l'effet durable avant de traiter un autre dead-letter.

## Rollback / mitigation

- Ne pas supprimer manuellement des lignes outbox sans analyse (risque perte d'événements).
- Ne remettre aucun dead-letter en file avant d'avoir identifié la cause et
  vérifié l'idempotence du consumer en staging (SEC-TEST-027).

## Gate beta

- [ ] Alerte log warning testée (`OutboxBacklogMonitorTest`)
- [ ] Retry, rollback d'effet partiel et dead-letter testés (`OutboxFailureHandlingTest`)
- [ ] Runbook relu avant publication contrôlée
