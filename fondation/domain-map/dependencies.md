# Dépendances

## Dépendances autorisées

CRM

↓

Billing

↓

Analytics

↓

Business Health

↓

Advisor

↓

Notifications

---

## Dépendances interdites

Notifications → CRM

Analytics → Billing

Business Health → CRM

---

## Advisor peut consommer :

- des événements ;
- des projections ;
- des read models ;
- des capacités publiques.

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
