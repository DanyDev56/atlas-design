---
title: Support et sortie — Beta fermée
status: In Review
owner: Support + Operations
last_updated: 2026-08-23
references:
  - beta-program.md
  - beta-release-checklist.md
  - data-retention-beta.md
  - backup-restore.md
  - observability.md
  - outbox-incident.md
---

# Support et sortie de la beta

Ce runbook définit le service réellement soutenable pour cinq participants. Les
délais sont des objectifs internes de prise en compte, pas un SLA contractuel.

## Canal et responsabilités

| Élément | Décision candidate |
|---|---|
| Canal participant | email `beta@atlas-design.fr` *(à activer et tester)* |
| Horaires suivis | jours ouvrés, 09:00–18:00 Europe/Paris |
| Owner principal | à nommer |
| Relais | à nommer |
| Registre | outil restreint, jamais une issue publique ni le dépôt Git |
| Accusé de réception | automatique ou manuel, avec identifiant de demande |

La boîte n'est déclarée opérationnelle qu'après un test aller-retour depuis une
adresse externe et un test de relais vers l'owner secondaire.

## Qualification des demandes

| Niveau | Exemple | Prise en compte cible | Action initiale |
|---|---|---:|---|
| `P0` | suspicion de fuite, accès au mauvais Workspace, perte massive de données | 4 h ouvrées | contenir, préserver les preuves, prévenir Security |
| `P1` | connexion impossible, email indispensable absent, blocage d'un parcours critique | 1 jour ouvré | reproduire, proposer un contournement sûr |
| `P2` | défaut fonctionnel non bloquant, export ou correction de données | 2 jours ouvrés | qualifier et annoncer la prochaine étape |
| `P3` | question, suggestion, retour UX | 3 jours ouvrés | accuser réception et rattacher à la revue produit |

Le ticket conserve : identifiant pseudonyme, date, gravité, Workspace concerné,
résumé minimisé, owner, statut, prochaine action et date de clôture. Il ne
contient ni mot de passe, ni jeton, ni document client complet.

## Réponse standard

Chaque réponse indique :

1. ce qui a été compris ;
2. le niveau de priorité et la prochaine mise à jour ;
3. le contournement éventuel et ses limites ;
4. les données supplémentaires strictement nécessaires ;
5. la confirmation de résolution ou la raison de clôture.

Une capture est expurgée avant partage. L'accès opérateur aux données réelles
reste exceptionnel, borné, approuvé et tracé conformément au modèle de menace.

## Demandes relatives aux données

Le demandeur écrit depuis son adresse vérifiée. Pour une exportation ou une
fermeture de Workspace, le support vérifie aussi qu'il en est Owner. En cas de
doute, aucune donnée n'est transmise et la demande est escaladée.

| Demande | Traitement beta | Cible annoncée |
|---|---|---:|
| accès/copie | export assisté | 5 jours ouvrés |
| correction | correction dans le produit ou intervention tracée | 5 jours ouvrés |
| suppression | procédure de fermeture ci-dessous | confirmation sous 5 jours ouvrés |
| opposition/limitation | gel des traitements non indispensables et revue | 2 jours ouvrés |

Ces objectifs internes ne remplacent pas les délais légaux applicables, qui
doivent être confirmés dans la notice validée.

## Export assisté

Le libre-service n'étant pas implémenté, l'interface ne doit pas promettre un
bouton d'export. Sur demande vérifiée, Engineering produit un paquet isolé et
chiffré. Les données réutilisables sont remises dans des formats structurés et
lisibles par machine (`CSV` ou `JSON`) ; les documents originaux restent en
`PDF`. Le paquet contient, selon les données présentes :

- profil du Workspace, membres et préférences ;
- clients, contacts, opportunités et activités ;
- devis, factures, avoirs, paiements et documents PDF ;
- faits Analytics, évaluations Business Health et recommandations Advisor ;
- préférences et historique de notifications utiles au participant.

Les mots de passe, jetons, secrets, traces de sécurité, données d'autres
Workspaces et identifiants internes sans utilité sont exclus. Le paquet est
contrôlé sur des données fictives avant la beta, puis remis par un canal à durée
de vie bornée. Le mot de passe de déchiffrement emprunte un canal séparé.

Preuve à conserver : ticket, approbation de l'Owner, périmètre, empreinte du
paquet, date de remise, date d'expiration du lien et confirmation de suppression
de la copie de travail. Aucun paquet d'export n'est committé.

Le périmètre exact d'un droit à la portabilité est validé au cas par cas : il
ne se confond pas avec une copie exhaustive de toutes les données calculées par
Atlas. La [fiche CNIL sur la portabilité](https://www.cnil.fr/fr/respecter-les-droits-des-personnes/professionnels-comment-repondre-une-demande-de-droit-la-portabilite)
sert de référence à la procédure.

## Fermeture et suppression

1. confirmer l'identité, le Workspace, la portée et l'impact sur les membres ;
2. proposer l'export assisté et recueillir le choix explicite de l'Owner ;
3. fixer une date de fermeture et révoquer les sessions à cette date ;
4. empêcher toute nouvelle invitation, email métier et écriture sur le
   Workspace ;
5. exécuter la procédure de suppression validée sur la cible ;
6. vérifier l'absence du Workspace dans les surfaces applicatives et les jobs ;
7. supprimer les copies de travail et liens d'export ;
8. confirmer au demandeur la fermeture et la rétention résiduelle des backups ;
9. laisser expirer les sauvegardes selon la rotation documentée, sauf obligation
   légale ou preuve d'incident explicitement consignée.

## Limite technique actuelle

Atlas ne dispose pas encore d'une commande transactionnelle, testée et
réexécutable pour exporter ou supprimer tout un Workspace. Une intervention SQL
ad hoc ne constitue pas une procédure acceptable pour des données externes.

Avant le `Go` beta externe :

- [ ] produire un export sur un Workspace fictif et contrôler chaque fichier ;
- [ ] implémenter ou valider une suppression Workspace atomique et auditable ;
- [ ] tester la révocation des sessions et l'arrêt des emails après fermeture ;
- [ ] tester la restauration sans réactiver un Workspace supprimé par erreur ;
- [ ] faire approuver la procédure par Product et Security.

## Clôture de la beta

À la fin du programme, Product remet à Support la liste restreinte des cinq
participants et leur choix : poursuivre, exporter puis fermer, ou fermer sans
export. Support rapproche chaque participant d'un ticket de sortie et publie
uniquement une synthèse agrégée : comptes fermés, exports remis, demandes
ouvertes et date d'expiration de la dernière sauvegarde concernée.
