---
title: Domain Driven Design
status: Draft
owner: Product
last_updated: 2026-08-05
---

# Les domaines métier

Atlas est organisé autour de domaines métier.

Chaque domaine possède :

- son vocabulaire ;
- ses objets ;
- ses règles ;
- ses événements ;
- ses permissions ;
- ses invariants ;
- ses API ;
- ses cas limites.

Cette séparation permet de maintenir un modèle cohérent au fil des années.

Un domaine ne doit jamais dépendre des détails d'implémentation d'un autre domaine.

Les interactions passent toujours par des contrats publics clairement
identifiés : événements, commandes ou interfaces de lecture.

---

## Liste actuelle

- Identity
- Workspace
- Billing
- CRM
- Advisor
- Business Health
- Automation
- Notifications
- Analytics
