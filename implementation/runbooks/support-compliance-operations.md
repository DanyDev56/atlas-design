---
id: RUN-022
title: Support and Compliance Operations
status: In Review
owner: Support, Operations and Legal
version: 0.3.0
last_updated: 2026-08-24

references:
  - backoffice-access.md
  - beta-support-offboarding.md
  - beta-terms.fr.md
  - privacy-notice.fr.md
  - ../../evolution/blueprint/backoffice.md
  - ../../evolution/blueprint/backoffice-implementation-plan.md
---

# Support et conformité dans le back-office

## Portée livrée

`/backoffice/support` expose trois registres Operations minimisés :

- dossiers support ouverts, priorité et échéance de première réponse ;
- demandes relatives aux données, type, état et échéance ;
- versions de conditions et de notice, preuves associées et consentements de
  recherche facultative actifs.

Les listes sont filtrées et paginées côté serveur. Elles ne chargent ni email,
UUID brut, contenu d'échange, justificatif, document juridique, référence de
preuve brute ou donnée d'un Workspace. Les corrélations utilisent des
références HMAC locales `WS-*` et `USR-*`.

La lecture reste le comportement par défaut. Une première action web bornée
permet, lorsqu'elle est explicitement activée, de changer le statut d'un dossier
Support et de l'assigner à l'opérateur courant ou de le désassigner.

Les demandes `Access` et `Portability` qualifiées disposent désormais d'un
workflow d'export assisté séparé : demande, approbation par une autre identité
Operator, génération asynchrone, artefact chiffré et téléchargement expirant.
Il ne produit aucun email et ne ferme, ne corrige, ne restreint ni ne supprime
le Workspace.

## Autorisations

| Permission | Effet |
|---|---|
| `operations.support.read` | compteurs et liste des dossiers support |
| `operations.compliance.read` | demandes de données, politiques, preuves et consentements |
| `operations.support.manage` | prévisualiser et confirmer un changement borné de statut ou d'assignation Support |
| `operations.compliance.manage` | autorité attendue pour administrer les registres ; aucun bouton web livré |
| `operations.exports.request` | préparer un export sur une demande de données vérifiée |
| `operations.exports.approve` | approuver la demande créée par un autre opérateur et lancer la génération |
| `operations.exports.download` | télécharger l'artefact prêt avant son expiration pour sa remise contrôlée |

## Activer la gestion bornée des dossiers

L'action reste coupée par deux verrous serveur et n'est destinée qu'à une
recette locale encadrée tant que l'accès externe fort n'est pas livré :

```dotenv
BACKOFFICE_READ_ONLY=false
BACKOFFICE_ACTIONS_ENABLED=true
```

Après modification, purger le cache de configuration puis accorder explicitement
`operations.support.manage` en plus des permissions de lecture :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan config:clear
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:grant demo@atlas.test \
  --permissions="operations.backoffice.access,operations.dashboard.read,operations.support.read,operations.support.manage,operations.compliance.read" \
  --reason="Recette locale de la gestion Support"
```

Une session avec MFA et un step-up encore valide est obligatoire. L'interface
impose ensuite une prévisualisation exacte avant confirmation. La confirmation
porte une clé d'idempotence stable, une révision attendue et l'empreinte de la
prévisualisation ; un rejeu identique renvoie le même résultat, tandis qu'un
rejeu différent, une révision périmée ou une autorité révoquée est refusé.

Les transitions autorisées sont :

- `Open` vers `Acknowledged` ou `InProgress` ;
- `Acknowledged` vers `InProgress`, `WaitingRequester` ou `Resolved` ;
- `InProgress` vers `WaitingRequester` ou `Resolved` ;
- `WaitingRequester` vers `InProgress` ou `Resolved` ;
- `Resolved` vers `InProgress` ou `Closed`.

Le motif provient d'une taxonomie structurée, jamais d'un texte libre. La
mutation du dossier, ses événements append-only, le résultat d'idempotence et
l'audit de succès sont validés dans une seule transaction. Les tentatives,
refus et rejeux sont également audités sans contenu métier.

Pour couper immédiatement toute nouvelle action, remettre au moins un des deux
verrous dans son état sûr puis purger la configuration :

```dotenv
BACKOFFICE_READ_ONLY=true
BACKOFFICE_ACTIONS_ENABLED=false
```

Les commandes ci-dessous constituent un canal d'administration privilégié :
leur accès repose sur l'accès au conteneur et non sur une session opérateur.
Elles imposent un motif audité. Les exécuter depuis un terminal privé ; l'email
passé en argument peut rester dans l'historique du shell, même s'il n'est jamais
stocké dans le schéma Operations.

## Ouvrir un dossier support

Le demandeur doit être un utilisateur actif, à l'adresse vérifiée, membre actif
d'un Workspace actif. Le résumé est un code structuré de 64 caractères maximum,
pas une phrase ni une donnée personnelle.

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:support:open WORKSPACE_UUID participant@example.test Product P2 \
  product.navigation-blocked \
  --reason="Qualification support beta"
```

Les objectifs sont calculés en Europe/Paris, du lundi au vendredi entre 09:00
et 18:00 : P0 sous 4 heures ouvrées, P1 sous 1 jour ouvré, P2 sous 2 jours
ouvrés, P3 sous 3 jours ouvrés. Le calendrier ne déduit pas encore les jours
fériés français ; Support doit anticiper cette limite lors d'une semaine
concernée. Ces objectifs internes ne sont pas des SLA contractuels.

## Enregistrer une demande relative aux données

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:compliance:request WORKSPACE_UUID participant@example.test Access \
  data.subject-access \
  --reason="Qualification d'une demande vérifiée"
```

La commande crée une demande `DR-*` liée à un dossier support `SUP-*`. Accès,
effacement et portabilité exigent un Owner vérifié ; aucune action n'est lancée.
La cible interne vaut 5 jours ouvrés pour accès, rectification, effacement et
portabilité, 2 jours pour restriction et opposition. Le périmètre et les délais
légaux restent soumis à la notice validée.

## Export assisté à double contrôle

Le bouton « Demander l'export » n'est proposé que si la demande est de type
`Access` ou `Portability`, au statut `Qualified`, avec identité et ownership
vérifiés, et sans export existant.

Après prévisualisation et confirmation idempotente, l'export passe à
`AwaitingApproval`. L'identité Operator ayant fait la demande ne peut pas
approuver sa propre demande, même si son grant contient les deux permissions.
L'approbateur doit disposer d'un step-up courant et relire le périmètre. Sa
confirmation place `operations.data_export.generation_requested` dans l'Outbox :
la requête HTTP ne lit pas les données métier et ne fabrique aucun fichier.

Le worker compose ensuite `WorkspaceDataV1` à partir du profil de l'espace, des
membres, du CRM, des activités, devis, factures, règlements, avoirs et de l'état
d'abonnement sans référence fournisseur. Sont exclus les mots de passe,
sessions, jetons, clés d'idempotence, payloads Outbox, références Stripe, audit
Operator, binaires PDF et projections recalculables.

Le JSON est limité à `BACKOFFICE_EXPORT_MAX_BYTES` — 5 Mio par défaut —,
chiffré avec la clé applicative, associé à une empreinte SHA-256 et conservé en
base sans chemin public. Un dépassement déterministe place l'export en `Failed`.
Une fois `Ready`, un opérateur portant `operations.exports.download` peut le
télécharger pour sa remise. Le serveur déchiffre l'artefact seulement après
réautorisation, recalcule son empreinte et répond avec `Cache-Control: no-store`.

Le téléchargement est idempotent et audité. L'artefact expire après
`BACKOFFICE_EXPORT_TTL_HOURS`, soit 24 heures par défaut. Aucun lien public ou
email n'est créé. Le job quotidien `atlas:retention:purge` détruit le ciphertext
expiré et conserve seulement l'état et l'historique probatoire. L'opérateur doit donc vérifier le destinataire et utiliser le
canal de remise validé avant de cliquer ; `Delivered` matérialise cette remise
assistée dans Atlas, pas une preuve de lecture par le participant.

Deux comptes Operator distincts sont nécessaires pour la recette :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:grant demandeur@atlas.test \
  --permissions="operations.backoffice.access,operations.compliance.read,operations.exports.request" \
  --reason="Recette demande export"

docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:operator:grant approbateur@atlas.test \
  --permissions="operations.backoffice.access,operations.compliance.read,operations.exports.approve,operations.exports.download" \
  --reason="Recette approbation export"
```

En cas de blocage `Generating`, diagnostiquer l'Outbox et le worker sans recréer
la demande. Pour un artefact expiré ou `Failed`, ne pas contourner le workflow
par une lecture SQL : consigner l'incident et utiliser le canal d'administration
approuvé en attendant un parcours de régénération borné.

## Versionner les textes et leurs preuves

Le contenu juridique reste dans son support publié. Operations n'enregistre
que son empreinte SHA-256 et une version immuable :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:compliance:policy BetaTerms 2026-08-beta Published SHA256_HEXADECIMAL \
  --effective-at="2026-09-01T00:00:00+02:00" \
  --approval-ref="LEGAL:2026-08:beta-terms" \
  --reason="Publication de la version juridiquement approuvée"
```

Une version `Published` exige une référence d'approbation Legal structurée et
une date d'effet. La référence d'approbation est elle-même hachée. Les projets
actuels `beta-terms.fr.md` et `privacy-notice.fr.md` restent des brouillons : ils
ne doivent pas être enregistrés comme publiés avant résolution de leurs champs
bloquants et validation juridique.

Une preuve ne peut viser qu'une version publiée :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:compliance:policy-proof BetaTerms 2026-08-beta \
  participant@example.test Accepted PROOF:restricted-register:123 \
  --workspace-id=WORKSPACE_UUID \
  --reason="Rapprochement de la preuve d'acceptation"
```

La référence opaque pointe vers le registre restreint faisant autorité ; seule
son empreinte est conservée dans Atlas. Ni capture ni contenu nominatif ne doit
être placé dans le motif, les logs ou le dépôt.

## Consentement de recherche facultatif

Les finalités `Interview`, `Recording` et `PublicQuote` sont séparées. Un retrait
n'est accepté qu'après un consentement accordé pour la même finalité :

```bash
docker compose -f implementation/docker-compose.yml exec app php artisan \
  atlas:compliance:consent participant@example.test Recording Granted \
  CONSENT:restricted-register:456 \
  --workspace-id=WORKSPACE_UUID \
  --reason="Consentement facultatif recueilli"
```

Le refus ou le retrait ne modifie jamais l'accès au produit, l'abonnement ou la
participation à la beta. Le registre est append-only : un retrait ajoute un
événement et ne réécrit pas la preuve initiale.

## Conservation et limites

Les événements de support, versions, preuves et consentements sont append-only.
Aucune purge automatique n'est activée tant que les durées Support et Recherche
de la notice ne sont pas validées. Cette absence de purge est un blocage de
conformité à résoudre, pas une politique de conservation indéfinie.

Le registre ne remplace pas :

- le test de la boîte `beta@atlas-design.fr` et la désignation des responsables ;
- l'approbation juridique des textes et du registre des traitements ;
- la suppression Workspace atomique ;
- une authentification back-office externe résistante au phishing.

## Recette minimale

Pour remplir la cohorte beta et ces registres avec un scénario local complet et
rejouable :

```bash
make backoffice-seed
```

La commande crée huit dossiers Support, quatre demandes de données, trois
versions de textes, sept preuves et sept événements de consentement. Elle crée
au besoin les cinq comptes `BETA-001` à `BETA-005` et leurs Workspaces. Elle est
refusée hors des environnements `local` et `testing`.

```bash
./implementation/scripts/run-tests.sh \
  tests/Integration/Demo/SupportComplianceFixtureSeederTest.php \
  tests/Unit/Operations/OperationsOverviewQueryHandlerTest.php \
  tests/Integration/Operations/SupportComplianceTest.php \
  tests/Integration/Operations/SupportCaseManagementTest.php \
  tests/Integration/Operations/OperatorDataExportTest.php \
  tests/Integration/Operations/OperatorOverviewTest.php

make web-check
```

Vérifier que les permissions Support et Compliance sont indépendantes, qu'un
non-Owner ne peut préparer une demande Workspace sensible, qu'une preuve sur un
brouillon est refusée, que les registres probatoires refusent update/delete et
qu'aucune identité brute n'est renvoyée par l'API. Pour l'action bornée, vérifier
indépendamment les deux flags, la permission, le step-up, le conflit de révision,
le rejeu idempotent et la révocation concurrente.
Pour l'export, vérifier en plus l'interdiction d'auto-approbation, la génération
par le worker, le chiffrement au repos, l'absence de secrets et références
fournisseur, l'empreinte, l'expiration et le téléchargement `no-store`.
