---
id: ADR-001
title: MVP Application Topology
status: Accepted
date: 2026-08-06
owner: Engineering
version: 1.0.1
last_updated: 2026-08-06

references:
  - README.md
  - ../constitution.md
  - ../domain-map/context-map.md
  - ../domain-map/dependencies.md
  - ../domain-map/ownership.md
  - ../../evolution/blueprint/implementation-plan.md
  - ../../evolution/roadmap/mvp-acceptance.md
  - ../../evolution/governance/quality-gates.md
  - ../security/mvp-threat-model.md
---

# ADR-001 — Topologie applicative du MVP

## Contexte

Atlas possède huit bounded contexts consolidés : Identity, Workspace, CRM,
Billing, Analytics, Business Health, Advisor et Notifications. Le MVP doit
prouver trois parcours de bout en bout avec peu d'incertitude opérationnelle,
tout en conservant les frontières nécessaires à l'explicabilité, la sécurité
et l'évolution future du produit.

Deux risques opposés doivent être évités :

- distribuer prématurément le système en services indépendants et payer avant
  validation les coûts de réseau, déploiement, observabilité et cohérence ;
- construire un monolithe en couches techniques avec un modèle et une base
  partagés, ce qui annulerait le travail de conception des domaines.

La topologie doit aussi respecter les contrats déjà établis : transactions
locales aux agrégats, concurrence optimiste, outbox atomique, consommateurs
idempotents, relecture de révisions exactes et absence de transaction distribuée.

---

## Forces de décision

La décision privilégie, dans cet ordre :

1. l'intégrité des frontières et de l'isolation Workspace ;
2. la livraison rapide des tranches verticales MVP ;
3. la cohérence transactionnelle locale et la reprise déterministe ;
4. la simplicité de développement, test, déploiement et exploitation ;
5. l'observabilité de la chaîne complète ;
6. la possibilité d'extraire un module sans réécrire son modèle ;
7. l'absence de dépendance prématurée à un langage, cloud ou broker précis.

---

## Options étudiées

### Option A — Modular monolith à frontières fortes

Un codebase et une version applicative, plusieurs modules métier fermés, des
contrats publics explicites et un stockage possédé par module. Des rôles
d'exécution distincts peuvent utiliser le même artefact.

### Option B — Un service déployable par bounded context

Chaque domaine possède immédiatement son processus, son déploiement, son réseau
et son datastore indépendants.

### Option C — Monolithe en couches avec modèle et schéma partagés

Controllers, services et repositories sont organisés par couche technique. Les
tables et objets peuvent être utilisés transversalement.

### Option D — Backend serverless par fonction

Chaque commande, lecture ou consumer devient une fonction déployée séparément,
avec orchestration et infrastructure managées.

---

## Décision

Atlas adopte **l'option A : un modular monolith à frontières fortes pour le
MVP**.

Cette décision concerne la topologie logique et de livraison. Elle ne choisit
ni langage, ni framework, ni fournisseur cloud, ni moteur de base de données,
ni broker. Ces choix doivent respecter les contraintes ci-dessous et pourront
faire l'objet d'ADR séparés.

---

## Unité de version et rôles d'exécution

Le MVP possède :

- un codebase applicatif versionné ;
- un artefact ou ensemble d'artefacts construits depuis le même commit ;
- une release coordonnée ;
- une configuration par environnement ;
- des migrations regroupées par module et appliquées avant activation de la
  version qui les consomme.

La même version peut s'exécuter sous plusieurs rôles :

```text
API role
  commandes et lectures first-party

Worker role
  outbox, consumers, projections et effets externes

Scheduler role
  intentions temporelles bornées et auditables
```

Ces rôles ne sont pas des microservices métier. Ils utilisent les mêmes modules
et contrats, sont publiés ensemble et ne possèdent aucune vérité différente.
Le Scheduler émet une intention système ; il ne modifie jamais directement une
table métier.

---

## Modules obligatoires

La structure logique comporte exactement les huit domaines MVP :

```text
modules/
  identity/
  workspace/
  crm/
  billing/
  analytics/
  business-health/
  advisor/
  notifications/

composition/
  onboarding/
  dashboard/
  settings/

platform/
  persistence/
  messaging/
  security/
  observability/
  scheduling/
```

Cette arborescence est illustrative et indépendante d'un langage. Chaque module
métier sépare au minimum :

```text
domain
application
contracts
adapters
migrations
tests
```

- `domain` ne dépend d'aucun autre module métier ni d'un framework d'entrée ;
- `application` orchestre les agrégats du module et ses ports ;
- `contracts` contient uniquement les commandes, lectures, résultats, erreurs
  et événements publics ;
- `adapters` implémente transport, persistence et fournisseurs ;
- `migrations` ne modifie que le stockage possédé par le module ;
- `tests` inclut invariants, contrats et isolation.

Le dossier `platform` peut fournir des primitives techniques. Il ne contient
aucun concept tel que Client, Invoice, Membership, Recommendation ou
Notification, ni aucun Value Object métier partagé par commodité.

---

## Propriété du stockage

Un moteur transactionnel commun peut être utilisé au MVP afin de simplifier
l'exploitation. La séparation reste néanmoins obligatoire :

- chaque module possède son namespace ou schéma ;
- seul le module propriétaire exécute des lectures, écritures et migrations sur
  ce namespace ;
- aucune jointure, vue, trigger ou clé étrangère ne traverse deux modules ;
- aucune entité ORM ou repository privé n'est importé par un autre module ;
- les read models de composition conservent leur provenance et sont
  reconstructibles ;
- les sauvegardes et restaurations préservent une cohérence de versions connue.

Des identités de connexion distinctes par module sont recommandées en
production pour rendre la règle vérifiable par le datastore. Si l'outillage de
développement utilise temporairement une identité commune, les tests
d'architecture et revues de migration restent obligatoires.

Atlas utilise par défaut une persistance d'état des agrégats avec Domain Events
et audit. L'event sourcing générique n'est pas requis. Les historiques déclarés
immuables par un domaine restent immuables indépendamment de cette décision.

---

## Transactions et effets externes

Une transaction ne traverse jamais un module.

```text
adapter
  -> owning application service
  -> aggregate decision
  -> commit state + Domain Events + outbox atomically
  -> return committed result

outbox dispatcher
  -> durable transport
  -> consuming module
  -> deduplicate in inbox
  -> commit consumer state + checkpoint + optional outbox atomically
```

Le transport garantit une livraison **at least once**. Atlas ne revendique pas
un traitement exactement une fois : l'effet métier est rendu unique par les
clés d'idempotence, `EventId`, versions d'agrégat, cursors et contraintes du
domaine.

Il n'existe aucun ordre global des événements. Les consommateurs utilisent la
version de l'agrégat, le sujet d'ordre documenté ou une relecture exacte pour
détecter duplications, retards et trous.

Le dispatcher peut commencer par un mécanisme durable adossé au datastore. Un
broker externe reste un adaptateur remplaçable et n'est introduit que si les
besoins mesurés de débit, isolation ou exploitation le justifient.

---

## Communication entre modules

Deux formes sont autorisées.

### Contrat synchrone

Utilisé lorsqu'une intention a besoin d'une décision courante avant son commit,
par exemple l'autorisation Identity, le contexte d'accès Workspace ou un
snapshot CRM/Workspace nécessaire à Billing.

Le consommateur dépend d'une interface publique et d'un DTO versionné, jamais
d'une classe de domaine ou d'un repository source. Les données obtenues avant
la transaction sont snapshotées avec leur version lorsque le domaine le
requiert.

Un appel synchrone ne propage jamais une transaction vers le fournisseur et ne
forme pas une longue chaîne de mutations synchrones.

### Événement asynchrone

Utilisé pour annoncer un fait commité, déclencher une projection ou poursuivre
une saga. Un consumer ne modifie que son propre stockage et rappelle le contrat
public source lorsqu'il doit obtenir une révision exacte.

Le partenariat bidirectionnel Identity–Workspace est câblé par des ports au
niveau de la composition. Il ne crée pas de cycle de dépendance entre leurs
packages internes.

---

## Orchestrations et sagas

Les workflows multi-domaines, notamment le bootstrap Workspace et
`QuoteAccepted -> OpportunityWon`, sont des coordinations explicites :

- état de progression durable ;
- étapes et compensations ou décisions de reprise documentées ;
- clé d'idempotence dérivée du workflow ;
- timeouts et échecs observables ;
- aucune possession des agrégats participants ;
- aucune suppression destinée à masquer un état partiel.

La coordination vit dans la couche application ou composition appropriée. Son
état technique ne devient pas une nouvelle vérité métier et chaque domaine
reste seul à accepter ou refuser sa commande.

---

## Surfaces de composition

Onboarding, Dashboard et Settings ne sont pas des modules métier.

- Onboarding coordonne les contrats Identity et Workspace ;
- Dashboard compose des lectures publiques et peut maintenir un cache jetable ;
- Settings route vers les écrans et commandes de leurs propriétaires.

Ces surfaces ne possèdent aucun agrégat source, ne recalculent aucune règle et
ne contournent jamais l'autorisation du module cible.

---

## Isolation et sécurité architecturales

Cette décision ne remplace pas le modèle de menace, mais impose déjà :

- `WorkspaceId` explicite sur tout contrat contextualisé ;
- résolution et revalidation du principal ou workload ;
- refus par défaut si le contexte requis est absent ;
- aucune variable globale ou contexte implicite réutilisé entre requêtes ;
- aucune donnée brute sensible dans les messages, logs ou caches ordinaires ;
- secrets fournis uniquement aux adaptateurs spécialisés ;
- audit des décisions et actions sensibles avec corrélation.

Les tests d'isolation vérifient les commandes, lectures, consumers, jobs,
caches et exports, pas uniquement les routes HTTP.

---

## Enforcement automatique

La build doit échouer si :

- un module importe l'interne d'un autre ;
- un domaine dépend d'un adapter ou de la composition ;
- une migration cible le namespace d'un autre module ;
- une requête ou un mapping déclare une relation de persistence transverse ;
- une commande inter-domaine tente de partager une transaction ;
- un événement public n'a ni identité, ni version, ni causalité ;
- un consumer ne possède pas de stratégie de déduplication ;
- une surface de composition reproduit une formule ou un invariant métier.

Des tests de contrat vérifient chaque frontière de la matrice MVP. Les adapters
internes et futurs adapters réseau doivent passer le même corpus.

---

## Raisons

Le modular monolith :

- minimise le nombre de systèmes à déployer pendant la validation du produit ;
- rend les refactorings et tranches verticales moins coûteux ;
- permet des transactions locales fiables sans protocole distribué ;
- conserve des frontières testables et extractibles ;
- simplifie une trace de bout en bout et la reproduction locale ;
- évite de transformer une hypothèse de scalabilité en complexité permanente.

L'option B est rejetée pour le MVP car aucune mesure ne justifie encore le coût
de huit services indépendants. L'option C est rejetée car elle contredit la
propriété des domaines. L'option D est rejetée car la granularité de déploiement
augmenterait la complexité des workflows, du cold start et de l'observabilité
avant d'apporter une valeur utilisateur.

---

## Conséquences

### Positives

- un environnement local et une release restent reproductibles ;
- les parcours MVP peuvent évoluer dans un même changement atomique de code ;
- les appels internes n'imposent pas de latence réseau ;
- les Domain Events et contrats préparent une extraction future ;
- la cohérence et l'isolation sont vérifiables module par module.

### Négatives

- une erreur de processus peut affecter plusieurs modules malgré leurs
  frontières logiques ;
- le scaling est d'abord celui d'un rôle d'exécution, pas d'un service métier ;
- la discipline de code et de migration doit compenser l'absence d'une barrière
  réseau ;
- une release coordonnée limite l'autonomie de déploiement future ;
- une base commune exige des contrôles explicites contre les accès transverses.

### Coûts acceptés

- outbox, inbox, checkpoints et observabilité sont implémentés dès l'incrément 0 ;
- des tests d'architecture et de contrats font partie de la CI ;
- les frontières peuvent introduire des DTOs et mappings apparemment redondants ;
- les résultats dérivés restent éventuellement cohérents même dans un seul
  processus.

---

## Critères d'extraction future

Un module peut devenir un service indépendant seulement si au moins une
contrainte mesurée le justifie :

- profil de charge ou de stockage très différent ;
- isolation de panne ou de sécurité impossible dans le processus courant ;
- exigence de résidence, rétention ou disponibilité distincte ;
- cadence de livraison et équipe réellement autonomes ;
- coût opérationnel du partage supérieur au coût du réseau et de la
  distribution.

Avant extraction, le module doit disposer de contrats stables, d'un stockage
sans dépendance transverse, de tests de compatibilité, d'une stratégie de
migration et d'observabilité réseau. Le consommateur ne doit pas changer son
intention lorsque l'appel passe d'un adapter local à un adapter distant.

---

## Conditions d'implémentation

L'incrément 0 est terminé lorsque :

1. les huit modules et trois surfaces de composition possèdent des règles de
   dépendance exécutables ;
2. un module exemple commit état et outbox atomiquement ;
3. un consumer rejoue le même événement sans double effet ;
4. une migration transverse et un import interne interdit font échouer la CI ;
5. une trace relie commande, transaction, message et consumer ;
6. la panne du worker ne perd aucun événement et la reprise converge ;
7. les décisions de stack et le modèle de menace compatibles avec cet ADR sont
   documentés avant la production.

---

## Réexamen

Cette décision est réexaminée après la beta MVP ou plus tôt si une contrainte
mesurée satisfait un critère d'extraction. Une préférence technologique, une
mode architecturale ou l'existence théorique de futurs clients ne suffisent pas.

Toute évolution conserve les frontières de propriété, même si la topologie de
déploiement change.
