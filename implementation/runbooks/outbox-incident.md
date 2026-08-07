---
title: Runbook — Incident outbox backlog
owner: Engineering
last_updated: 2026-08-07
references:
  - ../SEC-TEST-MATRIX.md
  - observability.md
---

# Incident outbox backlog

## Symptômes

- Logs `Outbox backlog above threshold` avec `outbox.pending_count` ≥ seuil
- Délais croissants sur effets asynchrones (analytics, notifications, advisor…)
- Spans Jaeger `outbox.process_pending` avec batch traité = 0 alors que pending > 0

## Seuil par défaut

| Variable | Défaut | Description |
|---|---|---|
| `OUTBOX_BACKLOG_WARNING_THRESHOLD` | `25` | Alerte log warning après chaque cycle `atlas:outbox:process` |

Champs structurés émis :

- `outbox.pending_count`
- `outbox.processed_in_batch`
- `outbox.oldest_pending_age_seconds` (si messages en attente)
- `outbox.backlog_warning_threshold`

## Diagnostic rapide

```sql
SELECT COUNT(*) AS pending,
       MIN(created_at) AS oldest,
       MAX(created_at) AS newest
FROM platform.outbox_messages
WHERE dispatched_at IS NULL;
```

```sql
SELECT event_type, COUNT(*) AS cnt
FROM platform.outbox_messages
WHERE dispatched_at IS NULL
GROUP BY event_type
ORDER BY cnt DESC;
```

Vérifier que le worker outbox tourne :

```bash
php artisan atlas:outbox:process
# ou POST /api/dev/outbox/process en dev
```

## Actions

1. **Confirmer le worker** — cron / commande `atlas:outbox:process` actif.
2. **Identifier le type d'événement bloquant** — requête par `event_type`.
3. **Consulter les logs applicatifs** — erreurs consumer, exceptions inbox.
4. **Augmenter temporairement le batch** si pic transitoire : `atlas:outbox:process --batch=500`.
5. **Escalade** si pending ne diminue pas après 15 min ou si `oldest_pending_age_seconds` > 3600.

## Rollback / mitigation

- Ne pas supprimer manuellement des lignes outbox sans analyse (risque perte d'événements).
- En dernier recours beta : marquer un message poison après reproduction en staging (SEC-TEST-022).

## Gate beta

- [ ] Alerte log warning testée (`OutboxBacklogMonitorTest`)
- [ ] Runbook relu avant publication contrôlée
