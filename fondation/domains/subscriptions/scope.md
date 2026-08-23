---
id: SUB-SCOPE-001
title: Subscriptions Scope
status: Draft
owner: Product and Engineering
version: 0.1.0
last_updated: 2026-08-23

references:
  - README.md
  - ../../decisions/ADR-003-subscriptions-context-ownership.md
  - ../../product/pricing-strategy.md
  - ../../../evolution/governance/pricing-validation.md
---

# Périmètre Subscriptions

## Responsabilité

Le contexte décide quel catalogue Atlas est proposé à un Workspace, quel état
commercial normalisé s'applique et quelles capacités en résultent.

## Possède

- Plan et PlanPrice versionnés ;
- Trial ;
- Subscription ;
- BillingAccount Atlas ;
- Entitlement calculé ;
- références du prestataire et événements externes dédupliqués ;
- idempotence des commandes commerciales.

## Ne possède pas

- User, Membership, Role ou Session ;
- Workspace, son profil ou son identité courante ;
- Client, Quote, Invoice, CreditNote ou Payment du domaine Billing ;
- données de carte ;
- règles fiscales non validées par Legal et Finance.

## Contrats initiaux

- consomme `workspace.workspace_activated` pour démarrer un Trial idempotent ;
- expose la lecture du catalogue candidat et de l'état commercial du Workspace ;
- expose `WorkspaceEntitlementReader` pour une décision locale par capacité ;
- émet `subscriptions.trial_started` ;
- encapsule le checkout derrière `RecurringBillingGateway` ;
- normalise les événements récurrents activé, renouvelé, paiement échoué et
  résilié après vérification de leur signature.

## Invariants initiaux

- un seul Trial initial par Workspace ;
- un prix est identifié par Plan, version, période et devise ;
- tous les montants utilisent l'unité mineure entière ;
- le catalogue candidat n'est pas public ;
- un Trial expiré conserve uniquement lecture, export et gestion d'abonnement ;
- checkout et enforcement sont désactivés par défaut ;
- l'enforcement ne peut être activé sans durée de grâce `PastDue` explicite ;
- RBAC décide qui peut agir, Entitlement décide si le Workspace dispose encore
  de la capacité ; aucune décision commerciale ne remplace une autorisation ;
- les invitations en attente réservent une place et le plafond membre est
  vérifié atomiquement par Identity à partir du contrat d'Entitlement ;
- un retour de checkout ne prouve jamais un paiement ;
- seul un événement signé du prestataire peut modifier une Subscription ;
- un événement externe est traité au plus une fois par couple prestataire/id ;
- un événement plus ancien que le dernier événement appliqué ne régresse jamais
  l'état courant ;
- aucun stockage privé d'un autre contexte n'est lu par le domaine.

## État d'implémentation

| Capacité | État |
|---|---|
| `Atlas Solo@1`, 24 €/mois et 240 €/an | Candidat interne persisté |
| Trial de 30 jours sur activation | Implémenté via outbox |
| Entitlements Full/Restricted | Implémentés et gardes serveur branchées ; enforcement désactivé |
| Accès expiré | Lectures, export et gestion d'abonnement conservés ; mutations gardées |
| Grâce `PastDue` | Politique configurable et fail-closed ; durée commerciale non décidée |
| Limite de membres | Owner, membres actifs et invitations valides contrôlés atomiquement quand l'enforcement est actif |
| Lecture API propriétaire | Implémentée |
| UI propriétaire essai, offre et prix candidat | Implémentée dans Gérer l'espace |
| Gateway factice | Implémenté derrière feature flag, simulation sans paiement |
| Cycle Subscription normalisé | Implémenté pour activation, renouvellement, échec et résiliation |
| Inbox webhook factice signée | Implémentée, dédupliquée, ordonnée et rejouable ; désactivée par défaut |
| Reprise des Workspaces actifs antérieurs | Commande idempotente avec `--dry-run` |
| Prestataire réel et encaissement récurrent | Non implémentés |
| Restauration et resouscription | Implémentées avec historique des références prestataire |
| BillingAccount, portail, résiliation utilisateur et dunning | Non implémentés |

L'existence technique du candidat ne valide ni son prix ni son ouverture
commerciale.
