# Boundaries

Chaque domaine est responsable de son modèle.

Un domaine ne peut pas :

- modifier directement les données d'un autre domaine ;
- contourner les règles métier d'un autre domaine ;
- accéder directement à la base de données d'un autre domaine.

Les échanges passent toujours par un contrat public explicite :

- un événement pour annoncer un fait déjà survenu ;
- une commande ou une API publique pour demander une action ;
- une interface de lecture publique pour consulter une information nécessaire.

Le choix du contrat dépend de l'intention de l'échange. Un événement ne doit
pas être utilisé comme une commande implicite et un domaine ne lit jamais le
stockage interne d'un autre domaine.

Les limites des domaines sont considérées comme immuables.
