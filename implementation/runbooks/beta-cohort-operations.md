---
id: RUN-020
title: Beta Cohort Operations
status: In Review
owner: Product and Engineering
version: 0.1.0
last_updated: 2026-08-24

references:
  - beta-research-plan.md
  - backoffice-access.md
  - ../../evolution/blueprint/backoffice-implementation-plan.md
  - ../../evolution/governance/pricing-validation.md
---

# Exploitation de la cohorte beta

## Portée et garanties

Le back-office expose `/backoffice/beta` en lecture seule. La vue contient les
codes `BETA-001` à `BETA-005`, les étapes `E0` à `E6`, les cellules pricing,
les blocages structurés et les décisions. Elle ne charge aucun nom, email,
UUID métier, contenu de document, note libre ou montant.

Les étapes sont reconstruites depuis les sources propriétaires. Le registre
Operations ne conserve que l'affectation à la cohorte, les revues structurées
et la décision pricing. Les cellules pricing, revues et décisions sont
immutables en base. Les écritures web restent coupées ; les commandes ci-dessous
sont des opérations administratives locales, transactionnelles et auditées.

## Inscrire un participant

Identifier au préalable l'UUID d'un utilisateur actif et vérifié, son Workspace
actif et son Membership actif. Ne jamais inscrire ces UUID dans une note ou un
ticket. La cohorte est limitée à cinq participants et un utilisateur ou
Workspace ne peut être inscrit qu'une fois.

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:beta:enroll BETA-001 USER_UUID WORKSPACE_UUID \
  --packaging="Atlas-Solo@1" \
  --segment=primary \
  --channel=recommendation \
  --invited-at="2026-08-24T09:00:00+02:00" \
  --reason="Inscription validée dans la cohorte fermée"
```

Sans `--cell`, Atlas choisit la cellule la moins utilisée parmi `P19`, `P24`
et `P29`. Ne forcer `--cell` que pour appliquer un plan d'affectation approuvé ;
elle ne pourra plus être modifiée.

### Fixtures locales

Pour les tests d'interface uniquement, une commande idempotente crée cinq
comptes vérifiés, cinq Workspaces et une progression E2 à E6 :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:beta:seed-fixtures
```

Les comptes utilisent `beta-001@atlas.test` à `beta-005@atlas.test` et le mot
de passe commun `BetaAtlas2026!`. La commande est refusée hors `local/testing`.
Ces identifiants sont strictement fictifs et ne doivent jamais être réutilisés
sur un environnement externe.

## Enregistrer une revue

Utiliser un jalon parmi `J2`, `J7`, `J14`, `J21`, `J30`. `--blockage` et
`--next-action` acceptent uniquement des codes minuscules structurés ; aucune
note personnelle ou verbatim n'est admis. Le temps de support est cumulatif et
ne peut pas diminuer.

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:beta:review BETA-001 J7 \
  --blockage=onboarding-data \
  --support-minutes=35 \
  --next-action=schedule-data-review \
  --reason="Revue de cohorte J7"
```

## Enregistrer la décision pricing

Une seule décision peut être enregistrée parmi `PAID`, `PREORDERED`,
`TRIAL_COMMITTED`, `DECLINED_PRICE`, `DECLINED_VALUE`, `DECLINED_SCOPE`,
`DECLINED_TRUST` et `INELIGIBLE`. `PAID` exige un abonnement actif vérifiable.
La référence de preuve est opaque et ne contient jamais le verbatim ou
l'identité de la personne.

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:beta:decision BETA-001 TRIAL_COMMITTED \
  --primary-reason=context \
  --preference=Monthly \
  --evidence-ref=research:BETA-001:J30 \
  --reason="Décision recueillie au jalon J30"
```

## Accès et interprétation

- `operations.beta.read` : liste et diagnostic pseudonymisés ;
- `operations.metrics.read-product` : entonnoir, médianes et synthèse pricing ;
- `BACKOFFICE_BETA_BLOCKED_AFTER_DAYS=7` : seuil de stagnation par défaut ;
- un blocage explicite rend immédiatement le participant « à débloquer » ;
- `NoData` signifie que la cohorte est vide, `Unavailable` une erreur de source ;
- un taux sans dénominateur reste non calculable, jamais `0 %`.

## Correction et incident

Ne pas modifier directement une cellule, une revue ou une décision : les
triggers les rendent append-only. En cas d'erreur de saisie, suspendre
l'exploitation du participant concerné, conserver l'audit et ouvrir une
correction explicite revue par Product et Engineering. Tant qu'un workflow de
correction versionné n'est pas livré, toute correction SQL exceptionnelle doit
faire l'objet d'une sauvegarde, d'une approbation et d'une trace d'incident.

## Recette minimale

```bash
./implementation/scripts/run-tests.sh \
  tests/Unit/Operations/BetaCohortQueryHandlerTest.php \
  tests/Integration/Operations/OperatorBetaCohortTest.php

make web-check
```

Vérifier l'absence de nom, email et UUID métier dans les réponses API, le refus
sans grant exact, l'audit des lectures, l'immutabilité des preuves et la
présence des effectifs à côté de chaque pourcentage.
