---
id: ROADMAP-002
title: Slices d'implémentation MVP
status: In Review
owner: Product and Engineering
version: 1.0
last_updated: 2026-08-06
references:
  - mvp-scope.md
  - ../blueprint/decision-loop.md
  - ../blueprint/cold-start.md
---

# Slices d'implémentation MVP

## Règle de livraison

Une slice traverse stockage, commande/API publique, événement, projection,
autorisation, interface, observabilité et tests. Elle est livrable derrière un
feature flag, sans accès direct au stockage d'un autre domaine. Les noms exacts
restent ceux des catalogues de chaque bounded context.

## Ordre et slices verticales

| # | Domaine | Slice démontrable | Contrat de sortie | Preuve de terminé |
|---:|---|---|---|---|
| 1 | Identity | s'inscrire, vérifier l'email, ouvrir/révoquer une session | identité authentifiée et UserId | session expirée refusée, audit sans secret |
| 2 | Workspace | créer puis activer un Workspace avec son Owner | Workspace actif et contexte autorisé | saga rejouable, échec compensable |
| 3 | CRM | créer client/contact et faire progresser une opportunité | faits CRM publics versionnés | doublon RequestId neutre, historique visible |
| 4 | Billing | devis envoyé/accepté, facture issue, paiement enregistré | faits Billing publics versionnés | montants immuables après émission, PDF traçable |
| 5 | Analytics | ingérer les faits et publier le snapshot MVP | AnalyticsSnapshot exact, daté, couvert | rebuild égal, données absentes explicites |
| 6 | Business Health | évaluer le snapshot avec la politique active | assessment versionné et explicable | corpus de référence, ordre courant stable |
| 7 | Advisor | produire, expliquer et terminer une priorité | recommandation et signal versionnés | top 3 déterministe, action autorisée |
| 8 | Notifications | créer l'inbox et remettre un email consenti | notification et résultat de remise | déduplication, fenêtre, désinscription |

## Incréments par domaine

### Identity

**Entrée :** formulaire minimal et preuve d'email. **Chemin heureux :** User,
Credential, EmailVerification, Session. **Échecs :** email déjà lié, token expiré,
limite de tentative. **Tests :** transitions, concurrence, moindre privilège et
révocation immédiate.

### Workspace

**Entrée :** nom, pays, fuseau et identité de facturation minimale. **Chemin
heureux :** `Provisioning → Active` coordonné avec Identity. **Échecs :** owner
incomplet, reprise partielle, version concurrente. Le chemin manuel ne dépend pas
de l'import.

### CRM

**Entrée :** saisie ou lignes validées de l'import. **Chemin heureux :** Client,
Contact, Opportunity et Activity. **Échecs :** référence externe dupliquée,
transition interdite et correspondance ambiguë sans fusion automatique.

### Billing

**Entrée :** identité Workspace et client public. **Chemin heureux :** Quote,
acceptation publique, Invoice, Payment. **Échecs :** calcul monétaire invalide,
double acceptation, paiement inversé. Toute mutation passe par une commande du
catalogue Billing.

### Analytics

**Entrée :** événements publics CRM, Billing et Workspace. **Chemin heureux :**
ingestion idempotente puis snapshot au profil MVP. **Échecs :** contrat inconnu,
devise incompatible et retard ; chaque lacune affecte fraîcheur ou couverture.

### Business Health

**Entrée :** snapshot exact et `HealthPolicyVersion`. **Chemin heureux :** quatre
facteurs, score, fiabilité, risques et attention. **Échecs :** profil ou politique
incompatible et données insuffisantes, sans action Advisor inventée.

### Advisor

**Entrée :** assessment exact et `RecommendationPolicyVersion`. **Chemin heureux :**
règles déterministes, classement stable, explication et action principale.
**Échecs :** cible non autorisée, recommandation obsolète ou déjà terminale.

### Notifications

**Entrée :** signal Advisor public et préférences. **Chemin heureux :** plan,
inbox puis éventuelle remise email. **Échecs :** absence de consentement, fenêtre,
template incompatible ou fournisseur indisponible ; l'inbox reste utilisable.

## Gate d'intégration de chaque slice

- migration montante et rollback testés sur données représentatives ;
- API et événements validés par tests de contrat producteur/consommateur ;
- commandes idempotentes et concurrence optimiste couvertes ;
- permission positive et négative testée ;
- métriques, traces corrélées, journal d'audit et alertes opérationnelles ;
- états UI mobile, clavier, lecteur d'écran et erreurs documentés ;
- runbook de reprise et propriétaire explicites ;
- aucun élément exclu du MVP requis pour la démonstration.

## Démonstration de release

La release candidate rejoue dans un environnement vierge le parcours froid, le
cycle client jusqu'au paiement, la publication du snapshot, l'assessment, la
recommandation et l'inbox. Une panne simulée à chaque frontière doit reprendre
sans doublon visible ni perte de traçabilité.
