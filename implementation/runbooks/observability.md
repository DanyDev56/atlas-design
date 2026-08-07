---
title: Runbook — Observabilité (Palier 3)
owner: Engineering
last_updated: 2026-08-07
references:
  - ../SEC-TEST-MATRIX.md
  - ../../fondation/decisions/ADR-002-mvp-implementation-stack.md
---

# Observabilité

## État Palier 3 (Track A)

| Signal | Statut | Détail |
|---|---|---|
| Corrélation HTTP → outbox | ☑ | `CorrelationIdMiddleware`, colonne `correlation_id` |
| Logs JSON structurés | ☑ | canal `json_stderr`, contexte `correlation_id` |
| Export OTLP (infra) | ☑ | `otel-collector` + Jaeger (profile `observability`) |
| Export OTLP (PHP SDK) | ☑ | `Telemetry`, `HttpTracingMiddleware`, `TraceScope` / outbox |
| Métriques RED / outbox lag | ◐ | `OutboxBacklogMonitor`, logs structurés backlog |
| Alertes et runbooks incident | ◐ | `runbooks/outbox-incident.md` |

## Démarrer la stack observabilité

```bash
make up-observability
# active OTEL_TRACES_EXPORTER=otlp sur le conteneur app
# Jaeger UI : http://localhost:16686
# OTLP HTTP (collector) : http://localhost:4318/v1/traces
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

## OTLP PHP

`make up-observability` exporte les traces via le SDK OpenTelemetry :

- spans HTTP : `HttpTracingMiddleware` (`http.method`, `http.route`, `correlation_id`) ;
- spans outbox : `outbox.process_pending` dans `OutboxProcessor`.
- métriques backlog : `OutboxBacklogMonitor` après chaque cycle outbox (voir `runbooks/outbox-incident.md`).

Variables (`.env` ou Compose) :

```env
OTEL_SERVICE_NAME=atlas-app
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_TRACES_EXPORTER=otlp
```

Sans profile observability, laisser `OTEL_TRACES_EXPORTER=none` (défaut Compose).

### Vérifier dans Jaeger

1. `make up-observability` (recrée le conteneur `app` avec `OTEL_TRACES_EXPORTER=otlp`)
2. Vérifier que `.env` contient `OTEL_TRACES_EXPORTER=otlp`
3. `make serve` — redémarrer le serveur après changement `.env`
4. Playground → parcours API → « Process outbox »
5. Jaeger → service **`atlas-app`** → spans `GET api/...` et `outbox.process_pending`

Si seul le service `jaeger` apparaît, l'export OTLP PHP est désactivé : contrôler
`OTEL_TRACES_EXPORTER` et relancer `make up-observability`.

## Gate beta

- [ ] Spans visibles dans Jaeger pour au moins une requête API et un cycle outbox
- [ ] Logs JSON corrélés consultables sans secret
- [x] Runbook incident + alerte outbox backlog documentés (`runbooks/outbox-incident.md`)
