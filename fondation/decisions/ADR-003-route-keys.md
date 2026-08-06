---
id: ADR-003
title: Navigation découplée par RouteKeys stables
status: Accepted
date: 2026-08-06
owner: Product and Frontend
---

# Contexte

Notifications, deep links et écrans adaptatifs doivent cibler une intention sans
coupler les domaines à une URL ou un framework frontend.

# Options étudiées

1. stocker des URL complètes dans les événements ;
2. laisser chaque producteur construire une route ;
3. utiliser une RouteKey stable résolue par le shell applicatif.

# Décision

L'option 3 est retenue. Seules les clés du registre peuvent franchir une
frontière. Le shell résout la clé, contrôle l'autorisation et applique le fallback.
Les paramètres sont des identifiants opaques listés ; aucune donnée personnelle,
aucun libellé et aucune URL externe ne sont transportés.

# Raisons

La route visuelle peut évoluer sans republier l'historique métier. L'autorisation
reste au point d'entrée et le mobile partage les mêmes intentions que le desktop.

# Conséquences

Retirer ou renommer une clé requiert alias, télémétrie et dépréciation. Une cible
inaccessible retombe sur sa racine avec explication.

# Alternatives futures

Les liens universels natifs pourront résoudre les mêmes clés, sans changer les
contrats producteurs.
