---
id: CONSTITUTION-001
title: Atlas Constitution
status: Stable
owner: Product
version: 1.0
last_updated: 2026-07-30
references:
  - vision/mission.md
  - vision/vision.md
  - vision/principles.md
  - vision/anti-goals.md
---

# Constitution d’Atlas

## Préambule

Cette Constitution définit les règles fondamentales d’Atlas.

Elle guide les décisions relatives :

- au produit ;
- au design ;
- au métier ;
- à l’architecture ;
- à l’ingénierie ;
- à l’intelligence artificielle ;
- aux données ;
- à la sécurité ;
- au marketing ;
- au support.

En cas de contradiction, la Constitution prime sur les autres documents du référentiel.

Une décision incompatible avec cette Constitution doit être :

- rejetée ;
- modifiée ;
- ou accompagnée d’une révision formelle de la Constitution.

---

# Article 1 — Atlas aide à décider

Atlas ne se limite pas à gérer ou stocker des données.

Sa finalité est d’aider l’utilisateur à prendre de meilleures décisions pour son activité.

Chaque fonctionnalité significative doit contribuer à au moins un de ces objectifs :

- comprendre ;
- décider ;
- agir ;
- mesurer.

---

# Article 2 — L’utilisateur garde le contrôle

Atlas ne prend pas une décision importante à la place de l’utilisateur sans son accord explicite.

L’utilisateur doit pouvoir comprendre :

- ce qu’Atlas propose ;
- ce qu’Atlas prépare ;
- ce qu’Atlas exécute ;
- ce qu’Atlas a déjà exécuté.

Les automatisations doivent rester configurables et désactivables.

---

# Article 3 — Atlas est AI-first, jamais AI-only

L’intelligence artificielle peut améliorer :

- la compréhension ;
- la classification ;
- la prédiction ;
- la personnalisation ;
- la rédaction ;
- l’automatisation.

Toutefois, les fonctions essentielles doivent reposer sur des données et des règles métier fiables.

Une indisponibilité de l’intelligence artificielle ne doit pas rendre le cœur du produit inutilisable.

---

# Article 4 — Une décision automatisée doit être explicable

Toute recommandation ou action automatisée importante doit pouvoir exposer :

- son origine ;
- les données utilisées ;
- les règles ou facteurs déterminants ;
- son niveau de confiance ;
- son résultat attendu.

Atlas ne doit pas fonctionner comme une boîte noire lorsqu’une décision affecte l’activité de l’utilisateur.

---

# Article 5 — Une donnée est saisie une seule fois

Atlas doit réutiliser les informations déjà disponibles.

Une nouvelle saisie n’est acceptable que lorsque :

- l’information n’existe pas ;
- l’information existante doit être confirmée ;
- une obligation métier ou réglementaire l’exige.

La duplication inutile constitue un défaut de conception.

---

# Article 6 — Chaque donnée demandée doit produire de la valeur

Une information collectée doit avoir un usage clair.

Elle doit permettre au moins l’une des actions suivantes :

- éviter une future saisie ;
- améliorer une analyse ;
- personnaliser une recommandation ;
- préremplir un document ;
- déclencher une action utile ;
- améliorer la fiabilité du produit.

Atlas ne collecte pas une donnée sans finalité définie.

---

# Article 7 — Les événements racontent l’histoire

Chaque action métier importante doit laisser une trace durable.

Les événements servent à :

- reconstituer l’historique ;
- expliquer l’état actuel ;
- déclencher des traitements ;
- alimenter la Timeline ;
- produire des recommandations ;
- assurer la traçabilité.

Un événement métier enregistré ne doit pas être modifié silencieusement.

---

# Article 8 — La simplicité est une fonctionnalité

Atlas doit réduire la charge mentale.

La sophistication interne ne doit pas apparaître comme une complexité inutile dans l’interface.

Avant d’ajouter une étape, une option ou un paramètre, il faut rechercher une solution :

- automatique ;
- déduite ;
- progressive ;
- contextualisée ;
- définie par défaut.

---

# Article 9 — La confiance prévaut sur la sophistication

Atlas préfère une réponse simple et fiable à une réponse complexe mais incertaine.

Le produit doit reconnaître explicitement :

- les données manquantes ;
- les approximations ;
- les limites d’un calcul ;
- les incertitudes ;
- les erreurs.

Atlas ne doit jamais donner une apparence de certitude à une information qui ne l’est pas.

---

# Article 10 — Les recommandations sont actionnables

Une recommandation doit répondre clairement à trois questions :

1. Pourquoi cette recommandation existe-t-elle ?
2. Pourquoi mérite-t-elle l’attention maintenant ?
3. Que peut faire l’utilisateur ?

Une recommandation possède une action principale identifiable.

Les recommandations purement décoratives ou génériques doivent être évitées.

---

# Article 11 — Les automatisations sont transparentes

Toute automatisation doit être :

- identifiable ;
- traçable ;
- compréhensible ;
- configurable ;
- désactivable.

Lorsqu’une automatisation exécute une action, Atlas doit conserver :

- la règle déclenchée ;
- le contexte ;
- l’heure d’exécution ;
- le résultat ;
- les éventuelles erreurs.

---

# Article 12 — Les domaines sont responsables de leurs données

Chaque domaine possède :

- son vocabulaire ;
- ses entités ;
- ses règles ;
- ses invariants ;
- ses événements ;
- ses commandes.

Un domaine ne modifie pas directement les données internes d’un autre domaine.

Les échanges passent par des contrats publics clairement définis.

---

# Article 13 — Le même concept possède un seul langage

Un concept doit posséder :

- un nom officiel ;
- une définition officielle ;
- un comportement cohérent.

Le Product Language s’applique :

- à l’interface ;
- à la documentation ;
- aux événements ;
- aux API ;
- autant que possible au code.

Les synonymes non maîtrisés doivent être éliminés.

---

# Article 14 — La qualité fait partie du produit

Une fonctionnalité n’est pas terminée si elle n’est pas suffisamment :

- fiable ;
- sécurisée ;
- performante ;
- observable ;
- testée ;
- documentée ;
- accessible.

La qualité ne constitue pas une phase séparée ajoutée après le développement.

---

# Article 15 — Les performances font partie de l’expérience

Un comportement lent ou imprévisible dégrade la valeur du produit.

Les temps de réponse doivent rester cohérents avec l’usage.

Les traitements longs doivent :

- être visibles ;
- fournir un état ;
- gérer les erreurs ;
- pouvoir être repris lorsqu’il est pertinent de le faire.

---

# Article 16 — La sécurité et la confidentialité sont des exigences fondamentales

Atlas doit limiter l’accès aux données selon le principe du moindre privilège.

Toute fonctionnalité doit prendre en compte :

- l’authentification ;
- l’autorisation ;
- la confidentialité ;
- la traçabilité ;
- la protection contre les pertes ;
- la suppression ou l’export lorsque cela est requis.

Une nouvelle capacité ne doit pas affaiblir silencieusement la sécurité.

---

# Article 17 — Les intégrations sont des citoyens de première classe

Atlas doit pouvoir s’intégrer à l’environnement de l’utilisateur.

Les intégrations doivent respecter les mêmes exigences que les fonctionnalités natives :

- fiabilité ;
- sécurité ;
- traçabilité ;
- observabilité ;
- gestion des erreurs ;
- contrôle utilisateur.

Une intégration ne doit pas créer une dépendance impossible à remplacer sans justification stratégique.

---

# Article 18 — Chaque évolution doit renforcer Atlas

Une nouvelle fonctionnalité doit :

- résoudre un problème réel ;
- servir une cible définie ;
- renforcer une capability ;
- respecter les invariants ;
- posséder un résultat attendu.

Atlas ne développe pas une fonctionnalité uniquement pour augmenter le nombre de fonctionnalités disponibles.

---

# Article 19 — Le produit reste compréhensible

L’utilisateur ne doit pas avoir besoin de comprendre l’architecture interne d’Atlas.

Les concepts présentés doivent rester :

- clairs ;
- cohérents ;
- progressifs ;
- contextualisés.

Les termes techniques internes ne doivent pas apparaître inutilement dans l’expérience utilisateur.

---

# Article 20 — La qualité est cumulative

Chaque modification doit laisser Atlas dans un état au moins aussi cohérent qu’avant.

Une évolution ne doit pas introduire volontairement :

- une dette métier non documentée ;
- une contradiction terminologique ;
- une perte de traçabilité ;
- une régression connue sans plan de résolution ;
- une dépendance structurelle accidentelle.

Les compromis temporaires doivent être explicites, suivis et limités dans le temps.

---

# Gouvernance de la Constitution

## Modification mineure

Une modification rédactionnelle qui ne change pas le sens peut être intégrée directement.

## Modification majeure

Une modification qui change un principe fondamental doit :

1. décrire le problème ;
2. identifier les articles concernés ;
3. documenter les conséquences ;
4. créer une décision formelle ;
5. incrémenter la version majeure du document.

## Statut

La Constitution peut utiliser les statuts suivants :

- `In Review` ;
- `Stable` ;
- `Deprecated`.

Elle ne doit pas rester durablement en statut `Draft`.