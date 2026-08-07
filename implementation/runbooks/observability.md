---
title: Runbook — Observabilité (Palier 3)
owner: Engineering
last_updated: 2026-08-07
references:
  - ../SEC-TEST-MATRIX.md
  - ../../fondation/decisions/ADR-002-mvp-implementation-stack.md
---

# Observabilité

## État Palier 3 (Track A — lot 1)

| Signal | Statut | Détail |
|---|---|---|
| Corrélation HTTP → outbox | ☑ | `CorrelationIdMiddleware`, colonne `correlation_id` |
| Logs JSON structurés | ☑ | canal `json_stderr`, contexte `correlation_id` |
| Export OTLP (infra) | ☑ | `otel-collector` + Jaeger en Compose |
| Export OTLP (PHP SDK) | ◻ | Prochain lot Track A |
| Métriques RED / outbox lag | ◻ | Prochain lot Track A |
| Alertes et runbooks incident | ◻ | SEC-GAP-008 |

## Démarrer la stack observabilité

```bash
make up-observability
# Jaeger UI : http://localhost:16686
# OTLP HTTP  : http://localhost:4318
```

Services : `jaeger` (v2, image `jaegertracing/jaeger`), `otel-collector` (profile `observability`).

## Logs structurés

Dans `.env` :

```env
LOG_CHANNEL=stack
LOG_STACK=json_stderr
LOG_LEVEL=info
```

Chaque requête API enrichit le contexte Monolog avec `correlation_id` (identique
à l'en-tête `X-Correlation-Id`).

Exemple de ligne log :

```json
{"message":"…","context":{"correlation_id":"…"},"level":200,"channel":"stderr"}
```

## Tracer une requête jusqu'à l'outbox

1. Appeler une route API avec `X-Correlation-Id: <uuid>`.
2. Consulter la réponse — même en-tête renvoyé.
3. Interroger PostgreSQL :

```sql
SELECT id, event_type, correlation_id, created_at
FROM platform.outbox_messages
WHERE correlation_id = '<uuid>'
ORDER BY created_at;
```

## OTLP (prochain lot)

Variables préparées dans `.env.example` :

```env
OTEL_SERVICE_NAME=atlas-app
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_TRACES_EXPORTER=otlp
```

Brancher le SDK OpenTelemetry PHP sur le middleware HTTP et le processeur outbox
pour fermer l'écart SPIKE-CLOSURE / MVP-CLOSURE DoD §4.

## Gate beta

- [ ] Spans visibles dans Jaeger pour au moins une requête API et un cycle outbox
- [ ] Logs JSON corrélés consultables sans secret
- [ ] Runbook incident + alerte outbox backlog documentés
