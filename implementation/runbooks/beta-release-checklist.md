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

## Périmètre beta interne

| Élément | Décision |
|---|---|
| Utilisateurs | Beta fermée **interne** — équipe Atlas + proches |
| Support | `beta@atlas-design.fr` *(placeholder — à confirmer avant ouverture externe)* |
| Environnement démo J1–J3 | Local validé (`make serve`, playground) |
| Tag release rollback | `beta-0.1.0` |

## CI / qualité

- [x] `make test` vert (83+ tests)
- [x] CI GitHub `documentation` + `spike` + `oci` verts sur `main`
- [x] `scripts/check-mvp-reference-fixtures.sh` vert
- [x] Workflow `release-rehearsal` exécuté (SEC-TEST-023) — vert sur `main` (`501bf1c`, workflow_dispatch)

## Sécurité (subset beta)

- [x] SEC-TEST-004 session revoke validé
- [x] SEC-TEST-006 rate limit auth + public quote
- [x] SEC-TEST-007 membership revocation mid-session
- [x] SEC-TEST-013 idempotence documentée (`docs/idempotency.md`)
- [x] Routes dev/spike fermées par défaut (`BetaSurfaceHardeningTest`)
- [ ] Jeton de vérification debug désactivé et remise email réelle avant beta externe
- [x] Risques SEC-T résiduels High/Critical acceptés formellement (Product+Security)

## Exploitation

- [x] `make backup` testé ; rétention dumps documentée
- [x] Décision backup hors site (SEC-GAP-006) — **acceptation risque beta** : dumps locaux uniquement, rotation 30 j
- [x] `OUTBOX_BACKLOG_ALERT_WEBHOOK_URL` configuré en staging (si alerting externe) — N/A beta, logs only
- [x] Runbooks relus : backup, observabilité, outbox incident

## Observabilité (manuel)

- [x] Spans `atlas-app` visibles dans Jaeger (requête API + outbox)
- [x] Logs JSON corrélés sans secret (`correlation_id`, pas de token/mot de passe)
- [x] `LOG_STACK=json_stderr` en environnement beta

## Product / conformité

- [x] Draft rétention beta validé (SEC-GAP-004) — `atlas:retention:purge` + schedule quotidien
- [x] Liste utilisateurs beta + support définis — beta interne ; support `beta@atlas-design.fr`
- [x] Playground / parcours J1–J3 démontrés — local (`make serve`, playground)

## Rollback

- [x] Procédure restore documentée et testée (`make verify-restore`)
- [x] Tag ou commit de release identifié pour rollback image OCI — `beta-0.1.0`

---

**Sign-off**

Beta interne : Product et Security portés par la même personne.

| Rôle        | Nom    | Date       |
| ----------- | ------ | ---------- |
| Engineering | Daniel | 2026-08-07 |
| Product     | Daniel | 2026-08-07 |
| Security    | Daniel | 2026-08-07 |
