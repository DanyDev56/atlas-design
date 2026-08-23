---
id: BPT-015
title: Back-office Implementation Plan
status: In Review
owner: Engineering, Product and Security
version: 0.5.0
last_updated: 2026-08-24

references:
  - backoffice.md
  - backoffice-metrics.md
  - roadmap.md
  - ../../fondation/decisions/ADR-004-operator-control-plane.md
  - ../../fondation/security/mvp-threat-model.md
  - ../../implementation/runbooks/beta-release-checklist.md
  - ../../implementation/SEC-TEST-MATRIX.md
---

# Plan d'implémentation du back-office

## Gate documentaire — franchie le 23 août 2026

Les préconditions suivantes ont autorisé le démarrage du socle :

1. revue et acceptation d'`ADR-004` ;
2. validation Product du périmètre des écrans ;
3. validation Security des audiences, permissions et actions ;
4. maintien des données métier, support et cohorte hors du premier incrément ;
5. maintien des métriques hors du premier incrément jusqu'à validation de leur
   source et de leur rétention.

`ADR-004` est accepté. Toute surface ultérieure reste bloquée par sa gate
d'incrément ; ce franchissement n'autorise ni donnée métier transverse, ni
action opérateur, ni exposition externe sans authentification forte.

## État d'avancement

| Incrément | État | Preuve actuelle |
|---|---|---|
| 0 — décisions et menace | Partiel | ADR accepté, frontière `Operations`, TOTP et step-up borné livrés ; authentification résistante au phishing, break-glass et rétention restent ouverts |
| 1 — identité et audit | Socle local livré | audience séparée, provisioning CLI, grants fins, TOTP chiffré, récupération à usage unique, jetons hachés, révocation immédiate, audit et shell lecture seule |
| 2 — dashboard lecture seule | Socle partiel livré | Outbox et emails raccordés à leurs registres réels ; pagination, filtres, permissions et états d'absence livrés ; autres sources marquées `NotCollected` |
| 3 — cohorte beta | Socle lecture seule livré | registre pseudonymisé, dérivation E0–E6, entonnoir, jalons et décisions pricing raccordés ; écritures limitées aux commandes administratives auditées |
| 4 à 7 | Non démarrés | aucune action opérateur web ni donnée transverse supplémentaire raccordée |

## Incrément 0 — Décisions et modèle de menace

### Livrables

- accepter, réviser ou rejeter `ADR-004` ;
- ajouter le plan de contrôle aux frontières de confiance ;
- fermer ou remplacer `SEC-GAP-005` avec une politique opérateur normative ;
- décider provisioning, MFA, durée de session, step-up et break-glass ;
- décider rétention et accès de `OperatorAuditEntry`, SupportCase et preuves ;
- classer chaque action par impact, réversibilité et approbation.

### Gate

Aucune autorité ne dépend d'une Membership Workspace, aucun bypass global
n'existe, et chaque action cible un contrat propriétaire identifié.

## Incrément 1 — Identité opérateur et audit

### Livrables

- audience et session opérateur distinctes ;
- provisioning hors inscription publique ;
- grants fins et profils initiaux ;
- MFA/step-up selon la décision acceptée ;
- révocation immédiate et expiration courte ;
- audit append-only des connexions, refus et consultations sensibles ;
- shell `/backoffice` vide, accessible uniquement avec le grant exact.

### Gate

Tests positifs et négatifs entre session client, session opérateur, grant
révoqué, session expirée, route directe, cache d'autorisation et second
Workspace. Aucun écran métier n'est encore livré.

### Preuves livrées

- MFA TOTP enrôlée et tournée hors interface publique ;
- secret chiffré, anti-rejeu temporel et codes de récupération à usage unique ;
- sessions révoquées après rotation ou désactivation du facteur ;
- step-up récent de dix minutes, vérifié côté serveur par middleware ;
- refus externe par défaut, y compris avec TOTP, sans acceptation de risque
  explicite et accès réseau borné ;
- TOTP documenté comme contrôle transitoire non résistant au phishing.

## Incrément 2 — Dashboard lecture seule

### Livrables

- projection `OperationsOverview` reconstructible ;
- santé API/worker/scheduler, outbox, emails, support et cohorte ;
- provenance, fraîcheur et états d'absence ;
- pagination, filtres serveur et liens vers listes explicatives ;
- feature flag serveur `BACKOFFICE_ACTIONS_ENABLED=false`.

### Gate

Chaque compteur se rapproche de sa source avec des fixtures déterministes. Une
source indisponible produit `Unavailable`, jamais une valeur rassurante.

### Preuves livrées

- projection à la demande `OperationsOverview`, reconstruite depuis les tables
  propriétaires sans table de cache ni donnée de démonstration ;
- compteurs Outbox exclusifs `Pending`, `Retrying` et `DeadLetter`, avec âge du
  plus ancien message non distribué ;
- compteurs email `Accepted`, `Retrying` et `Failed` limités aux événements de
  livraison connus ;
- registres paginés et filtrés côté serveur, sans payload, erreur brute,
  destinataire, contenu ou identifiant fournisseur ;
- grants de détail `operations.outbox.read` et `operations.email.read`, refus
  audités et vue générale protégée par `operations.dashboard.read` ;
- sources non encore instrumentées affichées `NotCollected`, et erreurs de
  lecture affichées `Unavailable` ; aucun de ces états ne devient zéro ;
- `BACKOFFICE_ACTIONS_ENABLED=false` maintient les actions coupées côté serveur.

La gate reste partielle tant que les sources API, heartbeats, sauvegardes,
cohorte, support et conformité ne disposent pas de projections durables et de
fixtures de rapprochement.

## Incrément 3 — Cohorte beta et métriques produit

### Livrables

- participants pseudonymisés `BETA-001` à `BETA-005` ;
- étapes `E0` à `E6`, jalons J2/J7/J14/J21/J30 et blocages ;
- affectation unique d'une cellule pricing ;
- entonnoir en nombres absolus, délais et dénominateurs ;
- lien borné vers les états Analytics/Business Health d'un Workspace ;
- saisie de la décision selon la taxonomie pricing.

### Gate

Aucun nom, email, note nominative ou contenu métier dans les vues Product. La
même personne ne peut recevoir plusieurs cellules de prix sans biais visible.

### Preuves livrées

- registre Operations limité à cinq codes `BETA-001` à `BETA-005`, chaque
  utilisateur et Workspace ne pouvant apparaître qu'une fois ;
- cellule `P19`, `P24` ou `P29` équilibrée automatiquement et rendue immutable
  par la base ; version du packaging conservée avec l'affectation ;
- étapes `E0` à `E6` dérivées à la lecture depuis Identity, Workspace, CRM,
  Billing, Analytics, Business Health et le registre de décision ;
- réutilisation `E5` exigée lors d'un jour UTC distinct après la première valeur ;
- entonnoir avec effectifs, dénominateurs et médianes observées ; aucun taux
  n'est inventé lorsque le dénominateur vaut zéro ;
- jalons et décisions append-only, saisis par commandes administratives avec
  motif obligatoire et audit ;
- listes et diagnostic sans nom, email, UUID métier, contenu ou valeur
  financière ; grants séparés pour cohorte et métriques produit ;
- actions web toujours désactivées par `BACKOFFICE_ACTIONS_ENABLED=false`.

La gate reste partielle jusqu'à la recette Product/Security sur les cinq
participants réels et la validation des durées de rétention des preuves.

## Incrément 4 — Exploitation et abonnements

### Livrables

- séries HTTP RED, heartbeat des rôles et santé PostgreSQL ;
- outbox, retries, dead-letters et âge du plus ancien message ;
- états email et webhooks ;
- sauvegarde, restauration canary et scheduler ;
- Trials, Subscriptions, paiements échoués et grâce ;
- séparation visuelle et technique stricte entre sandbox et live.

### Gate

Une alerte de chaque famille est déclenchée puis reçue. Les vues corrèlent les
références sans exposer secret, adresse complète, contenu de document ou donnée
bancaire.

## Incrément 5 — Support et conformité

### Livrables

- SupportCase, assignation, gravité, objectifs et historique ;
- vérification du demandeur et révélation sensible auditée ;
- versions de conditions et notice, preuves d'information et consentements
  séparés pour recherche facultative ;
- DataRequest, échéances, décision et preuves ;
- demandes d'export et de fermeture en lecture seule/préparation ;
- tableaux de rétention et sous-traitants.

### Gate

Une recette fictive couvre demande d'accès, correction, export et fermeture,
sans transmettre une donnée d'un autre Workspace ni stocker une preuve dans les
logs.

## Incrément 6 — Actions opérateur bornées

### Ordre recommandé

1. gestion non destructive d'un dossier support ;
2. révocation d'une session opérateur ;
3. retry d'une dead-letter identifiée ;
4. réconciliation fournisseur ciblée ;
5. export approuvé ;
6. restriction Workspace réversible ;
7. fermeture Workspace approuvée.

### Gate par action

- permission dédiée et deny-by-default ;
- step-up récent ;
- prévisualisation exacte ;
- motif obligatoire ;
- idempotency key et gestion du conflit ;
- seconde approbation si destructive ;
- appel au domaine propriétaire ;
- audit avant et après avec résultat ;
- test de rejeu, révocation concurrente, panne partielle et rollback/forward-fix ;
- runbook d'incident et bouton coupé par feature flag.

## Incrément 7 — Recette et ouverture

### Recette

- deux opérateurs aux permissions différentes et un compte client ;
- tentative d'accès croisée `/app` ↔ `/backoffice` ;
- réduction de grant pendant une session ;
- recherche d'un Workspace autorisé puis d'un identifiant transverse ;
- révélation sensible et vérification de l'audit ;
- source de métrique en panne et donnée périmée ;
- export fictif à double approbation ;
- dead-letter corrigée et rejouée une seule fois ;
- fermeture fictive sans réactivation par worker ou restauration ;
- scan de secrets, dépendances, image et tests d'accessibilité.

### Gate d'ouverture

Le back-office peut accompagner la beta externe lorsque les incréments 0 à 5
sont validés en lecture seule, que les alertes sont reçues et qu'aucun risque
critique d'autorité, d'isolation ou d'audit reste ouvert. Les actions de
l'incrément 6 peuvent rester désactivées et continuer via runbook jusqu'à leur
propre gate.

## Definition of Done

- tests unitaires des décisions et permissions ;
- tests d'intégration des projections et contrats publics ;
- tests d'acceptation des parcours opérateur ;
- tests sécurité audience, objet, cache, step-up et approbation ;
- tests d'accessibilité et responsive ;
- logs/traces sans données sensibles ;
- métriques avec définition, source, fraîcheur et absence ;
- migration, sauvegarde, restauration et rollback répétés ;
- documentation et runbooks mis à jour ;
- sign-off Product, Engineering, Security et Support/Operations.
