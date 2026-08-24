---
id: RUN-019
title: Back-office Operator Access
status: In Review
owner: Engineering and Security
version: 0.10.0
last_updated: 2026-08-24

references:
  - ../../fondation/decisions/ADR-004-operator-control-plane.md
  - ../../evolution/blueprint/backoffice.md
  - ../../evolution/blueprint/backoffice-implementation-plan.md
  - beta-cohort-operations.md
  - backoffice-operations.md
  - support-compliance-operations.md
  - ../SEC-TEST-MATRIX.md
---

# Accès opérateur au back-office

## Portée actuelle

Le back-office local fournit désormais une vue d'ensemble `/backoffice`, une
surface de sécurité `/backoffice/security` et des registres métier minimisés. Il sépare les
sessions opérateur des sessions Workspace, stocke
les jetons uniquement sous forme hachée et audite provisioning, refus,
connexion, MFA, step-up, consultation du contexte et révocation. Un trigger
PostgreSQL rend le registre d'audit append-only, y compris face à une mutation
accidentelle.

La vue d'ensemble lit les registres techniques Outbox et Emails, la projection
de cohorte beta, les abonnements/webhooks, les métriques HTTP RED agrégées, les
heartbeats runtime, les états d'alerte et les résultats de
sauvegarde/restauration, ainsi que les registres Support et Conformité. Elle expose
des compteurs et des listes paginées, jamais les payloads, erreurs brutes,
adresses destinataires, contenus ou identifiants fournisseur. Les cartes
Support et Demandes de données reflètent désormais leurs projections durables ;
une source attendue en erreur devient `Unavailable`, jamais zéro.

Le mode sûr reste la lecture seule. Quatre mutations web bornées sont livrées : la
gestion non destructive d'un dossier Support, la révocation d'un jeton de
session Operator identifié, la remise en file d'une dead-letter Outbox puis la
réconciliation Stripe d'un abonnement explicitement sélectionné. Elles exigent deux flags explicites, leur permission
dédiée et un step-up récent. L'activation Support est détaillée dans
[`support-compliance-operations.md`](support-compliance-operations.md).

La MFA actuelle utilise TOTP. Le secret est chiffré avec la clé applicative, un
code temporel ne peut pas être rejoué et huit codes de récupération à usage
unique sont créés lors de chaque enrôlement ou rotation. TOTP réduit fortement
le risque lié au vol du mot de passe mais n'est pas résistant au phishing.

Le mode mot de passe seul est strictement réservé à `local` et `testing`. TOTP
reste également refusé hors de ces environnements par défaut : son ouverture
externe exige un réseau borné et une acceptation explicite du risque. Ne jamais
exposer le mode mot de passe seul ou la MFA TOTP par Quick Tunnel.

## Activation locale

Ajouter dans `implementation/app/.env` :

```dotenv
BACKOFFICE_ENABLED=true
BACKOFFICE_ALLOW_PASSWORD_ONLY_LOCAL=false
BACKOFFICE_REQUIRE_MFA=true
BACKOFFICE_ALLOW_TOTP_EXTERNAL=false
BACKOFFICE_READ_ONLY=true
BACKOFFICE_ACTIONS_ENABLED=false
BACKOFFICE_SESSION_MINUTES=30
BACKOFFICE_STEP_UP_MINUTES=10
BACKOFFICE_TOTP_ISSUER="Atlas Back-office"
```

Puis appliquer les migrations et purger un éventuel cache de configuration :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan migrate
docker compose -f implementation/docker-compose.yml exec app php artisan config:clear
```

## Provisionner un opérateur

Le compte doit déjà exister, être actif et avoir une adresse vérifiée. Aucun
formulaire public, rôle Owner ou invitation Workspace ne crée un grant.

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:grant demo@atlas.test \
  --reason="Accès opérateur local pour recette"
```

Sans `--permissions`, le grant reçoit uniquement :

- `operations.backoffice.access` ;
- `operations.dashboard.read`.

Une expiration peut être imposée avec `--expires=2026-08-24T18:00:00+02:00`.
Les permissions supplémentaires sont passées par une liste séparée par des
virgules et sont rejetées si elles ne figurent pas au catalogue canonique.

Pour ouvrir les registres techniques et la cohorte depuis la vue générale :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:grant demo@atlas.test \
  --permissions="operations.backoffice.access,operations.dashboard.read,operations.outbox.read,operations.outbox.retry,operations.email.read,operations.subscriptions.read,operations.subscriptions.reconcile,operations.beta.read,operations.metrics.read-product,operations.support.read,operations.compliance.read,operations.support.manage,operations.sessions.read,operations.sessions.revoke,operations.exports.request,operations.exports.approve,operations.exports.download" \
  --reason="Recette locale du dashboard opérateur"
```

`operations.beta.read` ouvre la liste pseudonymisée et son diagnostic borné ;
`operations.metrics.read-product` ouvre l'entonnoir agrégé. Sans ces grants de
détail, les cartes restent visibles mais les liens et API correspondantes sont
refusés. Chaque refus et chaque consultation autorisée sont audités.

`operations.subscriptions.read` ouvre les abonnements et webhooks séparés par
environnement. Les écrans runtime et continuité restent couverts par
`operations.dashboard.read`. Leur exploitation est détaillée dans
[`backoffice-operations.md`](backoffice-operations.md).
`operations.subscriptions.reconcile` ajoute uniquement la comparaison et la
correction locale d'un abonnement Stripe ciblé ; elle ne modifie jamais Stripe
et reste sans effet tant que les flags d'action et le step-up ne sont pas actifs.

`operations.support.read` et `operations.compliance.read` ouvrent les registres
pseudonymisés correspondants sur `/backoffice/support`. Leur qualification et
leurs limites non destructives sont détaillées dans
[`support-compliance-operations.md`](support-compliance-operations.md).
`operations.support.manage` n'a d'effet que lorsque
`BACKOFFICE_READ_ONLY=false` et `BACKOFFICE_ACTIONS_ENABLED=true` ; accorder la
permission seule ne contourne jamais ces verrous.

Les permissions `operations.exports.request`, `operations.exports.approve` et
`operations.exports.download` sont volontairement séparées. Un même grant peut
les contenir pour l'exploitation, mais le serveur refuse toujours qu'une même
identité Operator demande puis approuve un export. Le détail du chiffrement, de
l'expiration et de la recette à deux comptes se trouve dans
[`support-compliance-operations.md`](support-compliance-operations.md).

`operations.sessions.read` ouvre le registre pseudonymisé sur
`/backoffice/security`. `operations.sessions.revoke` permet uniquement de
préparer et confirmer la révocation d'une autre session Operator active. Elle
ne révoque ni le grant, ni le facteur MFA, ni une session Workspace.

`operations.outbox.retry` complète `operations.outbox.read` sans révéler le
payload ni l'erreur brute. Elle autorise uniquement la remise en attente d'un
message actuellement en dead-letter ; le worker reste seul responsable de son
traitement.

## Enrôler ou renouveler la MFA

Après le grant, créer le facteur depuis la console d'administration :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:mfa:enroll demo@atlas.test \
  --reason="Enrôlement MFA initial"
```

La commande affiche une URI `otpauth://`, le secret manuel et huit codes de
récupération. Ces valeurs ne sont affichées qu'une fois :

1. ajouter l'URI ou le secret dans l'application d'authentification ;
2. conserver les codes de récupération hors de l'application et du dépôt ;
3. ne jamais copier ces valeurs dans un ticket, un log ou une capture ;
4. vérifier la connexion avec un code à six chiffres actuel.

Relancer la commande effectue une rotation complète, remplace les codes de
récupération et révoque immédiatement toutes les sessions opérateur existantes.

Ouvrir ensuite `http://localhost:8000/backoffice/login` avec les identifiants
du compte et le code MFA. Un jeton `/app` ne fonctionne pas sur
`/api/operator`, et le jeton opérateur ne fonctionne pas sur
`/api/auth/session/context`.

La connexion MFA produit un step-up valide pendant dix minutes par défaut. Le
shell affiche son expiration et permet de le renouveler. Toute future route
sensible devra appliquer `RequireRecentOperatorStepUpMiddleware` côté serveur ;
l'état visuel seul n'accorde aucune autorité.

### Mode local exceptionnel sans MFA

Pour une recette locale courte uniquement :

```dotenv
BACKOFFICE_ALLOW_PASSWORD_ONLY_LOCAL=true
BACKOFFICE_REQUIRE_MFA=false
```

Ce mode est refusé automatiquement en staging et production. Un opérateur déjà
enrôlé doit toujours fournir son code MFA, même lorsque ce mode local est actif.

## Révoquer une session ciblée

Pour la recette locale, conserver les deux verrous d'action dans cet état :

```dotenv
BACKOFFICE_READ_ONLY=false
BACKOFFICE_ACTIONS_ENABLED=true
```

Après rechargement du contexte opérateur, ouvrir `/backoffice/security`. Le
registre expose uniquement une référence `SES-*`, une référence opérateur
opaque, l'état d'authentification et les dates utiles. Il ne renvoie ni token,
UUID utilisateur, email, adresse IP ou user-agent. La session courante est
signalée et ne peut pas être révoquée par cette action ; utiliser Déconnexion.

La révocation d'une autre session exige :

- `operations.sessions.read` pour voir le registre ;
- `operations.sessions.revoke` pour agir ;
- un step-up encore valide ;
- une prévisualisation, un motif structuré, une révision attendue et une clé
  d'idempotence stable.

Un rejeu identique ne révoque rien une seconde fois. Une clé réutilisée avec un
autre contenu, une révision périmée, une session cible déjà inactive ou une
autorité concurrentement retirée est refusée. La mutation, sa preuve
d'idempotence et l'audit de succès sont atomiques. Les sessions Operator
révoquées ou expirées et les preuves d'idempotence sont purgées selon les
durées `RETENTION_SESSIONS_DAYS` et `RETENTION_IDEMPOTENCY_DAYS`, 30 jours par
défaut.

En incident global ou si l'identité opérateur elle-même est compromise, ne pas
utiliser cette action unitaire : couper `BACKOFFICE_ACTIONS_ENABLED`, purger la
configuration, puis utiliser la commande de révocation complète ci-dessous.

## Reprendre une dead-letter ciblée

Ouvrir `/backoffice/outbox`, filtrer sur « Dead-letter », puis sélectionner
« Préparer ». La confirmation exige :

- `operations.outbox.read` pour consulter le registre ;
- `operations.outbox.retry` pour préparer et confirmer ;
- les deux flags d'action actifs et un step-up encore valide ;
- un motif structuré attestant que la cause a été corrigée ou écartée ;
- la prévisualisation exacte et une clé d'idempotence stable.

La requête web n'exécute jamais le message. Elle remet atomiquement la ligne en
`Pending`, réinitialise `attempts`, `last_error` et `failed_at`, puis laisse le
worker Outbox la prendre au cycle suivant. Les consommateurs déjà validés dans
`platform.inbox_receipts` ne sont pas rejoués. En revanche, une remise externe
dont le succès était incertain avant l'échec peut produire un doublon chez le
destinataire : vérifier le fournisseur avant de confirmer.

Une dead-letter modifiée depuis la prévisualisation, déjà reprise ou distribuée
est refusée. Le serveur recoupe aussi la session, le grant, la permission et le
step-up dans la transaction. La remise en file, sa preuve d'idempotence et
l'audit de succès sont atomiques ; si l'audit échoue, la dead-letter reste
inchangée. Couper `BACKOFFICE_ACTIONS_ENABLED` arrête immédiatement les nouvelles
prévisualisations et confirmations sans désactiver la lecture.

La commande `atlas:outbox:retry` reste le chemin runbook hors UI. Elle doit être
réservée à un incident contrôlé, car elle ne porte pas le contexte opérateur ni
la prévisualisation web.

## Révoquer immédiatement

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:revoke demo@atlas.test \
  --reason="Fin de recette locale"
```

La commande révoque le grant et toutes ses sessions opérateur actives dans la
même transaction. Elle ne révoque pas les sessions Workspace du compte.

Pour désactiver uniquement le facteur et révoquer les sessions opérateur :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:mfa:disable demo@atlas.test \
  --reason="Facteur perdu ou compromis"
```

Avec `BACKOFFICE_REQUIRE_MFA=true`, aucune nouvelle connexion n'est possible
avant un nouvel enrôlement.

## Vérifications minimales

```bash
./implementation/scripts/run-tests.sh \
  tests/Unit/Operations/OperatorPermissionCatalogTest.php \
  tests/Unit/Operations/TotpAuthenticatorTest.php \
  tests/Unit/Operations/OperationsOverviewQueryHandlerTest.php \
  tests/Unit/Operations/BetaCohortQueryHandlerTest.php \
  tests/Integration/Operations/OperatorAccessFoundationTest.php \
  tests/Integration/Operations/OperatorMfaTest.php \
  tests/Integration/Operations/OperatorOverviewTest.php \
  tests/Integration/Operations/OperationsAlertsTest.php \
  tests/Integration/Operations/OperatorBetaCohortTest.php \
  tests/Integration/Operations/SupportComplianceTest.php \
  tests/Integration/Operations/SupportCaseManagementTest.php \
  tests/Integration/Operations/OperatorSessionManagementTest.php \
  tests/Integration/Operations/OperatorOutboxRetryTest.php \
  tests/Integration/Retention/RetentionPurgerTest.php

make web-check
```

Attendus : séparation d'audience, deny-by-default, révocation immédiate,
absence du jeton et du secret MFA bruts en base, anti-rejeu TOTP, codes de
récupération consommés une seule fois, step-up borné, séparation entre
révocation ciblée, logout et révocation complète, audit présent, purge bornée et
build frontend valide.

## Conditions avant une cible externe

Ne pas activer le back-office hors local tant que les éléments suivants ne sont
pas livrés et recettés :

- authentification résistante au phishing, ou décision de risque temporaire
  explicitement approuvée pour TOTP sur un accès réseau borné ;
- politique de provisioning, rotation, break-glass et revue des grants ;
- rétention et accès à l'audit privilégié ;
- collecte d'alertes et journalisation centralisée sans donnée sensible ;
- gates des écrans effectivement raccordés aux données.

Le flag serveur reste `BACKOFFICE_ENABLED=false` sur toute cible qui ne remplit
pas ces conditions.

`BACKOFFICE_ALLOW_TOTP_EXTERNAL=true` est un mécanisme d'acceptation explicite,
pas une recommandation de déploiement. Il ne doit être utilisé qu'après revue
Security, sur une cible à accès réseau borné, en lecture seule. Un Quick Tunnel
public ne satisfait pas cette condition.
