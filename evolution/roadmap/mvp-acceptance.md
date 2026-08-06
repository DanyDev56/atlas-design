---
id: ROADMAP-002
title: Atlas MVP End-to-End Acceptance
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - mvp-scope.md
  - ../blueprint/implementation-plan.md
  - ../blueprint/dashboard.md
  - ../../fondation/product/product-strategy.md
  - ../../fondation/product/personnas/persona-primary.md
  - ../../fondation/product/jobs-to-be-done/manage-business.md
  - ../../fondation/domain-map/dependencies.md
  - ../../fondation/domains/identity/api.md
  - ../../fondation/domains/workspace/workflows.md
  - ../../fondation/domains/crm/api.md
  - ../../fondation/domains/billing/api.md
  - ../../fondation/domains/analytics/api.md
  - ../../fondation/domains/business-health/api.md
  - ../../fondation/domains/advisor/api.md
  - ../../fondation/domains/notifications/api.md
  - ../../fondation/security/mvp-threat-model.md
---

# Acceptation de bout en bout du MVP

## Objectif

Ce document transforme le périmètre MVP en preuve livrable. Une fonctionnalité
isolée ou un bounded context correctement implémenté ne suffit pas : Atlas doit
démontrer les trois résultats utilisateur ci-dessous à travers ses contrats
publics réels.

Chaque parcours doit être validé avec :

- une exécution nominale depuis une base vide ;
- une répétition des demandes avec la même clé d'idempotence ;
- une reprise après interruption entre deux domaines ;
- un refus d'autorisation et un conflit de version ;
- une corrélation observable de l'intention jusqu'au résultat final ;
- aucune lecture directe du stockage d'un autre bounded context.

---

## MVP-J1 — De l'inscription au Workspace actif

### Résultat utilisateur

Un nouveau prestataire vérifie son identité, ouvre une session et obtient un
premier Workspace utilisable dont il est l'owner actif.

### Trace contractuelle

| Étape | Propriétaire | Intention ou lecture | Résultat attendu |
|---|---|---|---|
| 1 | Identity | `registerUser` / `CreateUser` | `UserCreated` dans l'état `PendingVerification`. |
| 2 | Identity | remise spécialisée de la preuve de vérification | Aucun secret dans un Domain Event ou une lecture ordinaire. |
| 3 | Identity | `VerifyUserEmail` | `UserEmailVerified`, puis `UserActivated`. |
| 4 | Identity | authentification, puis `CreateSession` | `SessionCreated` et credential remis une seule fois par l'adaptateur spécialisé. |
| 5 | Orchestrateur de bootstrap | demande de premier Workspace avec une clé stable | Le retry retrouve le même workflow et le même `WorkspaceId`. |
| 6 | Workspace | `CreateWorkspace` | `WorkspaceCreated` dans l'état `Provisioning`. |
| 7 | Identity | `CreateRole`, `GrantPermissionToRole`, puis `CreateMembership` | Rôles initiaux créés, socles owner réconciliés, `MembershipCreated`. |
| 8 | Identity | `getWorkspaceOwnerReadiness` | Preuve minimale, récente et sans identifiants internes. |
| 9 | Workspace | `ActivateWorkspace` avec `workspace.lifecycle.activate` | `WorkspaceActivated` et `WorkspaceAccessStateChanged(Active)`. |
| 10 | Application | lectures publiques Identity et Workspace | Le Workspace devient sélectionnable et les commandes ordinaires sont autorisables. |

Le bootstrap est une saga. Aucune transaction distribuée n'est attendue. Les
clés d'idempotence de chaque étape sont dérivées de l'identifiant du workflow
et du `WorkspaceId`.

### Critères d'acceptation

- avant `WorkspaceActivated`, le Workspace affiche un état de provisioning et
  refuse les commandes métier ordinaires ;
- l'owner possède le socle Identity obligatoire et les permissions owner
  obligatoires déclarées par chaque domaine MVP ;
- l'absence d'owner actif bloque l'activation sans rendre le Workspace
  partiellement utilisable ;
- une reprise après chaque étape converge vers un seul User, une seule Session,
  un seul Workspace et un seul Membership owner actifs ;
- une preuve expirée, un token rejoué ou un email non vérifié ne permet jamais
  l'activation implicite du User ;
- aucune adresse, credential ou topologie de permissions sensible n'apparaît
  dans les événements d'intégration.

---

## MVP-J2 — Du client au paiement

### Résultat utilisateur

Un prestataire enregistre un client, qualifie une opportunité, fait accepter un
devis, émet la facture correspondante et enregistre son règlement.

### Trace contractuelle

| Étape | Propriétaire | Autorité | Événement ou résultat attendu |
|---|---|---|---|
| 1 | CRM — `CreateClient` | `crm.clients.create` | `ClientCreated`. |
| 2 | CRM — `AddContact` si nécessaire | `crm.contacts.create` | `ContactAdded` et, le cas échéant, `ClientPrimaryContactChanged`. |
| 3 | CRM — `CreateOpportunity` | `crm.opportunities.create` | `OpportunityCreated`. |
| 4 | CRM — `QualifyOpportunity` | `crm.opportunities.qualify` | `OpportunityQualified`. |
| 5 | Billing — `CreateQuote` | `billing.quotes.create` | Snapshots des contextes CRM/Workspace, puis `QuoteCreated`. |
| 6 | Billing — `UpdateQuoteDraft`, puis `SendQuote` | `billing.quotes.update-draft`, `billing.quotes.send` | `QuoteDraftUpdated`, `QuoteSendRequested`, puis `QuoteSent` après confirmation système. |
| 7 | Billing — `AcceptQuote` avec preuve publique bornée | autorité intrinsèque de la preuve | `QuoteAccepted`, une seule fois pour la révision attendue. |
| 8 | CRM — `WinOpportunity` depuis le devis | `crm.opportunities.win-from-quote` | `OpportunityWon`, corrélé au `QuoteId`. |
| 9 | Billing — `CreateDepositInvoiceFromQuote` ou `CreateFinalInvoiceFromQuote` | `billing.invoices.create` | `InvoiceCreated` à partir du snapshot accepté. |
| 10 | Billing — `IssueInvoice`, puis `SendInvoice` | `billing.invoices.issue`, `billing.invoices.send` | `InvoiceIssued`, `InvoiceDeliveryRequested`, puis `InvoiceSent`. |
| 11 | Billing — `RecordPayment` | `billing.payments.record` | `PaymentRecorded`, `PaymentAppliedToInvoice`, `InvoiceBalanceChanged`. |
| 12 | Billing | solde ramené exactement à zéro | `InvoiceSettled` et `InvoicePaid`. |

Les communications de devis, factures et relances restent la responsabilité de
Billing. Elles ne passent pas par Notifications.

### Critères d'acceptation

- Billing crée des snapshots versionnés des données CRM et Workspace et ne
  modifie jamais leurs sources ;
- la consultation et l'acceptation publiques utilisent des preuves opaques,
  bornées au document, expirables et non substituables à une Session ;
- deux acceptations concurrentes ne gagnent pas deux fois l'Opportunity et ne
  créent pas deux factures pour la même intention ;
- un paiement partiel est un résultat valide et laisse un solde exact sans
  publier `InvoicePaid` ;
- un paiement erroné est inversé par une intention explicite, jamais édité ou
  supprimé ;
- une erreur de rendu ou de livraison est reprenable sans réémettre le document
  ni réutiliser un numéro financier ;
- chaque mutation applique la permission exacte de son domaine et la version
  attendue de l'agrégat.

---

## MVP-J3 — Des faits à une priorité notifiée

### Résultat utilisateur

Les faits du cycle commercial deviennent une lecture explicable de la santé de
l'activité, puis au plus trois recommandations ordonnées et, si la politique le
décide, une notification personnelle.

### Trace contractuelle

| Étape | Propriétaire | Contrat | Résultat attendu |
|---|---|---|---|
| 1 | Analytics | `IngestSourceFact` sur les événements CRM/Billing supportés | Relecture de la révision source exacte, puis `AnalyticsFactRecorded`. |
| 2 | Analytics | projections et métriques versionnées | Fraîcheur, complétude et `NoData` conservés ; aucun manque n'est transformé en zéro. |
| 3 | Analytics | `PublishAnalyticsSnapshot` | `AnalyticsSnapshotPublished` pour un profil cohérent. |
| 4 | Business Health | `EvaluateBusinessHealth` | Relecture du snapshot exact, puis `BusinessHealthAssessed`. |
| 5 | Advisor | `EvaluateRecommendations` | Relecture de l'évaluation exacte, puis `RecommendationEvaluationCompleted` et événements de cycle utiles. |
| 6 | Notifications | `ProcessAdvisorNotificationSignal` | Relecture de l'AdvisorOverview stabilisé, puis `NotificationPlanCompleted` et, si éligible, `NotificationCreated`. |
| 7 | Application | `getAdvisorOverview`, `getCurrentBusinessHealth`, `listNotifications` | Priorité, preuve, état de fraîcheur et inbox cohérents. |
| 8 | Utilisateur | décision Advisor ou état lu/non lu | L'action navigue vers le domaine propriétaire ; Advisor et Notifications ne l'exécutent pas. |

### Critères d'acceptation

- un fait dupliqué ne produit ni double observation ni double notification ;
- un fait tardif est intégré selon la politique de période et peut produire un
  nouveau snapshot, sans réécrire l'historique publié ;
- un snapshot incomplet peut conduire à `InsufficientData` ; ce résultat reste
  consultable et expliqué ;
- zéro recommandation est un résultat nominal de l'évaluation Advisor ;
- Notifications ne réagit qu'à `RecommendationEvaluationCompleted` pour créer
  un plan, jamais directement à `RecommendationGenerated` ;
- le canal InApp est actif par défaut ; l'email exige un consentement explicite
  et une priorité `High` ou `Critical` selon la politique Notifications ;
- l'audience et le contexte Workspace sont revalidés avant tout effet externe ;
- le Dashboard montre la fraîcheur propre à chaque vue et reste utilisable si
  un read model secondaire est temporairement indisponible.

---

## Scénarios transverses obligatoires

| Scénario | Attente |
|---|---|
| Isolation | Un identifiant d'un autre Workspace ne révèle ni présence ni contenu. |
| Autorisation | Toute commande et lecture utilise la capacité canonique du domaine propriétaire ; le refus est par défaut. |
| Idempotence | Une même intention rejouée converge vers le résultat initial et ne republie aucun effet métier. |
| Concurrence | Une révision obsolète produit un conflit stable et ne commit aucune mutation partielle. |
| Reprise asynchrone | Un consumer reprend depuis son checkpoint et déduplique par événement source. |
| Traçabilité | `CorrelationId`, `CausationId`, `EventId`, acteur, versions et résultat sont recherchables sans secret. |
| Données absentes | `NoData`, `InsufficientData` et zéro Recommendation sont distincts d'une panne. |
| Effet externe | Rendu et livraison possèdent une clé fournisseur, un statut prouvé et une politique de retry bornée. |
| Reconstruction | Analytics, Business Health, Advisor et Notifications peuvent reconstruire leurs projections depuis des sources immuables et versionnées. |
| Accessibilité | Les parcours principaux sont utilisables au clavier, avec libellés, focus, erreurs et statuts non dépendants de la couleur. |
| Sécurité | Les menaces `SEC-T01` à `SEC-T28` sont tracées vers leurs contrôles et les tests applicables à l'incrément passent. |

---

## Definition of Done du MVP

Le MVP est prêt pour une publication contrôlée lorsque :

1. les trois parcours passent en environnement proche de la production ;
2. leurs contrats, schémas, permissions et erreurs sont testés aux frontières ;
3. les scénarios de retry, conflit, indisponibilité et reconstruction sont
   démontrés ;
4. les journaux et métriques permettent de localiser une rupture de chaîne ;
5. les données de démonstration de référence produisent des résultats
   déterministes ;
6. aucune fonctionnalité exclue n'est nécessaire pour atteindre le résultat ;
7. Dashboard, Analytics et adaptateurs techniques respectent les frontières
   établies dans le Blueprint ;
8. le modèle `SEC-001` est validé, les gaps de la release sont fermés et aucun
   risque résiduel `High` ou `Critical` ne reste sans acceptation formelle.
