---
id: ADR-001
title: Intégration inter-domaines par contrats publics et événements
status: Accepted
date: 2026-08-06
owner: Architecture
---

# Contexte

Atlas doit livrer une boucle cohérente sans diluer la responsabilité des bounded
contexts ni créer de transaction distribuée.

# Options étudiées

1. base relationnelle partagée et jointures transverses ;
2. appels synchrones en chaîne ;
3. stockage privé par domaine, API publiques et événements versionnés.

# Décision

L'option 3 est retenue. Une commande ne modifie que son domaine. Les effets
transverses sont orchestrés par une saga explicite ou réagissent à un événement
public via inbox/outbox. La remise est au moins une fois ; consommateurs et
commandes sont idempotents. `EventId`, `CausationId`, `CorrelationId`, version de
schéma, instant et WorkspaceId forment l'enveloppe minimale.

# Raisons

Cette option respecte la Constitution, rend les reprises observables et permet
aux domaines d'évoluer sans partager leur modèle interne.

# Conséquences

Les projections sont éventuellement cohérentes et l'UI doit montrer les états en
cours. Des tests de contrat et une politique de compatibilité sont obligatoires.
Aucun rapport ne peut contourner un contrat par lecture directe.

# Alternatives futures

Un appel synchrone reste possible pour une lecture publique sans transfert de
responsabilité. Toute exception structurelle exige un nouvel ADR.
