---
id: SEC-001
title: MVP Transversal Threat Model
status: In Review
owner: Product, Engineering and Security
version: 1.3.0
last_updated: 2026-08-06

references:
  - README.md
  - ../constitution.md
  - ../decisions/ADR-001-mvp-application-topology.md
  - ../decisions/ADR-002-mvp-implementation-stack.md
  - ../domain-map/context-map.md
  - ../domain-map/dependencies.md
  - ../domains/identity/api.md
  - ../domains/identity/invariants.md
  - ../domains/identity/integrations.md
  - ../domains/workspace/api.md
  - ../domains/billing/api.md
  - ../domains/billing/integrations.md
  - ../domains/notifications/integrations.md
  - ../../evolution/roadmap/mvp-acceptance.md
  - ../../evolution/blueprint/dashboard.md
  - ../../evolution/blueprint/historical-import.md
  - ../../evolution/governance/quality-gates.md
---

# Modèle de menace transversal du MVP

## Statut du modèle

Ce document est une baseline de conception. Les contrôles sont **requis**, mais
ne sont pas considérés implémentés ou efficaces avant le passage des tests
associés dans un environnement représentatif.

Le passage à `Stable` exige une revue conjointe Product, Engineering et Security,
la résolution des gaps bloquants et une première exécution de la matrice de
vérification.

---

## Méthode et référentiels

Le modèle répond aux quatre questions maintenues par le projet OWASP Threat
Modeling : que construisons-nous, que peut-il arriver, que faisons-nous et
avons-nous suffisamment vérifié ? Les catégories STRIDE servent d'aide de
couverture, pas de score automatique.

Référentiels externes retenus :

- [OWASP Threat Modeling Project](https://owasp.org/www-project-threat-modeling/),
  pour le processus continu et indépendant d'un outil ;
- [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0_release),
  comme catalogue versionné de contrôles et tests applicatifs ;
- [OWASP API Security Top 10 — 2023](https://owasp.org/API-Security/editions/2023/en/0x11-t10/),
  notamment pour les autorisations objet/fonction, les flux sensibles et la
  consommation de ressources ;
- [NIST SP 800-63B-4](https://doi.org/10.6028/NIST.SP.800-63B-4), pour éclairer
  les décisions d'authentification, d'authenticator et de session.

Atlas ne revendique pas encore une conformité complète à ces référentiels ni un
niveau d'assurance NIST. Les exigences applicables seront tracées vers leurs
identifiants versionnés lors du choix de stack et de l'implémentation.

---

## Périmètre

### Inclus

- l'application first-party du MVP et ses adaptateurs d'entrée ;
- les huit modules de `ADR-001` et les surfaces Onboarding, Dashboard et
  Settings ;
- les rôles d'exécution API, Worker et Scheduler ;
- les namespaces de persistence, outbox, inbox, checkpoints et caches ;
- les parcours `MVP-J1`, `MVP-J2` et `MVP-J3` ;
- vérification d'e-mail, invitation, récupération, authentification, sessions,
  step-up et autorisation ;
- vues publiques Quote/Invoice et leurs artefacts ;
- rendu et remise d'e-mails/documents, callbacks fournisseurs et stockage
  d'artefacts ;
- CI/CD, configuration, secrets, sauvegardes, observabilité et accès opérateur.

### Hors périmètre

- API publique externe, marketplace et applications clientes tierces ;
- connecteurs banque, comptabilité, paiement, signature ou stockage utilisateur ;
- application mobile native et multi-workspaces simultanés dans l'interface ;
- Automation, IA générative autonome et webhooks produit génériques ;
- menaces physiques du terminal utilisateur et sécurité interne des fournisseurs
  au-delà de leurs contrats et preuves ;
- interprétation juridique détaillée des durées de rétention ou obligations
  sectorielles.

Un élément hors périmètre n'est pas implicitement sûr. Son introduction exige
une nouvelle analyse avant activation.

---

## Hypothèses contestables

| ID | Hypothèse | Conséquence si elle devient fausse |
|---|---|---|
| `SEC-A01` | Toute interaction distante utilise un transport confidentiel et authentifié. | Les credentials, preuves et données deviennent interceptables ou modifiables. |
| `SEC-A02` | Le navigateur, l'appareil et le réseau utilisateur sont non fiables. | Aucun secret durable ou contrôle d'autorisation ne peut dépendre du client. |
| `SEC-A03` | Un membre authentifié peut être malveillant ou compromis. | Toute ressource doit être autorisée par objet et Workspace, pas seulement par route. |
| `SEC-A04` | Les fournisseurs et callbacks externes peuvent être indisponibles, compromis ou mal configurés. | Le domaine revalide, minimise et ne confond jamais leur réponse avec une décision métier. |
| `SEC-A05` | Le transport de messages livre au moins une fois, avec duplication, retard et trous possibles. | Inbox, versions, cursors et relecture exacte sont obligatoires. |
| `SEC-A06` | Les opérateurs et pipelines possèdent un accès puissant mais ne sont pas intrinsèquement fiables. | Moindre privilège, séparation, audit et accès temporaires sont requis. |
| `SEC-A07` | Le volume MVP est modeste, mais les routes publiques et effets facturés peuvent subir un abus automatisé. | Quotas, backpressure et budgets d'effets sont requis dès le MVP. |
| `SEC-A08` | La possession d'une boîte e-mail n'est pas une autorité suffisante pour une action Workspace critique. | Les actions critiques exigent session, permission et step-up adaptés. |
| `SEC-A09` | Une sauvegarde, un cache, un log et une fixture sont des copies de données soumises aux mêmes menaces. | Classification, isolation, redaction, rétention et tests s'appliquent à toutes les copies. |
| `SEC-A10` | `ADR-002` propose PHP, Laravel, React et PostgreSQL, mais reste non normatif et le fournisseur cloud n'est pas choisi. | Le spike, l'acceptation de l'ADR et la revue du fournisseur doivent ajouter les preuves spécifiques à la technologie. |

---

## Actifs et classification

| Classe | Actifs | Exigences minimales |
|---|---|---|
| `Restricted` | mots de passe et authenticators, session/refresh credentials, preuves de vérification/récupération/invitation, `PublicDocumentProof`, clés de signature/chiffrement, secrets fournisseurs et CI/CD | jamais dans Domain Events ou logs ; accès dédié ; protection au repos ; rotation, révocation et durée minimales ; redaction irréversible des copies. |
| `Confidential` | identité utilisateur, contacts Client, identité de facturation, contenu et artefacts Quote/Invoice, références de paiement, memberships/permissions détaillés, préférences, audit de sécurité | isolation Workspace ou sujet ; minimisation ; chiffrement adapté ; accès et export audités ; rétention explicite. |
| `Internal` | faits analytiques pseudonymisés, métriques, assessments, Recommendations, événements minimaux, configurations non secrètes, métadonnées d'exploitation | contrat allowlisté ; intégrité/version ; accès workload borné ; pas d'enrichissement silencieux en données personnelles. |
| `Public` | documentation et contenu explicitement publié | publication volontaire ; aucune donnée Atlas n'est publique par défaut. |

Un identifiant opaque n'est pas nécessairement anonyme. `UserId`, `ClientId` et
`WorkspaceId` restent des données à protéger lorsqu'ils permettent une
corrélation ou une réidentification.

---

## Objectifs de sécurité

| ID | Objectif |
|---|---|
| `SEC-O01` | Une donnée ou autorité d'un Workspace ne s'applique jamais à un autre. |
| `SEC-O02` | Une Session prouve un User ; seule une autorisation actuelle prouve une capacité dans un Workspace. |
| `SEC-O03` | Les preuves secrètes sont imprévisibles, bornées, expirables, révocables et résistantes au rejeu. |
| `SEC-O04` | Les décisions commerciales et financières ne peuvent être falsifiées, dupliquées ou effacées silencieusement. |
| `SEC-O05` | Un message ou callback non authentique, dupliqué, tardif ou incomplet ne crée aucun effet non autorisé. |
| `SEC-O06` | Une réduction de privilège ou restriction prend effet avant tout nouvel effet externe. |
| `SEC-O07` | Les données sensibles restent minimales dans contrats, caches, logs, sauvegardes et fournisseurs. |
| `SEC-O08` | Les actions sensibles sont attribuables sans transformer l'audit en source de secrets. |
| `SEC-O09` | Une panne ou attaque volumétrique se dégrade de manière bornée, observable et reprenable. |
| `SEC-O10` | Sauvegarde, restauration, migration et reconstruction ne réduisent ni isolation ni intégrité. |

---

## Acteurs et agents de menace

| Acteur | Autorité légitime | Abus considéré |
|---|---|---|
| visiteur non authentifié | inscription, authentification, récupération, preuve publique bornée | énumération, bruteforce, automatisation, token guessing, déni de service. |
| destinataire d'un document | voir ou décider selon `PublicDocumentProof` | partage du lien, substitution de document, rejeu, action forcée. |
| membre Workspace | capacités exactes de son Role | accès objet transverse, élévation, mass assignment, export abusif. |
| owner ou membre privilégié | gouvernance et actions sensibles autorisées | abus interne, erreur critique, contournement du step-up. |
| workload Atlas | capacité `SystemActorOnly` bornée | confused deputy, credential volée, mutation directe, effet après révocation. |
| fournisseur externe | rendu, stockage ou livraison selon un port minimal | callback forgé, exfiltration, contenu altéré, SSRF, indisponibilité. |
| opérateur ou support | diagnostic/remédiation explicitement approuvés | accès permanent, impersonation non bornée, modification ou suppression de traces. |
| pipeline ou dépendance compromise | build, migration ou runtime approuvés | injection de code, secret exfiltré, configuration affaiblie. |

---

## Frontières de confiance

```text
Untrusted Internet
  browsers, public recipients, bots
          |
          | SEC-B01: edge and transport
          v
First-party adapters / API role
          |
          | SEC-B02: principal, Workspace and input binding
          v
Application composition
  Onboarding, Dashboard, Settings
          |
          | SEC-B03: public module contracts only
          v
Eight domain modules
          |
          | SEC-B04: module-owned transaction and namespace
          v
Datastore / outbox / inbox / cache
          |
          | SEC-B05: durable message dispatch
          v
Worker and Scheduler roles
          |
          | SEC-B06: confidential outbound ports
          v
Email, rendering, artifact and observability providers

SEC-B07 surrounds CI/CD, secrets, backups and operator access.
```

### Règles par frontière

| Frontière | Règle |
|---|---|
| `SEC-B01` | transport authentifié, limites de taille/fréquence, réponses non énumérantes et aucune mutation sensible par navigation passive. |
| `SEC-B02` | validation syntaxique et sémantique, résolution du principal, `WorkspaceId` explicite, autorisation objet/fonction et protection navigateur selon le transport choisi. |
| `SEC-B03` | contrats versionnés, DTOs allowlistés, aucun import interne, aucune autorité déduite par la composition. |
| `SEC-B04` | transaction locale, identité datastore bornée, aucun accès ou relation transverse, état + audit/outbox atomiques. |
| `SEC-B05` | identité workload, intégrité, `EventId`, causalité, version, inbox, checkpoint, ordre métier et dead-letter observable. |
| `SEC-B06` | endpoint opaque, payload minimal, destination allowlistée, clé idempotente, callback authentifié, timeout et revalidation avant effet. |
| `SEC-B07` | moindre privilège, secrets hors code, artefacts signés/provenancés, accès temporaires, séparation des environnements et audit protégé. |

---

## Points d'entrée et de sortie

| Point | Confiance à l'entrée | Sortie sensible possible |
|---|---|---|
| inscription, login, récupération et vérification | aucune ; preuve à construire | réponse opaque, session ou nouvelle preuve. |
| application first-party et API adapters | Session potentiellement volée ou obsolète | lecture/mutation Workspace autorisée. |
| routes Quote/Invoice publiques | `PublicDocumentProof` non encore validée | document, artefact ou décision terminale. |
| callbacks de livraison | requête externe non fiable | preuve de remise ou échec fournisseur. |
| dispatcher, consumers et scheduler | identité workload à vérifier | projection, nouvelle commande système ou effet externe. |
| rendu et stockage d'artefacts | payload interne minimisé ; fournisseur non souverain | artefact immuable et référence opaque. |
| Dashboard, export et recherche | principal et contexte susceptibles de changer en vol | composition ou copie de données confidentielles. |
| migrations, CI/CD et configuration | pipeline/opérateur puissant | code, schéma, secret ou comportement de production. |
| backup et restore | copie chiffrée mais fortement privilégiée | restauration de plusieurs namespaces et versions. |

Toute nouvelle route, callback, file, job, export ou interface opérateur est un
point d'entrée même si elle n'est pas exposée à Internet.

---

## Flux sensibles

| Flux | Secrets ou données | Décision de sécurité |
|---|---|---|
| inscription et vérification | email, `EmailOwnershipProof` | réponse opaque, preuve bornée/consommée, activation atomique. |
| authentification et session | credential, `AuthenticationProof`, session/refresh credentials | comparaison spécialisée, rotation, versions de sécurité, expiration et révocation. |
| récupération et invitation | recovery/invitation proof | non-énumération, usage unique, finalité, expiration, rotation et invalidation concurrente. |
| bootstrap Workspace | principal, owner readiness, permissions de base | saga idempotente, même Workspace, aucun accès ordinaire avant activation. |
| consultation/acceptation Billing | `PublicDocumentProof`, document confidentiel | preuve par document/capacité/échéance, action explicite, révision attendue. |
| paiement manuel | montant, référence, actor, Invoice revision | permission exacte, step-up selon risque, idempotence et inversion plutôt qu'édition. |
| chaîne analytique | événements et faits versionnés | workload allowlisté, relecture exacte, déduplication et données personnelles minimales. |
| notification email | audience, endpoint opaque, contenu minimal | consentement, priorité, revalidation, résolution confidentielle au dernier moment. |
| sauvegarde/restauration | copies multi-domaines et secrets chiffrés | accès restreint, cohérence de version, test de restauration et traçabilité. |

---

## Catalogue des contrôles requis

| ID | Contrôle requis |
|---|---|
| `SEC-C01` | Lier chaque ressource, cache, job, message et export au `WorkspaceId`/sujet attendu et refuser toute incohérence. |
| `SEC-C02` | Réponses anti-énumération, throttling multi-dimensionnel, délais/backoff bornés et détection d'abus sur les flux Identity. |
| `SEC-C03` | Preuves opaques à forte entropie, empreinte non réversible, finalité/capacité/sujet/échéance, rotation, révocation et consommation atomique. |
| `SEC-C04` | Session ID renouvelé après authentification/élévation, credentials protégées, refresh à usage unique, famille compromise au rejeu, expirations et révocation par `UserSecurityVersion` et versions associées. |
| `SEC-C05` | Autorisation Identity actuelle par objet et fonction, permission exacte, refus par défaut et step-up borné aux opérations sensibles. |
| `SEC-C06` | Identité workload distincte, capacité `SystemActorOnly` minimale, audience/purpose allowlistés et aucune permission système accordable à un Role. |
| `SEC-C07` | Schémas d'entrée/sortie allowlistés, longueurs et cardinalités bornées, canonicalisation, encodage contextuel et absence de mass assignment. |
| `SEC-C08` | `PublicDocumentProof` limité au document, capacité et durée ; lecture/action séparées ; aucune décision sensible sur `GET` ; confirmation explicite. |
| `SEC-C09` | `ExpectedRevision`, idempotency key, calcul exact, numéros non réutilisables, snapshots/hash immuables et correction financière additive. |
| `SEC-C10` | Transaction locale avec état, événements, audit et outbox atomiques ; inbox/checkpoint atomiques chez le consumer. |
| `SEC-C11` | Enveloppe versionnée et authentifiée, provenance workload, déduplication, ordre métier, détection de trous et callbacks anti-rejeu. |
| `SEC-C12` | Revalidation autorisation/Workspace/`DeliveryEndpointReference` immédiatement avant effet, payload minimal, destination allowlistée et clé fournisseur stable. |
| `SEC-C13` | Gestion centralisée des secrets/keys, chiffrement adapté au repos et en transit, rotation/révocation, séparation des environnements et redaction. |
| `SEC-C14` | Classification, minimisation, rétention, suppression/export auditables et fixtures synthétiques sans données de production. |
| `SEC-C15` | Audit append-only/tamper-evident, logs structurés, neutralisation des entrées, accès restreint, corrélation et alertes sans secrets. |
| `SEC-C16` | Sauvegardes protégées, objectifs de restauration, tests de restore, migrations réversibles/forward-fix et reconstruction versionnée. |
| `SEC-C17` | Limites de requête, pagination, quotas, budgets d'effets, timeout, circuit breaker, backpressure et isolation des workloads coûteux. |
| `SEC-C18` | Dépendances verrouillées et analysées, provenance/SBOM, build reproductible, artefacts signés, configuration durcie et séparation des rôles opérateur. |
| `SEC-C19` | Défenses navigateur adaptées au transport : cookies sécurisés si utilisés, CSRF, origine, headers, CSP et prévention de l'open redirect/clickjacking. |
| `SEC-C20` | Runbooks de détection, confinement, rotation, révocation, restriction Workspace, conservation de preuve et retour d'expérience. |

Les paramètres concrets — algorithmes, durées, seuils et headers — seront fixés
avec la stack et versionnés. Une valeur par défaut d'un framework n'est pas une
preuve de conformité.

---

## Priorisation qualitative

Le modèle ne calcule pas un score numérique : la probabilité et l'impact ne sont
pas encore mesurés avec assez de précision pour qu'une multiplication soit
significative.

| Niveau | Interprétation |
|---|---|
| `Critical` | prise de compte, franchissement de Workspace, compromission d'un secret majeur, décision financière falsifiée ou contrôle de plateforme. |
| `High` | exposition ou altération matérielle bornée, abus externe important, indisponibilité d'un parcours critique ou perte de confiance durable. |
| `Medium` | impact contenu avec récupération démontrée, détection fiable et rayon d'explosion limité. |
| `Low` | effet mineur, local, rapidement réversible et sans donnée sensible ni élévation. |

Le risque initial suppose les contrôles non prouvés. Le résiduel cible suppose
tous les contrôles associés efficaces et leurs tests réussis. Si une hypothèse
change ou un test échoue, le risque est réévalué avant acceptation.

---

## Registre des menaces

### Identity, authentification et sessions

| ID | Scénario et catégorie STRIDE | Risque initial | Contrôles | Détection | Vérification | Owner | Résiduel cible |
|---|---|---|---|---|---|---|---|
| `SEC-T01` | Énumérer les comptes par réponses, statuts, timing ou comportement de livraison (`I`). | High | C02, C07, C15 | taux d'identifiants distincts, écarts de réponse et campagnes distribuées. | `SEC-TEST-002` | Identity + Security | Low |
| `SEC-T02` | Automatiser inscription, login, récupération ou invitation pour épuiser ressources et budgets de communication (`D`). | High | C02, C17, C20 | quotas par source/sujet/finalité, coût fournisseur et files anormales. | `SEC-TEST-003`, `SEC-TEST-021` | Identity + Platform | Medium |
| `SEC-T03` | Credential stuffing, bruteforce ou authenticator faible menant à une prise de compte (`S`, `E`). | Critical | C02, C04, C13, C20 | échecs distribués, changement de device/risque, familles révoquées. | `SEC-TEST-003`, `SEC-TEST-005` | Identity + Security | Medium |
| `SEC-T04` | Voler, substituer ou rejouer une preuve de vérification, invitation ou récupération (`S`, `T`, `E`). | Critical | C03, C13, C15, C19 | preuve réutilisée, finalité/sujet incohérents, rotations et expirations. | `SEC-TEST-004` | Identity | Low |
| `SEC-T05` | Fixer ou voler une Session, rejouer un refresh credential ou maintenir l'accès après révocation (`S`, `E`). | Critical | C04, C05, C13, C19, C20 | refresh réutilisé, SecurityVersion obsolète, géographie/device anormal. | `SEC-TEST-005`, `SEC-TEST-007` | Identity + Security | Medium |
| `SEC-T06` | Réutiliser une élévation pour une autre action, après expiration ou avec une preuve trop faible (`E`). | Critical | C04, C05, C15 | échecs de scope/version, élévations atypiques et actions critiques corrélées. | `SEC-TEST-006` | Identity + domaine cible | Low |

### Autorisation, isolation et composition

| ID | Scénario et catégorie STRIDE | Risque initial | Contrôles | Détection | Vérification | Owner | Résiduel cible |
|---|---|---|---|---|---|---|---|
| `SEC-T07` | Remplacer un ID de ressource ou `WorkspaceId` et lire/muter l'objet d'un autre tenant (`I`, `T`, `E`). | Critical | C01, C05, C07, C15 | refus cross-tenant corrélés et probes d'identifiants. | `SEC-TEST-001` | Tous modules + Security | Low |
| `SEC-T08` | Exploiter un cache d'autorisation obsolète après retrait, rôle désactivé, User révoqué ou Workspace restreint (`E`). | Critical | C01, C04, C05, C12 | décision basée sur version obsolète et effet annulé à la revalidation. | `SEC-TEST-007`, `SEC-TEST-026` | Identity + Workspace + consommateurs | Low |
| `SEC-T09` | Accorder une permission critique interdite, déduire l'autorité d'un nom de Role ou utiliser humainement une capacité `SystemActorOnly` (`E`, `T`). | Critical | C05, C06, C15 | grants critiques, tentative SystemActorOnly et changements de template. | `SEC-TEST-008` | Identity | Low |
| `SEC-T10` | Usurper un workload ou utiliser un consumer/scheduler comme confused deputy hors purpose/Workspace (`S`, `E`). | Critical | C01, C06, C11, C15 | appels workload hors audience, purpose ou cadence prévue. | `SEC-TEST-009` | Platform + module propriétaire | Low |
| `SEC-T11` | Mélanger des réponses, caches ou requêtes en vol entre deux Workspaces dans Dashboard/Settings (`I`). | Critical | C01, C05, C14 | cache key incohérente, réponse après changement de contexte. | `SEC-TEST-010` | Application composition | Low |

### Preuves publiques et intégrité financière

| ID | Scénario et catégorie STRIDE | Risque initial | Contrôles | Détection | Vérification | Owner | Résiduel cible |
|---|---|---|---|---|---|---|---|
| `SEC-T12` | Deviner, divulguer ou substituer un `PublicDocumentProof` pour lire ou décider sur un autre document (`S`, `I`, `E`). | Critical | C03, C08, C13, C15 | preuves invalides par document/capacité, taux de guessing et usage après révocation. | `SEC-TEST-011` | Billing + Security | Low |
| `SEC-T13` | Déclencher une acceptation/rejection par navigation passive, CSRF, clickjacking ou consentement ambigu (`S`, `T`). | High | C08, C19 | origine anormale, décision sans étape de confirmation et tentatives cross-site. | `SEC-TEST-012` | Billing + Web adapter | Low |
| `SEC-T14` | Altérer un PDF/artefact, servir une mauvaise version ou remplacer sa référence (`T`, `I`). | High | C07, C09, C12, C13 | hash/version/source incompatibles et mismatch de stockage. | `SEC-TEST-013` | Billing + Artifact adapter | Low |
| `SEC-T15` | Rejouer ou concurrencer acceptation, émission, numérotation ou paiement pour dupliquer un effet financier (`T`, `R`, `E`). | Critical | C05, C09, C10, C15 | idempotency conflict, révision obsolète, doublon de source/numéro. | `SEC-TEST-014` | Billing | Low |

### Entrées, messages et traitements système

| ID | Scénario et catégorie STRIDE | Risque initial | Contrôles | Détection | Vérification | Owner | Résiduel cible |
|---|---|---|---|---|---|---|---|
| `SEC-T16` | Injection, overposting/mass assignment, fichier actif, malware ou archive expansive via champs, imports, recherche, templates ou métadonnées (`T`, `I`, `E`). | High | C07, C14, C19 | erreurs de schéma, fichiers bloqués, payloads bloqués et signaux WAF/applicatifs. | `SEC-TEST-015` | Tous modules + adaptateur d'import | Low |
| `SEC-T17` | Forger ou modifier un Domain Event, une preuve d'horloge ou un callback fournisseur (`S`, `T`, `R`). | Critical | C06, C11, C13, C15 | signature/identité/audience invalide, nonce ou timestamp rejoué. | `SEC-TEST-016` | Platform + consommateur | Low |
| `SEC-T18` | Dupliquer, retarder, réordonner ou omettre un message afin de rétablir un ancien état ou répéter un effet (`T`, `D`). | High | C10, C11, C15 | gap de version/cursor, inbox duplicate et âge de message. | `SEC-TEST-017` | Tous consumers | Low |
| `SEC-T19` | Un Worker ou Scheduler contourne le domaine en écrivant directement son stockage ou celui d'un autre module (`E`, `T`). | Critical | C05, C06, C10, C18 | requête interdite par rôle datastore et test d'architecture. | `SEC-TEST-018` | Platform + Engineering | Low |

### Fournisseurs et effets externes

| ID | Scénario et catégorie STRIDE | Risque initial | Contrôles | Détection | Vérification | Owner | Résiduel cible |
|---|---|---|---|---|---|---|---|
| `SEC-T20` | Exposer endpoint, secret, document ou contenu métier à un fournisseur ou via logs/outbox (`I`). | Critical | C12, C13, C14, C15 | scan de payload/log, accès secret et volume de données sortantes. | `SEC-TEST-019`, `SEC-TEST-022` | Adapter propriétaire + Security | Low |
| `SEC-T21` | Exploiter une URL, redirection, callback ou réponse fournisseur non fiable pour SSRF ou injection (`S`, `T`, `E`). | High | C07, C11, C12, C17 | destination hors allowlist, résolution privée, schéma/protocole interdit. | `SEC-TEST-020` | Platform + adapters | Low |
| `SEC-T22` | Amplifier rendu, stockage ou e-mails pour déni de service, spam ou coût externe (`D`). | High | C02, C12, C17, C20 | budget par Workspace/finalité, queue depth, taux provider et coût. | `SEC-TEST-021` | Billing/Notifications + Platform | Medium |
| `SEC-T23` | Exécuter un effet après retrait du destinataire, restriction Workspace, expiration ou changement de priorité (`E`, `T`). | Critical | C01, C05, C12 | revalidation refusée, effet annulé et version source dépassée. | `SEC-TEST-026` | Identity/Workspace + domaine émetteur | Low |

### Données, exploitation et disponibilité

| ID | Scénario et catégorie STRIDE | Risque initial | Contrôles | Détection | Vérification | Owner | Résiduel cible |
|---|---|---|---|---|---|---|---|
| `SEC-T24` | Fuir ou falsifier secrets/PII par logs, audit, traces ou injection de lignes (`I`, `T`, `R`). | Critical | C13, C14, C15 | secret scanning, accès audit, anomalies de chaîne/intégrité et log forging. | `SEC-TEST-022` | Platform + Security | Low |
| `SEC-T25` | Mélanger ou perdre des données dans sauvegarde, export, restore, migration, cache ou fixture (`I`, `T`, `D`). | Critical | C01, C13, C14, C16 | restore canary, inventaire des copies et contrôle de cohérence/version. | `SEC-TEST-023` | Platform + propriétaires | Medium |
| `SEC-T26` | Compromettre dépendance, build, migration, configuration ou accès opérateur pour exécuter du code privilégié (`S`, `T`, `E`). | Critical | C13, C15, C18, C20 | provenance échouée, drift, secret use, accès privilégié et changement hors pipeline. | `SEC-TEST-024` | Engineering + Security | Medium |
| `SEC-T27` | Manipuler des données métier autorisées ou leur provenance pour produire une métrique, santé ou Recommendation trompeuse (`T`, `R`). | High | C05, C09, C10, C11, C15 | correction anormale, FactHash/version incohérent et rebuild divergent. | `SEC-TEST-025` | CRM/Billing/Analytics | Medium |
| `SEC-T28` | Bloquer la chaîne par poison message, rebuild coûteux, file morte ignorée ou starvation d'un Workspace (`D`). | High | C10, C11, C16, C17, C20 | lag/âge, retries, DLQ, checkpoint immobile et budget par workload. | `SEC-TEST-027` | Platform + consommateurs | Medium |

`Residual target` est la cible après contrôles et tests. Ce n'est ni un état
actuel, ni une acceptation. Aucun risque `High` ou `Critical` n'est accepté par
ce document.

---

## Matrice de vérification

| ID | Vérification exigée | Phase |
|---|---|---|
| `SEC-TEST-001` | Pour chaque commande, lecture, projection, cache, job et export, substituer Workspace/objet et prouver refus sans existence révélée. | CI + pre-release |
| `SEC-TEST-002` | Comparer réponses et comportements inscription/login/récupération pour comptes existant/inexistant, sans différence exploitable. | CI + security review |
| `SEC-TEST-003` | Simuler bruteforce et abus distribué ; vérifier limites par source, sujet, finalité, coût et récupération légitime. | pre-release + continuous |
| `SEC-TEST-004` | Tester preuve erronée, autre sujet/finalité, expiration, rotation, révocation, consommation concurrente et rejeu. | CI |
| `SEC-TEST-005` | Tester fixation, vol/rejeu refresh, rotation concurrente, expiration absolue/inactivité, logout et famille compromise. | CI + security review |
| `SEC-TEST-006` | Réutiliser une élévation hors scope, action, durée, version et niveau d'authentification attendus. | CI |
| `SEC-TEST-007` | Révoquer User/Session/Membership/Role/Permission ou restreindre Workspace pendant une requête et avant un effet ; prouver le refus. | integration + pre-release |
| `SEC-TEST-008` | Tenter grant/revoke critique sans authority, permission par nom de Role et attribution humaine de chaque `SystemActorOnly`. | CI |
| `SEC-TEST-009` | Appeler chaque contrat workload avec identité, audience, purpose ou Workspace incorrects et depuis un rôle d'exécution non autorisé. | contract tests |
| `SEC-TEST-010` | Changer rapidement de Workspace avec requêtes concurrentes ; vérifier cache keys, annulation et absence de fusion Dashboard. | end-to-end |
| `SEC-TEST-011` | Deviner/substituer une preuve publique ; tester mauvais document/capacité, expiration, révocation, artefact et réponse anti-énumération. | CI + security review |
| `SEC-TEST-012` | Vérifier qu'un `GET`, prefetch, image, iframe, origine tierce ou requête sans confirmation ne décide jamais une Quote. | end-to-end |
| `SEC-TEST-013` | Modifier artifact, hash, version et référence ; prouver refus et non-remplacement d'un document communiqué. | integration |
| `SEC-TEST-014` | Concurrencer/rejouer acceptation, émission, séquence, paiement et inversion avec mêmes/différentes clés et révisions. | CI + integration |
| `SEC-TEST-015` | Fuzz schémas, tailles, encodages, propriétés inconnues, contenu actif et champs server-owned ; tester malware, archive expansive, rétention et parsing sans exécution sur l'import. | CI + dynamic testing |
| `SEC-TEST-016` | Forger/tamper/rejouer événements, horloge et callbacks ; vérifier identité, intégrité, audience, nonce et timestamp. | contract + integration |
| `SEC-TEST-017` | Injecter duplicate, retard, ancien ordre et trou ; vérifier inbox, cursor/version, lecture exacte et absence de régression. | integration |
| `SEC-TEST-018` | Faire échouer la CI sur import interne/migration transverse et refuser au runtime l'accès datastore d'un Worker/Scheduler non propriétaire. | CI + pre-release |
| `SEC-TEST-019` | Scanner événements, outbox, logs et payload fournisseur pour secrets, endpoints bruts, PII ou contenu non allowlisté. | CI + continuous |
| `SEC-TEST-020` | Fournir URL/redirection/callback malveillants, adresses privées, DNS changeant et réponse active ; prouver l'egress borné. | security review |
| `SEC-TEST-021` | Charger login, routes publiques, rendu, e-mail, rebuild et exports ; vérifier quotas, budgets, backpressure et dégradation. | performance + pre-release |
| `SEC-TEST-022` | Injecter séparateurs/markup/secrets dans les logs ; vérifier redaction, neutralisation, accès, intégrité, rotation et alertes. | CI + operations drill |
| `SEC-TEST-023` | Restaurer sauvegarde représentative, migrer/rollback ou forward-fix, reconstruire, exporter et vérifier isolation, versions et hashes. | release rehearsal |
| `SEC-TEST-024` | Vérifier lockfiles, analyse, SBOM/provenance, signature, secret scanning, configuration, droits CI/CD et accès break-glass. | CI + quarterly review |
| `SEC-TEST-025` | Corriger et rejouer les mêmes faits ; vérifier FactHash, provenance, déterminisme et divergence visible des politiques. | integration |
| `SEC-TEST-026` | Suspendre/revoquer/restreindre entre planification et dispatch ; vérifier revalidation et absence d'effet fournisseur. | integration |
| `SEC-TEST-027` | Injecter poison message, panne consumer, DLQ et rebuild concurrent ; vérifier isolation, alerte, reprise et absence de perte. | resilience drill |

Les tests `continuous`, `quarterly` ou `operations drill` deviennent des
fréquences minimales seulement après définition de l'environnement
d'exploitation. Avant cela, ils constituent des exigences de conception.

---

## Gaps et décisions ouvertes

| ID | Gap | Owner | Gate | Statut |
|---|---|---|---|---|
| `SEC-GAP-001` | Politique concrète d'authenticator, credential, session, durée, cookie/token et niveau de step-up non fixée. | Identity + Security | avant implémentation Session production | Open |
| `SEC-GAP-002` | Le workflow de récupération existe, mais ses commandes détaillées, preuve, rotation et facteurs de secours doivent être contractés au niveau des autres commandes Identity. | Identity | avant `MVP-J1` complet | Open |
| `SEC-GAP-003` | Entropie, TTL, capacités, rotation, transport et limites de `PublicDocumentProof` non quantifiés. | Billing + Security | avant exposition publique de `MVP-J2` | Open |
| `SEC-GAP-004` | Classification opérationnelle, durées de rétention, suppression/export et données de support non décidées. | Product + Security | avant données réelles | Open |
| `SEC-GAP-005` | Modèle opérateur/support, break-glass, impersonation, approbation et séparation des devoirs non défini. | Engineering + Security | avant accès production | Open |
| `SEC-GAP-006` | `ADR-002` propose PostgreSQL et un hébergement managé en UE ; fournisseur, gestionnaire de secrets/keys, chiffrement, backup, RPO/RTO et rotation restent à décider. | Engineering | ADR de stack et fournisseur avant production | Open |
| `SEC-GAP-007` | Seuils de rate limit, quotas, budgets fournisseurs et protection edge restent à calibrer. | Product + Platform | avant beta exposée | Open |
| `SEC-GAP-008` | Runbooks incident, niveaux d'alerte, conservation des preuves et exercices de restauration/confinement absents. | Security + Platform | avant release candidate | Open |
| `SEC-GAP-009` | `ADR-002` propose GitHub Actions, lockfiles Composer/npm, scanning, SBOM et provenance ; leur configuration durcie et leurs preuves restent à implémenter. | Engineering + Security | incrément 0 | Open |

Ces gaps ne justifient pas d'inventer une valeur dans ce document. Leur
résolution met à jour les références, contrôles, tests et risques concernés.

---

## Exigences de détection et réponse

Le MVP doit rendre recherchables, sans secret brut :

- échecs et succès d'authentification, récupération, preuve et step-up ;
- création, rotation, élévation, révocation et rejeu de session ;
- refus d'autorisation, grants critiques et usages `SystemActorOnly` ;
- accès publics invalides et décisions Quote ;
- mutations financières sensibles, conflicts et inversions ;
- événements/callbacks invalides, duplicates, gaps, DLQ et lag ;
- résolution/revalidation d'un effet externe et résultat fournisseur ;
- changements de configuration, migration, secret et accès privilégié ;
- sauvegarde, restore, export et restriction Workspace.

Une alerte ne contient ni credential, ni preuve, ni document, ni adresse brute.
Les runbooks privilégient confinement réversible : révocation, rotation,
restriction, désactivation d'un adapter ou feature flag, puis préservation des
preuves et remédiation.

---

## Données interdites par support

Les fixtures, captures, tickets et environnements de support ne doivent pas
copier automatiquement :

- secrets et credentials ;
- preuves publiques ou de récupération ;
- documents financiers réels ;
- adresses complètes, contacts ou références de paiement ;
- logs non expurgés ;
- sauvegardes de production.

Les scénarios MVP utilisent des données synthétiques déterministes. Une analyse
d'incident nécessitant des données réelles suit un accès borné, audité et
temporaire défini par la future politique opérateur.

---

## Critères de validation du modèle

Le modèle est prêt à devenir `Stable` lorsque :

1. Product confirme actifs, flux sensibles et impacts ;
2. Engineering confirme frontières, hypothèses et faisabilité des contrôles ;
3. Security confirme couverture, risques initiaux et résiduels cibles ;
4. chaque menace conserve contrôle, détection, test et owner ;
5. chaque gap possède une échéance de gate et aucun blocage n'est masqué ;
6. les décisions de stack ajoutent leurs menaces sans affaiblir cette baseline ;
7. les tests prioritaires sont reliés à l'incrément d'implémentation concerné.

---

## Déclencheurs de réexamen

- nouveau transport, fournisseur, endpoint public ou authenticator ;
- changement de topologie, stockage, messaging, chiffrement ou CI/CD ;
- nouvelle donnée `Restricted` ou `Confidential` ;
- exposition API externe, mobile, connecteur, Automation ou multi-workspaces ;
- modification d'une preuve, permission, capacité système ou workflow critique ;
- incident, vulnérabilité significative ou échec d'un contrôle ;
- évolution majeure du référentiel ASVS/NIST adopté.

La revue met à jour la version du modèle et conserve l'historique des risques
acceptés ou remplacés.
