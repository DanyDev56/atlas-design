# Boundaries

Chaque domaine est responsable de son modèle.

Un domaine ne peut pas :

- modifier directement les données d'un autre domaine ;
- contourner les règles métier d'un autre domaine ;
- accéder directement à la base de données d'un autre domaine.

Les échanges passent toujours par :

- des événements ;
- des commandes ;
- des interfaces publiques.

Les limites des domaines sont considérées comme immuables.