# Dépendances

Une flèche `A → B` signifie que B peut consommer un contrat public de A. Elle
n'autorise jamais B à lire le stockage de A.

## Contextes de fondation

```text
Identity  <---- partenariat versionné ---->  Workspace
    |                                         |
    +-------------------+---------------------+
                        |
                        v
             Contextes métier Atlas
```

- les contextes métier consomment Identity pour l'authentification et
  l'autorisation ;
- ils consomment Workspace pour l'isolation, l'état d'accès et les données de
  profil strictement nécessaires ;
- Identity consomme `getWorkspaceAccessContext` ;
- le workflow Workspace consomme `getWorkspaceOwnerReadiness` ;
- ce partenariat reste limité à ces contrats et ne crée aucune propriété
  partagée.

---

## Chaîne métier autorisée

```text
CRM -> Billing -> Analytics -> Business Health -> Advisor -> Notifications
```

Cette chaîne exprime un flux principal, pas l'obligation pour un domaine de
consommer tous les domaines précédents.

- Analytics consomme les événements et faits versionnés de CRM et Billing ;
- Business Health consomme `AnalyticsSnapshotPublished` puis le snapshot exact ;
- Advisor consomme `BusinessHealthAssessed` puis l'évaluation exacte sans
  redéfinir les métriques ou la HealthPolicy ;
- Advisor fournit des actions de navigation allowlistées, mais CRM et Billing
  réautorisent toujours l'utilisateur et traitent leurs propres commandes ;
- Notifications consomme `RecommendationEvaluationCompleted` afin de considérer
  une priorité déjà stabilisée ;
- Notifications relit l'AdvisorOverview exact, résout son audience auprès
  d'Identity et vérifie état d'accès et locale auprès de Workspace ;
- Identity ne lui fournit qu'une `DeliveryEndpointReference` opaque et
  revalide le destinataire avant un dispatch ;
- aucun calcul en aval n'autorise une mutation du domaine source.

---

## Dépendances interdites

- Notifications modifiant CRM ;
- Notifications lisant directement CRM, Billing, Analytics ou Business Health ;
- Notifications transportant les tokens Identity ou les documents Billing ;
- Analytics modifiant Billing ;
- Business Health modifiant CRM ;
- Business Health créant ou modifiant une Recommendation ;
- Advisor lisant directement Analytics, CRM ou Billing ;
- Advisor exécutant une RecommendationAction dans un domaine source ;
- Workspace modifiant un Membership ou un Role ;
- Identity modifiant le profil ou le cycle de vie d'un Workspace ;
- Billing réécrivant une identité de facturation Workspace ;
- tout contexte accédant au stockage privé d'un autre.

---

## Advisor peut consommer :

- `BusinessHealthAssessed` ;
- `getBusinessHealthAssessment` ;
- `getLatestBusinessHealthAssessment` ;
- `getWorkspaceAccessContext` ;
- des décisions d'autorisation Identity.

## Advisor ne peut pas :

- modifier une facture ;
- enregistrer un paiement ;
- changer le statut d’un devis ;
- contourner les commandes publiques du domaine Billing.

---

Les domaines communiquent uniquement via leurs contrats publics.

- les faits sont diffusés par des événements ;
- les intentions de modification passent par des commandes ou API publiques ;
- les besoins de lecture passent par des projections, read models ou interfaces
  de lecture publiques.

Aucun domaine n'accède directement au stockage ou au modèle interne d'un autre
domaine.
