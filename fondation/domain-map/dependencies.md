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

Les domaines communiquent uniquement via des événements.