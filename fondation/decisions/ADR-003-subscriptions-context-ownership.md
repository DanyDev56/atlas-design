---
id: ADR-003
title: Subscriptions Context Ownership
status: Accepted
date: 2026-08-23
owner: Product and Engineering
version: 1.0.0
last_updated: 2026-08-23

references:
  - README.md
  - ADR-001-mvp-application-topology.md
  - ADR-002-mvp-implementation-stack.md
  - ../product/pricing-strategy.md
  - ../../evolution/governance/pricing-validation.md
  - ../domains/workspace/scope.md
---

# ADR-003 — Propriété du contexte Subscriptions

## Contexte

La stratégie tarifaire introduit un catalogue Atlas, un essai commercial, un
abonnement récurrent, un compte payeur et des droits d'accès calculés. Aucun des
huit contextes du MVP ne peut posséder ces concepts sans brouiller sa frontière :

- `Billing` gère les devis, factures, avoirs et paiements des clients du
  Workspace ;
- `Workspace` porte l'activité, son profil et son cycle de vie ;
- `Identity` porte les utilisateurs, Memberships, rôles et sessions.

Le prestataire de paiement possède l'exécution du prélèvement et les données de
carte, mais il ne peut pas devenir la source implicite de la politique d'accès
d'Atlas. Le produit doit également pouvoir tester le catalogue et l'essai avant
de choisir définitivement ce prestataire.

## Forces de décision

1. ne jamais mélanger la facturation Atlas avec celle des clients du Workspace ;
2. calculer les droits d'accès localement, sans appel réseau par requête ;
3. préserver l'isolation Workspace et l'idempotence des effets externes ;
4. pouvoir remplacer le prestataire de paiement ;
5. versionner les prix et conserver la décision commerciale historique ;
6. livrer l'incrément initial sans activer de blocage ni de prélèvement réel.

## Options étudiées

### Option A — Nouveau bounded context `Subscriptions`

Un module autonome possède le catalogue, les prix, Trials, Subscriptions,
Billing Accounts, Entitlements et références du prestataire. Les autres modules
ne consomment qu'un contrat public de décision d'accès.

### Option B — Étendre `Billing`

Cette option réutilise le vocabulaire de facture et paiement, mais mélange deux
relations économiques opposées : l'utilisateur facture son client tandis
qu'Atlas facture l'utilisateur. Elle crée des ambiguïtés de données, de taxes,
d'autorisations et de support.

### Option C — Étendre `Workspace`

Cette option place l'accès près de l'activité, mais transforme Workspace en
contexte de paiement et l'oblige à connaître le catalogue et le prestataire.
Elle empêche de distinguer clairement cycle de vie métier et droit commercial.

### Option D — Utiliser directement le modèle du prestataire

Cette option réduit le code initial, mais lie les routes, états et règles
d'accès aux statuts externes. Elle rend les tests locaux, la migration de
prestataire et la gestion des événements désordonnés plus fragiles.

## Décision

Atlas retient **l'option A** et ajoute un bounded context `Subscriptions` au
modular monolith.

`Subscriptions` possède :

- `Plan` et `PlanPrice`, immuables et versionnés ;
- `Trial` ;
- `Subscription` et son cycle de vie commercial ;
- `BillingAccount` d'Atlas ;
- `Entitlement`, calculé à l'échelle d'un Workspace ;
- les références techniques du prestataire ;
- l'inbox des webhooks et l'idempotence des commandes commerciales.

Le contexte ne possède ni Client, Quote, Invoice, CreditNote ou Payment du
module `Billing`, ni User, Membership ou Workspace. Il conserve leurs
identifiants opaques et consomme leurs contrats publics ou événements.

Le prestataire de paiement est encapsulé derrière un port applicatif. Il est
source de vérité pour l'exécution financière externe ; Atlas reste source de
vérité pour le sens du catalogue, l'état commercial normalisé et les droits
d'accès. Un retour navigateur ne confirme jamais un paiement : seul un webhook
authentifié ou une réconciliation produit la transition interne.

Le premier incrément utilise un adapter factice et maintient l'enforcement
désactivé. Il peut créer un Trial et calculer des Entitlements, mais ne réalise
aucun prélèvement.

## Raisons

- la frontière empêche une facture d'abonnement Atlas d'être confondue avec une
  facture émise par l'utilisateur ;
- un Entitlement local offre une décision rapide, observable et testable ;
- le catalogue interne conserve les montants réellement consentis même si le
  catalogue externe change ;
- l'outbox existante permet de démarrer un Trial après `WorkspaceActivated`
  sans transaction entre contextes ;
- un adapter factice permet de construire et tester les parcours avant le choix
  irréversible d'un fournisseur.

## Conséquences

### Positives

- séparation explicite entre activité du client et revenu SaaS d'Atlas ;
- tests locaux déterministes ;
- changements de prix et cohortes auditables ;
- absence d'appel au prestataire sur le chemin critique des commandes métier ;
- migration de prestataire possible sans modifier le domaine.

### Négatives

- neuvième schéma et nouveau contrat transversal à exploiter ;
- projection d'Entitlements à maintenir cohérente avec Trial et Subscription ;
- gestion obligatoire des doublons, retards et désordres de webhooks ;
- décisions encore nécessaires sur le prestataire, la fiscalité, la grâce et
  la rétention.

## Conditions d'implémentation

- namespace PHP et schéma PostgreSQL `subscriptions` possédés par le module ;
- aucune dépendance du domaine vers Laravel ou un autre module ;
- prix stockés en unités mineures entières et devise ISO 4217 ;
- contrainte d'un Trial initial par Workspace ;
- démarrage idempotent depuis `workspace.workspace_activated` ;
- contrat public de lecture d'Entitlement, sans repository exposé ;
- feature flags séparés pour checkout et enforcement ;
- signature, inbox, rejeu et réconciliation obligatoires avant un adapter réel ;
- tests d'architecture et d'isolation Workspace dans la CI.

## Réexamen

Cette décision doit être réexaminée si :

- Atlas adopte une marketplace ou plusieurs produits facturés séparément ;
- le prix dépend d'une consommation temps réel à fort volume ;
- les obligations fiscales imposent un contexte financier plus large ;
- un prestataire devient contractuellement impossible à encapsuler ;
- `Subscriptions` doit être extrait du modular monolith pour une raison
  opérationnelle mesurée.
