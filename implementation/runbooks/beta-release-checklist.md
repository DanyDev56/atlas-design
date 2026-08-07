---
title: Checklist — Release beta fermée
owner: Engineering + Product
last_updated: 2026-08-07
references:
  - ../PALIER-3-CLOSURE.md
  - ../SEC-TEST-MATRIX.md
  - backup-restore.md
  - observability.md
  - outbox-incident.md
  - data-retention-beta.md
---

# Checklist release beta

Gate avant ouverture d'une **beta fermée**. Cocher et dater chaque item.

## CI / qualité

- [ ] `make test` vert (83+ tests)
- [ ] CI GitHub `documentation` + `spike` + `oci` verts sur `main`
- [ ] `scripts/check-mvp-reference-fixtures.sh` vert
- [ ] Workflow `release-rehearsal` exécuté (SEC-TEST-023)

## Sécurité (subset beta)

- [ ] SEC-TEST-004 session revoke validé
- [ ] SEC-TEST-006 rate limit auth + public quote
- [ ] SEC-TEST-007 membership revocation mid-session
- [ ] SEC-TEST-013 idempotence documentée (`docs/idempotency.md`)
- [ ] Risques SEC-T résiduels High/Critical acceptés formellement (Product+Security)

## Exploitation

- [ ] `make backup` testé ; rétention dumps documentée
- [ ] Décision backup hors site (SEC-GAP-006) ou acceptation risque beta
- [ ] `OUTBOX_BACKLOG_ALERT_WEBHOOK_URL` configuré en staging (si alerting externe)
- [ ] Runbooks relus : backup, observabilité, outbox incident

## Observabilité (manuel)

- [ ] Spans `atlas-app` visibles dans Jaeger (requête API + outbox)
- [ ] Logs JSON corrélés sans secret (`correlation_id`, pas de token/mot de passe)
- [ ] `LOG_STACK=json_stderr` en environnement beta

## Product / conformité

- [ ] Draft rétention beta validé ou écarts acceptés (SEC-GAP-004)
- [ ] Liste utilisateurs beta + support définis
- [ ] Playground / parcours J1–J3 démontrés sur environnement beta

## Rollback

- [ ] Procédure restore documentée et testée (`make verify-restore`)
- [ ] Tag ou commit de release identifié pour rollback image OCI

---

**Sign-off**

| Rôle | Nom | Date |
|---|---|---|
| Engineering | | |
| Product | | |
| Security | | |
