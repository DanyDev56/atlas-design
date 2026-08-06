---
id: ADR-002
title: MVP Implementation Stack
status: Proposed
date: 2026-08-06
owner: Engineering
version: 0.2.0
last_updated: 2026-08-06

references:
  - README.md
  - ADR-001-mvp-application-topology.md
  - ../security/mvp-threat-model.md
  - ../../evolution/blueprint/implementation-plan.md
  - ../../evolution/roadmap/mvp-acceptance.md
  - ../../evolution/reference-fixtures/README.md
---

# ADR-002 — Stack d'implémentation du MVP

## Contexte

`ADR-001` fixe un modular monolith à frontières fortes, trois rôles
d'exécution et une persistance possédée par module. Il ne choisit ni langage,
ni framework, ni datastore, ni pipeline. L'incrément 0 ne peut devenir
exécutable tant que ces décisions restent implicites.

La stack doit servir un produit transactionnel avec :

- huit bounded contexts et des règles de dépendance contrôlables ;
- montants exacts, périodes civiles, concurrence optimiste et audit ;
- état et outbox commis atomiquement ;
- API, Worker et Scheduler issus du même commit ;
- interface web accessible et responsive ;
- reconstruction Analytics et traitements idempotents ;
- petite équipe et charge MVP encore inconnue ;
- exigences Security sur l'isolation Workspace, les secrets, les builds et les
  effets externes.

Cette proposition choisit une baseline. Elle ne devient normative qu'après le
passage de l'ADR à `Accepted`.

---

## Forces de décision

La décision privilégie, dans cet ordre :

1. l'exactitude du domaine, des transactions et de l'isolation Workspace ;
2. la vitesse de livraison par une petite équipe ;
3. des frontières de modules vérifiables automatiquement ;
4. une exécution locale et CI reproductible ;
5. la sécurité et l'observabilité dès l'incrément 0 ;
6. un coût d'exploitation faible avant validation du produit ;
7. des composants maintenus, portables et remplaçables ;
8. l'absence de service distribué ou fournisseur propriétaire sans besoin
   mesuré.

---

## Options étudiées

### Option A — PHP, Laravel, React et PostgreSQL

Le backend, les workers, le scheduler et les contrats utilisent PHP strict.
Laravel fournit le shell HTTP et opérationnel, React/Vite porte l'application
web et PostgreSQL fournit transactions, contraintes et stockage durable.

Avantages :

- socle intégré pour HTTP, validation, authentification, autorisation, queues,
  scheduling, migrations et tests ;
- boucle de développement courte et conventions adaptées à une petite équipe ;
- transactions et workers compatibles avec l'outbox PostgreSQL du MVP ;
- PHP permet des Value Objects immuables, enums et types explicites ;
- React/Vite reste utilisable dans le même codebase et la même release.

Risques :

- les conventions Laravel favorisent un modèle applicatif global si les
  frontières ne sont pas imposées ;
- Eloquent et les facades rendent les accès transverses faciles à introduire ;
- la queue Laravel et `afterCommit()` ne constituent pas à eux seuls un
  transactional outbox ;
- PHP et TypeScript restent deux toolchains pour le backend et l'interface ;
- les upgrades majeurs annuels de Laravel doivent être planifiés.

### Option B — TypeScript, Node.js, Fastify, React et PostgreSQL

TypeScript permet un langage principal de l'interface aux rôles backend et un
framework HTTP mince. Cette option partage davantage de primitives techniques,
mais demande d'assembler plus de capacités applicatives, de worker et de
scheduling, et de compenser systématiquement l'effacement des types à
l'exécution.

### Option C — C#/.NET, ASP.NET Core, React et PostgreSQL

.NET fournit un backend fortement typé, un runtime mature, des workers intégrés
et un bon support des transactions. Il offre des types de domaine riches et
`decimal`, mais ajoute un écosystème moins aligné avec le choix d'une stack
productive et familière pour le MVP.

### Option D — Kotlin, Spring Boot, React et PostgreSQL

Kotlin et Spring offrent typage, écosystème transactionnel et conventions
robustes. Ils conviennent à un domaine complexe, mais augmentent le coût du
démarrage, la consommation du runtime et la surface de configuration du MVP.

---

## Décision

La proposition retient **l'option A** avec la baseline suivante.

| Zone | Choix MVP |
|---|---|
| Langage backend | PHP 8.5 strict, dernier patch supporté |
| Framework | Laravel 13, dernière release compatible |
| API HTTP | routes, middleware, Form Requests et API Resources comme adapters |
| Interface | React 19 stable, client HTTP construit par Vite |
| Datastore | PostgreSQL 18, dernier minor supporté |
| Persistence | Laravel Database sur PDO PostgreSQL ; Eloquent borné aux adapters |
| Messaging MVP | outbox, inbox, checkpoints et leases Atlas dans PostgreSQL |
| Workers | Laravel Queue avec driver database, déclenchée depuis l'outbox canonique |
| Scheduling | Laravel Scheduler émettant uniquement des intentions système durables |
| Observabilité | logs JSON structurés, OpenTelemetry et export OTLP |
| Tests | Pest sur PHPUnit, PostgreSQL réel et Playwright en bout en bout |
| Packaging | Composer, `composer.lock`, npm pour le web et artefacts OCI |
| CI | GitHub Actions durci, actions épinglées par SHA complet |
| Hébergement | compute conteneurisé et PostgreSQL managés en région UE |

Les versions exactes de patch sont verrouillées par manifestes et lockfiles au
scaffold. Un upgrade mineur ou patch reste une maintenance ; un changement de
runtime, framework majeur, datastore ou stratégie de messaging réexamine cet
ADR.

### Backend et frontières

Le code suit les frontières d'`ADR-001` :

```text
apps/
  api/
  worker/
  scheduler/
  web/

src/modules/
  identity/
  workspace/
  crm/
  billing/
  analytics/
  business-health/
  advisor/
  notifications/

src/composition/
  onboarding/
  dashboard/
  settings/

src/platform/
  persistence/
  messaging/
  security/
  observability/
  scheduling/
```

Chaque module expose un namespace `Contracts` public. Ses namespaces `Domain`,
`Application`, `Infrastructure`, `Migrations` et `Tests` restent internes. Les
facades Laravel, Eloquent, React ou PostgreSQL n'apparaissent pas dans les
décisions pures du domaine.

Le container Laravel compose les ports et adapters dans un Service Provider par
module. Une résolution par le container ne donne pas le droit d'importer un
type interne d'un autre module.

La CI refuse :

- l'import de l'interne d'un autre module ;
- une dépendance du domaine vers Laravel, Eloquent, React, SQL ou un provider ;
- un cycle entre modules métier ;
- un modèle Eloquent ou repository partagé ;
- une migration ou requête visant un schéma non possédé.

### Types et contrats

- `declare(strict_types=1)` est obligatoire dans tout fichier PHP first-party ;
- l'analyse statique s'exécute au niveau maximal avec une extension consciente
  de Laravel et sans baseline silencieuse ;
- les objets métier privilégient classes `final readonly`, enums et
  constructeurs privés validants ;
- les identifiants métier sont des Value Objects distincts ;
- les montants utilisent des unités mineures entières et ne passent jamais par
  un flottant ;
- ratios et moyennes conservent leurs composantes exactes ;
- les instants contractuels sont UTC et les calculs civils utilisent le fuseau
  et le calendrier Workspace versionnés ;
- les erreurs publiques utilisent le catalogue stable du domaine propriétaire.

Chaque entrée et sortie HTTP possède un schéma JSON fermé et versionné. Les
Form Requests et API Resources Laravel sont des adapters de ce schéma, pas sa
source implicite. Le client TypeScript est généré ou vérifié depuis le contrat
public ; aucune classe PHP métier n'est partagée avec React.

### HTTP, Identity et interface

Laravel possède uniquement, à la frontière HTTP : authentification de
l'enveloppe, validation et normalisation, limites de taille et temps, mapping
des erreurs, corrélation et appel du port applicatif propriétaire.

Les guards, middleware et capacités d'authentification Laravel adaptent les
contrats Identity. Ils ne possèdent ni `UserSecurityVersion`, ni les règles de
session, de Membership ou de step-up documentées par le domaine.

React consomme les contrats HTTP first-party. L'interface ne contient ni
formule Analytics, ni politique Business Health, ni rang Advisor, ni décision
de canal Notifications. Les écrans publics bornés restent des clients de
preuves opaques et de contrats Billing.

Le frontend est un build Vite statique versionné dans le même codebase. Inertia,
Livewire, les accès Eloquent depuis une page et un second serveur métier
frontend sont exclus de la baseline initiale. Leur adoption réexamine le
contrat API-first de cet ADR.

### Persistence

PostgreSQL utilise un schéma par module et, dans les environnements de
validation et production, une identité de connexion bornée par rôle et module.
Aucune vue, clé étrangère, relation Eloquent, jointure, fonction ou trigger ne
traverse les schémas métier.

Les adapters de persistence utilisent Laravel Database avec :

- paramètres obligatoires pour toute valeur non statique ;
- transaction explicite autour de chaque unité de travail ;
- deadlock retry borné seulement lorsque la commande est idempotente ;
- timeouts explicites et connexions bornées ;
- contraintes d'unicité pour idempotence et clés naturelles ;
- version d'agrégat pour concurrence optimiste ;
- migrations possédées, ordonnées et testées par module.

Eloquent peut mapper une table à l'intérieur d'un adapter. Un modèle Eloquent
n'est jamais un agrégat public, un contrat, une dépendance d'un autre module ou
un objet sérialisé dans un Domain Event. Le Query Builder ou SQL explicite est
préféré lorsque l'ORM masque concurrence, locks ou exactitude.

### Messaging et scheduling

La queue Laravel ne remplace pas les primitives Atlas. Le commit d'une mutation
écrit état, audit, Domain Events et outbox dans la même transaction PostgreSQL.
`afterCommit()` peut retarder un dispatch technique, mais ne constitue pas la
preuve atomique de l'intention.

Le Worker :

1. revendique un lot d'outbox borné avec verrou non bloquant ;
2. crée un job technique identifié par l'`EventId` stable sans perdre la source
   outbox en cas d'arrêt ;
3. déduplique dans l'inbox du consommateur ;
4. commit état, inbox, checkpoint et éventuelle outbox du consommateur dans sa
   transaction locale ;
5. applique retry borné, backoff, failed job, dead-letter et alerte ;
6. ne marque une livraison achevée qu'après résultat durable du consommateur.

Le driver `database` conserve la baseline sans Redis ni broker externe.
Horizon n'est pas utilisé au MVP, car il impose Redis. Un broker ou Horizon
n'est évalué qu'après mesure d'un besoin de débit, d'isolation de panne ou
d'exploitation non satisfait par PostgreSQL.

Laravel Scheduler coordonne les déclenchements avec verrou et exécution sur une
instance. Chaque tâche émet toutefois une intention système stable et durable ;
elle ne modifie jamais directement une table métier. API, Worker et Scheduler
utilisent le même commit et des credentials distincts.

### Notifications et effets externes

Mail, HTTP client et Notifications Laravel sont des adapters réservés au module
Notifications ou au module propriétaire de l'effet. Aucun autre module ne peut
envoyer directement un e-mail, appeler un fournisseur ou utiliser une
Notification Laravel pour contourner les consentements, budgets et preuves de
livraison Atlas.

### Observabilité

- les logs sont JSON, structurés, redacted et corrélés sans secrets ni PII ;
- OpenTelemetry porte traces, métriques et corrélation des logs via OTLP ;
- les spans relient requête, commande, transaction, outbox, job et effet
  externe ;
- le contexte est propagé explicitement dans les enveloppes et jobs ;
- les métriques couvrent RED, lag, checkpoints, retries, failed jobs, pool SQL
  et budgets fournisseurs ;
- l'instrumentation navigateur reste minimale et ne copie aucune donnée métier.

### Tests

Pest exécute les tests unitaires, d'architecture, de contrat, HTTP et console
sur PHPUnit. Les tests de persistence et messaging utilisent la même major
PostgreSQL que la production, jamais SQLite comme substitut transactionnel.

Playwright valide les trois parcours MVP, l'accessibilité utile, les preuves
publiques et les principaux navigateurs. Les fixtures
`evolution/reference-fixtures/mvp-v1.json` restent l'oracle Analytics →
Notifications.

### Build, supply chain et déploiement

GitHub Actions exécute au minimum :

```text
composer install from lockfile + npm install from lockfile
  -> format + lint + static analysis
  -> architecture + unit + contract tests
  -> PostgreSQL integration tests
  -> fixture and documentation checkers
  -> frontend build + end-to-end tests
  -> migration validation
  -> OCI build + scan + SBOM + provenance
```

- les scripts Composer et npm non nécessaires sont désactivés pendant les jobs
  non fiables ;
- les actions tierces sont allowlistées et épinglées par SHA complet ;
- les permissions du `GITHUB_TOKEN` sont minimales par job ;
- aucun secret de déploiement n'est disponible aux jobs de pull request ;
- les déploiements utilisent une identité OIDC courte plutôt qu'une clé cloud
  durable ;
- l'image est adressée par digest et associée au commit, au SBOM et à sa
  provenance ;
- les promotions d'environnement réutilisent le même digest.

Le même commit produit un rôle HTTP PHP-FPM, un rôle Worker et un rôle
Scheduler. Octane est exclu de la baseline tant qu'une mesure ne justifie pas
son état long-lived et sa surface opérationnelle supplémentaire.

Le runtime cible est une plateforme de conteneurs managée dans une région UE,
avec PostgreSQL managé, stockage objet managé pour les artefacts documentaires,
gestionnaire de secrets, chiffrement, sauvegardes et restauration point-in-time.
Le fournisseur, le RPO/RTO, la rétention et la rotation restent à décider avant
production dans la résolution de `SEC-GAP-004` à `SEC-GAP-006`.

---

## Raisons

Laravel réduit le code d'assemblage nécessaire aux capacités transversales du
MVP tout en laissant le domaine en PHP pur. Ses conventions accélèrent les
tranches verticales si elles s'arrêtent aux adapters ; les contrôles
d'architecture compensent leur tendance naturelle à un modèle global.

PostgreSQL fournit la transaction locale nécessaire à l'outbox, des contraintes
fortes et une exploitation connue. La queue et le scheduler Laravel fournissent
le cycle de vie des processus sans devenir la vérité des messages ou des
intentions. React/Vite préserve une interface riche et le contrat API-first.

La proposition s'appuie sur les politiques et documentations officielles :

- [Laravel 13 supporte PHP 8.3 à 8.5 et publie sa politique de maintenance](https://laravel.com/docs/13.x/releases) ;
- [PHP publie les échéances de support de chaque branche](https://www.php.net/supported-versions.php) ;
- [Laravel Database couvre transactions et retries de deadlock](https://laravel.com/docs/13.x/database#database-transactions) ;
- [Laravel documente le driver database, les workers et le dispatch après commit](https://laravel.com/docs/13.x/queues) ;
- [Laravel Scheduler documente verrous et exécution sur une instance](https://laravel.com/docs/13.x/scheduling) ;
- [Laravel prend en charge React et Vite dans le même codebase](https://laravel.com/docs/13.x/frontend) ;
- [Horizon requiert Redis](https://laravel.com/docs/13.x/horizon) ;
- [OpenTelemetry PHP stabilise traces, métriques et logs](https://opentelemetry.io/docs/languages/php/) ;
- [PostgreSQL maintient une major pendant cinq ans](https://www.postgresql.org/support/versioning/) ;
- [GitHub recommande d'épingler les Actions par SHA complet](https://docs.github.com/en/actions/reference/security/secure-use) ;
- [Docker BuildKit produit SBOM et provenance OCI](https://docs.docker.com/build/metadata/attestations/) ;
- [Playwright couvre Chromium, Firefox et WebKit](https://playwright.dev/docs/browsers).

---

## Conséquences

### Positives

- Laravel fournit un socle cohérent pour API, workers, scheduler et tests ;
- le domaine conserve des objets PHP explicites indépendants du framework ;
- PostgreSQL porte transaction, outbox, inbox et leases sans broker initial ;
- les contrats HTTP restent versionnés et consommables par React ;
- les trois rôles sont construits et publiés depuis le même commit ;
- OpenTelemetry PHP couvre les trois signaux ;
- l'écosystème réduit le temps consacré à l'infrastructure générique.

### Négatives

- backend PHP et frontend TypeScript exigent deux toolchains ;
- Eloquent et les facades nécessitent des règles d'architecture strictes ;
- la génération ou vérification du client React ajoute une étape de contrat ;
- PostgreSQL porte au départ état, queue et leases, donc sa santé est critique ;
- Horizon et son dashboard ne sont pas disponibles sans ajouter Redis ;
- Laravel impose une maintenance planifiée de ses releases majeures annuelles ;
- le fournisseur cloud et les objectifs de reprise restent à décider.

### Risques acceptés pour le spike

- la séparation des modules doit résister au container, aux facades, aux
  modèles Eloquent, aux events et aux jobs Laravel ;
- le pont outbox → queue database doit être prouvé sans perte et avec
  duplication convergente ;
- la séparation par rôles PostgreSQL doit être prouvée sur un environnement
  proche de la production ;
- les workers long-lived doivent libérer état, connexions et contexte entre
  deux jobs ;
- le schéma contractuel doit détecter toute dérive des Form Requests, API
  Resources et types du client React.

---

## Conditions d'implémentation

L'ADR peut passer à `Accepted` lorsque le spike de l'incrément 0 prouve les
douze conditions suivantes :

1. PHP 8.5 exécute Laravel 13, Pest et le driver PostgreSQL choisis ;
2. `composer.lock`, le lockfile npm et les versions de toolchain sont
   reproductibles en local et CI ;
3. une règle automatique refuse import interne, cycle métier, modèle Eloquent
   partagé et dépendance du domaine vers Laravel ;
4. un module exemple commit agrégat, audit, Domain Event et outbox dans une
   seule transaction PostgreSQL ;
5. deux Workers Laravel concurrents revendiquent sans double effet et un replay
   converge par inbox ;
6. une identité SQL ne peut lire ou migrer le schéma d'un autre module ;
7. toute route Laravel refuse payload inconnu, invalide ou surdimensionné avant
   l'application et valide son contrat de réponse ;
8. une trace relie HTTP, transaction, outbox, job et consumer sans donnée
   sensible ;
9. les fixtures MVP, les tests PHP/React et tous les checkers du dépôt passent
   dans GitHub Actions ;
10. le pipeline produit une image OCI scannée avec SBOM et provenance ;
11. l'arrêt et la reprise API/Worker/Scheduler ne perdent aucune intention ;
12. les limites mesurées et risques restants sont consignés avant acceptation.

L'acceptation de cet ADR ne ferme pas automatiquement les gaps Identity,
Billing, rétention, opérations ou cloud du modèle de menace.

---

## Réexamen

La décision est réexaminée si :

- la beta mesure un débit ou lag incompatible avec l'outbox PostgreSQL ou la
  queue database ;
- un module exige isolation de panne, sécurité ou résidence distincte ;
- PHP ou Laravel sort de maintenance avant la release ;
- le contrat API-first devient un frein produit mesuré justifiant Inertia ;
- un besoin de performance justifie l'évaluation d'Octane ;
- un fournisseur impose une contrainte non portable sur compute ou données ;
- le coût des frontières Laravel dépasse le gain de son socle intégré ;
- un incident révèle une faiblesse systémique du runtime ou de la supply chain.

Un changement de préférence sans donnée mesurée ne suffit pas. Toute évolution
continue de respecter les frontières, transactions locales et critères
d'extraction d'`ADR-001`.
