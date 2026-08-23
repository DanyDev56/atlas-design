---
id: BPT-013
title: Operator Back-office Blueprint
status: In Review
owner: Product, Engineering and Security
version: 0.4.0
last_updated: 2026-08-24

references:
  - README.md
  - backoffice-metrics.md
  - backoffice-implementation-plan.md
  - permissions.md
  - roadmap.md
  - ../../fondation/decisions/ADR-004-operator-control-plane.md
  - ../../fondation/security/mvp-threat-model.md
  - ../../implementation/runbooks/beta-program.md
  - ../../implementation/runbooks/beta-support-offboarding.md
---

# Blueprint du back-office opérateur

## Statut et objectif

Ce document décrit la cible fonctionnelle du back-office Atlas. `ADR-004` est
accepté. Les incréments locaux livrés couvrent l'audience opérateur, les grants,
les sessions, la MFA TOTP transitoire, le step-up, l'audit, le dashboard
Outbox/Emails et la cohorte beta pseudonymisée en lecture seule. TOTP n'est pas
présenté comme résistant au phishing et l'accès externe reste fermé par défaut.
Les autres surfaces restent soumises à leurs gates respectives.

Le back-office donne aux personnes qui exploitent Atlas une vue cohérente de la
beta, du produit, des opérations, du support et de la conformité. Il n'est pas
une fonctionnalité client et n'étend pas les droits d'un Owner de Workspace.

## Principes non négociables

- `/backoffice` est une surface et une audience séparées de `/app` ;
- l'accès est refusé par défaut et provisionné hors inscription publique ;
- une Membership Workspace ne donne jamais accès au back-office ;
- les listes affichent le minimum nécessaire et masquent les données sensibles ;
- aucune impersonation en V1 ;
- aucune mutation directe des tables ou repositories privés des domaines ;
- une action privilégiée appelle le contrat public du domaine propriétaire ;
- recherche, révélation sensible, export et action sont audités ;
- la lecture seule précède les actions opérateur ;
- une valeur absente reste absente : le back-office n'invente ni zéro, ni score.

## Profils opérateur

Les profils sont des ensembles de permissions, pas des décisions codées depuis
leur libellé. Une personne reçoit uniquement les grants nécessaires à sa
mission et pour une durée réexaminée.

| Profil initial | Besoin | Données accessibles par défaut | Actions |
|---|---|---|---|
| `ProductAnalyst` | cohorte, activation, pricing et retours agrégés | pseudonymes et métriques agrégées | notes et jalons beta |
| `SupportAgent` | traiter les demandes et diagnostiquer un Workspace | identité minimale, état du compte et diagnostic borné | répondre, qualifier, demander un export |
| `Operator` | disponibilité, emails, outbox, sauvegardes et abonnements | signaux techniques et références opaques | relancer une opération sûre et idempotente |
| `SecurityReviewer` | accès, incidents, demandes de données et audit | journaux privilégiés et preuves minimisées | restreindre, approuver, clôturer une revue |
| `LegalReviewer` | consentements, textes, sous-traitants et demandes de droits | preuves de version et dossier conformité | valider un traitement administratif |

Il n'existe pas de profil `SuperAdmin` donnant silencieusement tous les droits.
Le mode break-glass est une procédure exceptionnelle, temporaire, motivée,
alertée et revue après usage.

## Catalogue initial des permissions

| Permission | Portée |
|---|---|
| `operations.backoffice.access` | ouvrir une session opérateur et le shell |
| `operations.dashboard.read` | lire les compteurs non sensibles globaux |
| `operations.beta.read` | lire cohorte et activation pseudonymisées |
| `operations.beta.manage` | future mutation web bornée des jalons ; non exposée tant que les actions restent désactivées |
| `operations.metrics.read-product` | lire les métriques produit agrégées |
| `operations.metrics.read-financial` | lire les agrégats financiers Atlas autorisés |
| `operations.workspaces.read-summary` | rechercher et lire un résumé Workspace |
| `operations.users.read-summary` | rechercher et lire un résumé de compte |
| `operations.sensitive-data.reveal` | révéler un champ masqué avec motif et audit |
| `operations.support.read` | lire les dossiers support autorisés |
| `operations.support.manage` | qualifier, assigner et clôturer un dossier |
| `operations.compliance.read` | lire consentements et demandes de droits |
| `operations.compliance.manage` | instruire une demande ou preuve de conformité |
| `operations.exports.request` | préparer une demande d'export |
| `operations.exports.approve` | approuver un export demandé par une autre personne |
| `operations.exports.download` | télécharger un paquet borné et expirant |
| `operations.workspace-closure.request` | demander une fermeture Workspace |
| `operations.workspace-closure.approve` | approuver la fermeture demandée par une autre personne |
| `operations.outbox.read` | lire backlog, retries et dead-letters |
| `operations.outbox.retry` | rejouer un message explicitement identifié |
| `operations.email.read` | lire états de livraison sans adresse brute |
| `operations.subscriptions.read` | lire essais, abonnements et synchronisation fournisseur |
| `operations.audit.read` | consulter l'audit privilégié |
| `operations.incidents.manage` | déclarer, qualifier et clôturer un incident |

Les permissions d'approbation et de demande ne sont pas détenues par la même
personne pour une action destructrice lorsque deux opérateurs sont disponibles.
Sans second approbateur, la fonction reste désactivée ou suit un break-glass
explicitement accepté ; l'interface ne simule jamais une séparation des devoirs.

## Architecture de navigation

```text
/backoffice/login

/backoffice
  Vue d'ensemble

  Beta
    Cohorte
    Activation
    Entretiens et pricing

  Produit
    Métriques
    Workspaces
    Utilisateurs
    Essais et abonnements

  Exploitation
    Santé des services
    Emails
    Outbox et dead-letters
    Scheduler et sauvegardes
    Incidents

  Support
    Dossiers
    Exports
    Fermetures

  Conformité
    Consentements
    Demandes relatives aux données
    Rétention et sous-traitants

  Sécurité
    Grants opérateur
    Sessions opérateur
    Audit privilégié
```

Le menu n'affiche que les destinations autorisées. Le serveur réautorise chaque
requête et chaque objet ; masquer un menu ne constitue jamais un contrôle.

## Écrans et capacités

### Vue d'ensemble

La page d'accueil répond en moins d'une minute à quatre questions :

1. la plateforme fonctionne-t-elle ?
2. un participant beta est-il bloqué ?
3. une action support ou conformité attend-elle une réponse ?
4. un signal financier ou de livraison exige-t-il une intervention ?

Elle affiche des tuiles sourcées et datées : état API/worker/scheduler, backlog
outbox, emails en échec, incidents ouverts, cohorte par étape, essais proches de
la fin, demandes support et demandes de données. Chaque tuile ouvre la liste
filtrée qui explique son total.

### Cohorte beta

La liste utilise `BETA-001` à `BETA-005`. Elle montre cellule de prix, dates,
étape `E0` à `E6`, dernier jalon, blocage, support cumulé et prochaine action.
Le nom, l'email et les UUID métier ne sont pas chargés dans cette vue. Une
future révélation Support ou Legal devra utiliser une permission, un motif et
un audit séparés ; elle n'est pas livrée dans cet incrément.

### Workspaces et utilisateurs

La recherche exige un critère précis et n'autorise pas le parcours libre de
toute la base. Le résumé expose état, date de création, Trial/Subscription,
dernière activité, couverture Analytics, nombre de membres, incidents et
dossiers support. Les objets CRM/Billing détaillés et PDF ne sont pas chargés
par défaut.

Le back-office ne permet ni de modifier un devis ou une facture, ni de changer
un mot de passe, ni d'accepter une invitation à la place d'un utilisateur.

### Exploitation

Les vues opérationnelles présentent santé des rôles, latence et erreurs HTTP,
jobs scheduler, sauvegardes, outbox, dead-letters, emails, webhooks et fraîcheur
des projections. Un état `Unknown` ou `NotCollected` reste distinct de `Healthy`
et de la valeur zéro.

### Support et conformité

Un dossier conserve demande, identité vérifiée, Workspace, gravité, owner,
dates, décisions et preuves minimisées. Les exports et fermetures sont des jobs
explicites avec prévisualisation, approbation, empreinte, expiration et résultat.
Les versions de conditions et de notice sont immuables après publication.

### Audit

L'audit répond à : qui, quand, depuis quelle session, quelle permission, quel
objet opaque, quelle intention, quel résultat et quel identifiant de
corrélation. Le motif est obligatoire pour une révélation sensible ou une
action. Les anciennes entrées ne sont ni corrigées ni supprimées depuis l'UI.

## Règles de données

| Classe | Affichage par défaut | Révélation |
|---|---|---|
| identifiant opaque, état, date, compteurs | clair | non nécessaire |
| nom et email utilisateur | masqué partiellement | Support/Legal, motif audité |
| données CRM et montants d'un Workspace | résumé/agrégat | permission ciblée, besoin explicite |
| document PDF ou contenu de message | absent | parcours dédié exceptionnel |
| jeton, mot de passe, secret, carte bancaire | jamais | impossible |
| logs et erreurs | expurgés | jamais de secret brut |

Les exports de listes opérateur sont désactivés par défaut. Un export métier
pour un participant suit le runbook de sortie, pas un bouton générique de grille.

## Actions opérateur

### Lecture seule initiale

- consulter dashboard, cohorte et métriques ;
- rechercher un Workspace ou utilisateur par critère exact ;
- consulter support, consentements, emails, outbox et audit ;
- ouvrir les fournisseurs externes par lien vers un identifiant opaque, sans
  incorporer leurs secrets.

### Actions bornées ultérieures

- assigner et clôturer un dossier support ;
- enregistrer un jalon beta ou une décision pricing ;
- révoquer une session opérateur compromise ;
- demander puis approuver export ou fermeture ;
- rejouer une dead-letter après prévalidation de l'idempotence ;
- appliquer une restriction de sécurité réversible ;
- déclencher une réconciliation fournisseur explicitement bornée.

Chaque action affiche avant confirmation : objet, portée, conséquences,
réversibilité, préconditions, permission et besoin d'approbation. Un retour
navigateur ou un double clic ne répète pas l'effet.

## États UX obligatoires

Chaque écran couvre : chargement, absence réelle, données insuffisantes,
permission absente, collecte indisponible, donnée périmée, erreur partielle,
succès temporaire et conflit concurrent. Une vue composite reste utilisable si
une source non critique est indisponible et indique précisément cette source.

## Exigences non fonctionnelles

- desktop prioritaire, mobile utilisable pour lecture et incident, mais aucune
  action destructrice optimisée pour un usage précipité sur petit écran ;
- navigation clavier, focus visible, libellés explicites et contrastes conformes
  aux mêmes exigences que l'application client ;
- pagination et filtres côté serveur ;
- aucune recherche `contains` globale sur des données personnelles ;
- fraîcheur et provenance visibles sur chaque métrique ;
- corrélation de bout en bout des actions ;
- timeout court des sessions opérateur et step-up récent pour actions sensibles ;
- feature flag serveur permettant de couper toutes les actions en conservant
  le diagnostic lecture seule.

## Hors périmètre V1

- impersonation ou prise de contrôle d'un compte ;
- édition générique des tables ;
- requête SQL ou console arbitraire dans le navigateur ;
- consultation par défaut du contenu client ;
- gestion d'une marketplace ou de plusieurs organisations opératrices ;
- décision automatisée de suspension, remboursement ou suppression ;
- BI marketing, session replay et enrichissement externe de profils.

## Critères d'acceptation documentaire

- `ADR-004` accepté par Product, Engineering et Security ;
- permissions et profils relus sans bypass global ;
- catalogue de métriques avec source, fraîcheur, absence et audience ;
- actions classées par risque, réversibilité, step-up et approbation ;
- modèle de menace mis à jour pour le plan de contrôle ;
- rétention de l'audit, du support et de la cohorte décidée ;
- parcours export/fermeture aligné avec les textes publiés ;
- plan d'implémentation séquencé avec gates exécutables.
