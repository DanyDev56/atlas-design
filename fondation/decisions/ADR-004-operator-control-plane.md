---
id: ADR-004
title: Operator Control Plane and Back-office Boundary
status: Accepted
date: 2026-08-23
owner: Product, Engineering and Security
version: 1.0.0
last_updated: 2026-08-23

references:
  - README.md
  - ADR-001-mvp-application-topology.md
  - ADR-002-mvp-implementation-stack.md
  - ADR-003-subscriptions-context-ownership.md
  - ../security/mvp-threat-model.md
  - ../../evolution/blueprint/backoffice.md
  - ../../evolution/blueprint/backoffice-metrics.md
  - ../../implementation/runbooks/beta-release-checklist.md
---

# ADR-004 — Plan de contrôle opérateur et frontière du back-office

## Contexte

La beta externe exige de suivre la cohorte, les Workspaces, les métriques
d'activation, les emails, l'outbox, les abonnements, le support, les demandes
relatives aux données et les actions opérateur. Ces informations sont
aujourd'hui réparties entre l'interface client, les API, PostgreSQL, les logs,
Jaeger et les registres documentaires.

Réunir ces capacités dans les écrans Workspace créerait une autorité implicite
très dangereuse : un rôle `Owner` est puissant dans son Workspace, mais ne doit
jamais devenir opérateur de la plateforme. À l'inverse, un opérateur Atlas ne
doit pas recevoir une Membership artificielle dans chaque Workspace ni utiliser
une requête SQL pour accomplir une action métier.

Le back-office introduit aussi ses propres concepts durables : habilitation
opérateur, dossier support, participant beta pseudonymisé, demande de données,
export, fermeture, approbation et audit d'accès privilégié. Il ne peut donc pas
être réduit à un assemblage graphique sans frontière de responsabilité.

`ADR-001` a figé les huit domaines du MVP et `ADR-003` a ensuite ajouté
`Subscriptions` pour la relation commerciale entre Atlas et ses clients.
L'acceptation d'`ADR-004` étend à son tour la liste normative avec un contexte
de support interne `Operations`, sans modifier les frontières ou la propriété
des domaines déjà acceptés.

## Forces de décision

1. séparer strictement l'autorité plateforme de l'autorité d'un Workspace ;
2. refuser par défaut et rendre chaque accès sensible attribuable ;
3. préserver la propriété des données et commandes de chaque bounded context ;
4. fournir une vue transversale sans jointure directe entre schémas métier ;
5. empêcher l'impersonation et les corrections silencieuses de données client ;
6. minimiser les données affichées au support et aux analystes produit ;
7. livrer d'abord une surface en lecture seule, puis des actions bornées ;
8. permettre un déploiement coordonné dans le modular monolith actuel.

## Options étudiées

### Option A — Plan de contrôle séparé et contexte `Operations`

Une surface `/backoffice` possède une audience de session et un catalogue
d'habilitations opérateur distincts. Un contexte de support `Operations`
possède les dossiers support, la cohorte beta, les demandes de données, les
approbations et l'audit privilégié. Il compose les contrats publics des domaines
et des projections dédiées ; il ne devient propriétaire d'aucune vérité métier.

### Option B — Réutiliser les rôles et Memberships Workspace

Un rôle `PlatformAdmin` serait ajouté aux rôles existants et recevrait accès à
tous les Workspaces. Cette solution semble rapide mais contredit la portée des
Memberships, élargit les conséquences d'une erreur d'autorisation et rend
l'audit ambigu entre action client et action opérateur.

### Option C — Back-office graphique connecté directement à PostgreSQL

L'interface lirait et modifierait les schémas existants via des requêtes
transverses. Cette option facilite les listes initiales, mais viole les
frontières de `ADR-001`, contourne les invariants et rend les mutations, exports
et suppressions non auditables.

### Option D — Outil SaaS d'administration générique

Un outil externe fournirait rapidement tables et formulaires. Il imposerait un
nouveau destinataire de données, une surface de secrets et des contrôles
d'autorisation difficiles à aligner avec les contrats métier. Il peut rester un
outil d'observabilité, pas devenir le plan de contrôle d'Atlas.

## Décision

Atlas retient **l'option A** : un plan de contrôle opérateur séparé, servi sous
`/backoffice`, et un contexte de support `Operations` dans le modular monolith.

Cette décision amende uniquement la liste des modules
obligatoires d'`ADR-001` : `Operations` devient un contexte de support interne,
portant le total à dix avec `Subscriptions`. Elle ne remplace ni la topologie
du modular monolith, ni les règles de stockage, transaction, messaging et
contrats publics d'`ADR-001`.

La séparation porte sur quatre axes :

- audience : une session Workspace n'est jamais acceptée par le back-office ;
- autorité : les habilitations opérateur ne proviennent jamais d'une Role ou
  Membership Workspace ;
- stockage : `Operations` possède son schéma et aucun autre schéma métier ;
- transport : le back-office utilise des endpoints opérateur distincts et des
  contrats publics, jamais les controllers clients avec un bypass global.

`Operations` possède :

- `OperatorGrant` ou la référence opaque de l'habilitation opérateur ;
- `SupportCase` ;
- `BetaParticipant` pseudonymisé et ses jalons opérationnels ;
- `ConsentEvidence` et version des textes présentés ;
- `DataRequest`, `ExportJob` et `WorkspaceClosureRequest` ;
- `PrivilegedActionRequest`, son approbation et son résultat ;
- `OperatorAuditEntry`, append-only ;
- les projections de pilotage opérateur, reconstructibles depuis leurs sources.

`Operations` ne possède ni User, Membership, Workspace, Client, Quote,
Invoice, Payment, AnalyticsSnapshot, Recommendation, Notification, Trial ou
Subscription. Il conserve des identifiants opaques et la provenance de chaque
lecture. Une action métier reste exécutée par son module propriétaire.

Les identités humaines restent authentifiées par Identity, mais avec une
audience de session opérateur et des grants plateforme provisionnés hors du
parcours d'inscription public. Une même personne peut avoir un compte client et
un compte opérateur, mais les sessions, routes et autorités restent séparées.

La V1 interdit l'impersonation. Le diagnostic affiche des vues minimisées et
des preuves de provenance. Une éventuelle fonction « voir comme » ou prise de
contrôle exige un nouvel ADR et ne peut pas être introduite comme raccourci de
support.

## Raisons

- l'autorité plateforme n'est plus confondue avec la collaboration client ;
- les domaines conservent leurs invariants, transactions et audit métier ;
- le back-office peut agréger la cohorte sans exposer toutes les données brutes ;
- chaque action privilégiée possède une intention, un approbateur, un motif et
  un résultat ;
- la lecture seule peut être livrée avant les mutations à risque ;
- le même artefact et la même release restent compatibles avec `ADR-001`.

## Conséquences

### Positives

- moindre privilège explicite par fonction opérateur ;
- audit central des accès et actions sensibles ;
- support, beta, conformité et exploitation réunis sans devenir un domaine
  métier client ;
- possibilité de reconstruire les indicateurs opérateur ;
- séparation claire des interfaces, sessions et cookies éventuels.

### Négatives

- dixième contexte à documenter, migrer, tester et exploiter ;
- projections et contrats transversaux supplémentaires ;
- authentification renforcée et provisioning opérateur à construire ;
- charge de revue pour chaque nouvelle action privilégiée ;
- certaines opérations resteront indisponibles tant qu'un second approbateur
  n'est pas réellement disponible.

## Conditions d'implémentation

- routes `/backoffice/*` et `/api/operator/*` séparées des routes client ;
- audience opérateur vérifiée côté serveur sur chaque requête ;
- aucun grant opérateur créé par inscription publique, invitation Workspace ou
  variable frontend ;
- MFA résistante au phishing visée pour tout opérateur ; à défaut temporaire,
  accès lecture seule borné par réseau et step-up, avec risque explicitement
  accepté avant beta externe ;
- cookies ou stockage de session séparés de l'application client ;
- permissions fines, aucune permission générique `admin` ou bypass global ;
- données masquées par défaut et révélation sensible justifiée et auditée ;
- aucun accès direct aux repositories privés ou aux tables d'un autre module ;
- audit append-only pour connexion, recherche, lecture sensible, export,
  approbation, action, échec et téléchargement ;
- actions sensibles idempotentes, avec step-up récent, motif obligatoire,
  prévisualisation et confirmation ;
- export, fermeture, retry dead-letter et restriction nécessitent une capacité
  dédiée ; les actions destructrices exigent une seconde approbation ;
- aucun secret, token, mot de passe, donnée bancaire ou contenu complet de
  document dans les listes, logs ou exports opérateur ;
- tests d'isolation entre audience client et opérateur, de permissions, de
  réduction de privilège, de concurrence et d'audit avant toute exposition.

## Réexamen

Cette décision doit être réexaminée si :

- le back-office doit être déployé sur une infrastructure ou un réseau séparé ;
- plusieurs équipes d'exploitation nécessitent une délégation complexe ;
- un fournisseur externe doit héberger des données ou actions opérateur ;
- une obligation impose une séparation stricte entre support et conformité ;
- le volume des projections justifie l'extraction de `Operations` ;
- un besoin d'impersonation est démontré et ne peut être satisfait par des vues
  diagnostiques minimisées.
