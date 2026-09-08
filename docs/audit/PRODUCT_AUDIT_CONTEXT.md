# Atlas — Product Audit Context

> Contexte compact destiné à une revue stratégique. Ce fichier résume le produit ; il ne remplace pas les sources normatives du dépôt.

## 1. Résumé exécutif

Atlas est un système de pilotage quotidien destiné en priorité aux indépendants et petites entreprises de services.

Sa thèse produit est simple : les outils existants savent surtout **enregistrer** l'activité ; Atlas cherche à **interpréter** les données opérationnelles et à transformer cette compréhension en décisions puis en actions utiles.

Boucle centrale :

```text
Gérer -> Comprendre -> Décider -> Agir -> Mesurer
```

La facturation, le CRM et le suivi commercial constituent donc la couche de gestion nécessaire pour produire une donnée fiable. La différenciation recherchée se situe au-dessus : Business Health, détection de risques et opportunités, Advisor, priorisation, explications et mesure des effets.

Mission stable : **aider chaque indépendant à prendre de meilleures décisions pour son activité, chaque jour.**

Ambition stable : **devenir le système de pilotage quotidien des indépendants et petites entreprises de services**, sans devenir un ERP généraliste.

## 2. Problème adressé

La cible dispose déjà de nombreux outils spécialisés : facturation, banque, calendrier, CRM léger, tableurs, messagerie, gestion de tâches ou IA conversationnelle.

Le problème identifié n'est donc pas seulement l'absence d'outils, mais la fragmentation :

- les données restent dispersées ;
- les logiciels sont souvent passifs ;
- l'utilisateur reconstruit mentalement l'état de son activité ;
- les risques sont détectés tard ;
- les décisions commerciales et financières sont prises à partir d'informations partielles ;
- les tâches non urgentes mais importantes sont repoussées.

Atlas cherche à répondre à trois questions dès l'ouverture :

1. Qu'est-ce qui a changé ?
2. Pourquoi cela compte-t-il ?
3. Que devrais-je faire maintenant ?

## 3. Cible V1

La stratégie actuelle cible exclusivement les prestataires de services, notamment :

- développeurs ;
- consultants ;
- designers ;
- formateurs ;
- freelances ;
- petites agences et autres prestataires numériques ou de conseil.

Caractéristiques communes recherchées : relation client directe, cycle devis -> facture -> paiement, peu de complexité de stock, forte dépendance au temps disponible et besoin de visibilité sur l'activité future.

### Persona principal documenté

Le persona de référence, encore `Draft`, est « Julien » : développeur freelance de 34 ans, environ 6 ans d'indépendance, travaillant seul, avec un CA annuel indicatif de 70 k€ à 120 k€, 5 à 12 clients actifs et une maturité numérique élevée.

Il possède une activité viable mais la pilote encore largement à l'intuition. Il accepte de payer pour un produit qui lui fait gagner du temps, améliore sa visibilité, évite une erreur coûteuse ou l'aide à mieux gagner sa vie. Il accorde une importance particulière au design, à la rapidité, à la transparence du prix, à la protection des données et à la crédibilité des recommandations.

### Jobs To Be Done principaux

- savoir quoi traiter en priorité en commençant la journée ;
- anticiper une période creuse ;
- relancer les bonnes opportunités au bon moment ;
- réduire les délais de paiement ;
- comprendre les causes d'une évolution de performance ;
- déterminer quand ajuster ses tarifs.

## 4. Proposition de valeur

Atlas ne veut pas vendre « un autre logiciel de gestion ».

La proposition de valeur recherchée est :

- centraliser les faits utiles de l'activité ;
- les transformer en informations compréhensibles ;
- détecter risques, anomalies et opportunités ;
- expliquer pourquoi une situation mérite de l'attention ;
- proposer une priorité d'action ;
- permettre d'agir dans le même produit ;
- mesurer ensuite l'effet des actions.

La valeur fondamentale revendiquée est l'amélioration de la **qualité des décisions**, et pas seulement le temps administratif économisé.

Bénéfices attendus : réduction des oublis et de la charge mentale, encaissements plus rapides, meilleure anticipation, activité plus sécurisée et décisions commerciales plus rationnelles.

## 5. Différenciation recherchée

Formulation actuelle de la stratégie : **Atlas est un logiciel de décision.**

Les outils traditionnels enregistrent les données ; Atlas veut expliquer leur signification puis recommander la meilleure action.

Les deux briques les plus différenciantes prévues sont :

### Business Health

Une lecture synthétique de la santé récente de l'activité, avec score, facteurs, évolution, fiabilité et zone nécessitant de l'attention. Le produit ne doit jamais inventer un score en l'absence de données suffisantes.

### Advisor

Un moteur de recommandations et de priorisation. Les recommandations doivent être déterministes et explicables au MVP, montrer leur justification, conduire vers une action concrète et permettre de mesurer ou enregistrer la suite donnée.

La sophistication technique n'est pas destinée à être visible. L'expérience doit rester : « ce qui se passe -> pourquoi cela compte -> ce que je peux faire ».

## 6. Principes de conception structurants

Les principes suivants sont `Stable` :

1. **L'action avant l'information** — un indicateur doit servir une décision ou une action.
2. **L'explication avant la prédiction** — une règle simple et explicable vaut mieux qu'un modèle opaque.
3. **L'utilisateur reste responsable** — autonomie explicite, choisie et réversible lorsque possible.
4. **Une donnée, plusieurs usages** — éviter les doubles saisies et rendre chaque donnée utile.
5. **Les événements racontent l'activité** — conserver les faits permettant de comprendre l'évolution.
6. **La simplicité est une fonctionnalité** — complexité interne, simplicité apparente.
7. **Une priorité claire vaut mieux qu'une longue liste**.
8. **L'incertitude doit être visible**.
9. **Les règles métier priment sur la technologie**.
10. **La confiance se construit dans les détails** — fiabilité, précision, traçabilité, sécurité et cohérence.

## 7. Anti-objectifs

Atlas ne cherche pas à devenir :

- un ERP généraliste ;
- un logiciel comptable complet ;
- un simple logiciel de facturation ;
- un dashboard rempli de métriques passives ;
- une plateforme infiniment configurable ;
- un produit construit par imitation fonctionnelle des concurrents ;
- une IA qui remplace silencieusement le jugement humain ;
- un produit destiné à toutes les entreprises.

Le produit refuse également de collecter des données sans utilité identifiable et de sacrifier la cohérence structurelle pour livrer plus vite.

## 8. Périmètre MVP

Objectif du MVP : permettre à un indépendant de suivre son cycle commercial, d'être payé et de recevoir des recommandations réellement utiles à partir de ses données.

Parcours attendu :

1. créer un Workspace ;
2. importer un historique ou créer un premier client ;
3. créer et envoyer un devis ;
4. faire accepter ou refuser le devis ;
5. transformer le devis accepté en facture ;
6. enregistrer le paiement ;
7. comprendre la santé récente de l'activité ;
8. recevoir une priorité d'action claire.

### Capacités incluses

- Identity : inscription, connexion, récupération de mot de passe, vérification email ;
- Workspace : identité commerciale et préférences principales ;
- CRM : clients, contacts, opportunités simples, historique, import ;
- Billing : devis, acceptation/refus, factures, acompte simple, paiements manuels, PDF, échéances, relances, import historique ;
- Analytics interne : faits idempotents, métriques déterministes, fraîcheur et snapshots ;
- Business Health ;
- Advisor ;
- notifications in-app et certains emails ;
- Dashboard comme surface de composition.

### Exclusions explicites du MVP

Comptabilité complète, synchronisation/rapprochement bancaire, facturation électronique complète, connecteurs tiers synchronisés, stock, paie, projets complexes, automatisations libres, application native, multi-devises, multi-workspaces, marketplace, IA générative autonome, prévisions financières avancées et commercialisation réelle de l'abonnement.

## 9. État réel du produit au moment de cette synthèse

Le dépôt n'est plus seulement un référentiel conceptuel : une implémentation MVP existe sous `implementation/`.

La documentation courante indique notamment :

- backend et verticales métier déjà implémentés ;
- interface de démo / early access en React 19 + Vite + TypeScript ;
- Dashboard orienté action ;
- CRM, opportunités, devis et acceptation publique ;
- facturation, paiements, acomptes simples, avoirs, relances et PDF ;
- import historique CRM/Billing puis reconstruction Analytics ;
- Business Health, Advisor et notifications ;
- onboarding, settings et invitations membres bornées ;
- landing Early Access publique ;
- catalogue candidat d'abonnement et simulation technique ;
- Stripe prévu mais paiement réel et enforcement commercial désactivés tant que les gates ne sont pas validées.

La surface actuelle est donc suffisamment avancée pour des démonstrations et entretiens utilisateurs, mais la documentation distingue explicitement **capacité technique** et **validation commerciale**.

## 10. Branding et langage produit

Le nom de produit est **Atlas**.

Le branding conceptuel repose aujourd'hui davantage sur une idée de **pilotage, orientation, compréhension et action** que sur une identité publicitaire documentée dans ce pack.

Le dépôt possède en revanche une discipline forte de Product Language :

- un concept métier doit avoir un seul nom officiel ;
- terminologie cohérente entre documentation, UI, API, événements, code et tests ;
- vocabulaire destiné à rester compréhensible par le métier ;
- concepts techniques internes non exposés inutilement à l'utilisateur.

Pour l'audit branding, Astra doit donc distinguer :

1. la **cohérence sémantique interne**, déjà fortement cadrée ;
2. la **force de marque externe** : nom, mémorisation, promesse, désirabilité, ton, différenciation perçue et capacité à expliquer Atlas en quelques secondes — éléments qui doivent être jugés comme des sujets de marché et non présumés validés par la qualité de la documentation.

## 11. Pricing actuel

La stratégie tarifaire est `Draft`. Les montants sont des **hypothèses de validation**, pas des tarifs commercialisés.

### Candidat V1

| Élément | Hypothèse actuelle |
|---|---|
| Offre | `Atlas Solo`, offre payante unique |
| Mensuel | 24 € HT/mois |
| Annuel | 240 € HT/an |
| Plage à tester | 19 / 24 / 29 € HT/mois |
| Essai | 30 jours, produit complet, sans carte bancaire |
| Freemium permanent | Aucun |
| Métrique | 1 Workspace actif par période |
| Quotas métier | Aucun quota commercial sur clients, devis ou factures |
| Différenciation incluse | Business Health + Advisor + priorités + preuves |
| Équipe | Exploration ultérieure |

Logique : Atlas ne veut pas entrer dans une guerre de prix avec les outils de facturation gratuits ou peu chers. Le prix doit être défendu par la valeur de pilotage et de décision.

Le packaging unique cherche également à éviter de placer les capacités différenciantes derrière un plan supérieur : l'utilisateur doit pouvoir expérimenter toute la boucle `Gérer -> Comprendre -> Décider -> Agir -> Mesurer`.

### Benchmark documenté

La stratégie compare notamment Atlas à Freebe, Tiime, Indy et Abby. La conclusion interne est que la facturation seule est devenue peu chère voire gratuite, tandis que les offres plus avancées montent en prix avec l'automatisation, la collaboration, la comptabilité ou les services financiers.

Ce benchmark soutient un ordre de grandeur, mais **ne prouve pas la disposition à payer pour la proposition Atlas**.

## 12. Validation pricing prévue

Le dépôt comporte un protocole explicite afin d'éviter de confondre opinion et preuve.

Test proposé : présenter une seule cellule de prix par participant :

- P19 : 19 € HT/mois ;
- P24 : 24 € HT/mois ;
- P29 : 29 € HT/mois.

Les participants doivent correspondre au persona et pouvoir décider d'un achat logiciel réel.

Les intentions vagues ne sont pas considérées comme des preuves. Les signaux observables distinguent paiement, précommande, essai engagé et différents motifs de refus.

La gate `Draft -> In Review` demande notamment au moins 15 entretiens admissibles et 10 décisions finales motivées, avec les trois cellules testées.

La stabilisation réclame ensuite une cohorte payante observée dans la durée.

### Point critique pour tout audit

Au moment de cette synthèse, **l'existence d'une stratégie de validation rigoureuse ne doit pas être interprétée comme l'existence de résultats de validation**. Le registre documentaire présenté reste à alimenter par des observations réelles.

## 13. Hypothèses majeures à challenger

Un audit externe doit attaquer en priorité les hypothèses suivantes plutôt que la qualité rédactionnelle du référentiel :

1. **Le problème de pilotage est-il suffisamment douloureux pour déclencher un achat ?**
2. **La cible veut-elle remplacer certains outils ou accepter une couche supplémentaire ?**
3. **Atlas peut-il obtenir assez de données fiables sans créer trop de saisie ou dépendre rapidement d'intégrations absentes du MVP ?**
4. **Business Health est-il immédiatement compréhensible et crédible ?**
5. **Les recommandations Advisor seront-elles perçues comme réellement meilleures que rappels, dashboards, règles simples ou usage ponctuel d'une IA généraliste ?**
6. **La boucle complète produit crée-t-elle assez de valeur avant d'avoir beaucoup d'historique ?**
7. **Le persona 70–120 k€ est-il assez homogène et assez large pour la V1 ?**
8. **24 € HT/mois est-il cohérent avec la valeur perçue, indépendamment du benchmark concurrentiel ?**
9. **L'absence de freemium facilite-t-elle le positionnement premium ou freine-t-elle trop l'acquisition ?**
10. **Le périmètre gestion + analytics + décision est-il encore assez focalisé pour une petite équipe ?**
11. **Le nom Atlas et la promesse de “pilotage” sont-ils assez distinctifs sur le marché ?**
12. **La profondeur documentaire traduit-elle une vraie maîtrise ou risque-t-elle de créer de l'over-engineering avant validation ?**

## 14. Risques stratégiques à surveiller

Ces éléments sont formulés comme angles d'audit, pas comme défauts déjà prouvés :

- **cold start de la valeur** : recommandations faibles tant que l'historique est insuffisant ;
- **coût d'adoption** : migration et doubles saisies face à des outils déjà installés ;
- **surface produit large** : CRM + Billing + Analytics + Health + Advisor peut diluer l'exécution ;
- **confiance** : une seule recommandation erronée peut dégrader fortement la valeur perçue ;
- **différenciation abstraite** : “meilleures décisions” doit être démontrée par des situations concrètes ;
- **preuve du ROI** : le produit devra montrer que les actions proposées ont effectivement amélioré un résultat ;
- **positionnement de catégorie** : “logiciel de décision” peut être différenciant mais demander un effort pédagogique ;
- **écart documentation/marché** : le référentiel est très structuré alors que les preuves utilisateurs doivent encore déterminer quelles hypothèses survivront.

## 15. Forces conceptuelles à vérifier plutôt qu'à présumer

Le référentiel présente plusieurs choix cohérents qui méritent une validation externe :

- mission et anti-objectifs nettement formulés ;
- priorité donnée à l'action plutôt qu'aux dashboards décoratifs ;
- explicabilité et visibilité de l'incertitude ;
- séparation entre vérité métier et surfaces de composition ;
- volonté de mesurer l'effet d'une recommandation ;
- discipline de langage ;
- séparation claire entre hypothèse pricing et preuve de marché ;
- refus de monétiser artificiellement des quotas d'objets métier ;
- MVP borné malgré une vision à long terme plus large.

Un auditeur ne doit pas noter positivement ces éléments uniquement parce qu'ils sont bien documentés : il doit demander s'ils produisent un avantage utilisateur, une différenciation défendable et un modèle économique crédible.

## 16. Ce que l'audit doit juger

Un audit stratégique Atlas doit au minimum donner un avis sur :

- intensité et fréquence du problème ;
- précision de la cible ;
- clarté de la proposition de valeur ;
- différenciation réelle versus outils existants et IA généralistes ;
- crédibilité de Business Health et Advisor ;
- cohérence du MVP ;
- risque de sur-construction ;
- friction d'onboarding et besoin d'intégrations ;
- capacité à atteindre un “aha moment” rapidement ;
- branding, nom et langage externe ;
- pricing et packaging ;
- stratégie d'essai ;
- preuves manquantes avant commercialisation ;
- qualité et utilité de la documentation ;
- risques business, produit et go-to-market ;
- potentiel à devenir une habitude quotidienne ;
- capacité potentielle à construire un avantage défendable.

## 17. Règles pour l'auditeur

- Ne confonds jamais **documentation détaillée** et **validation marché**.
- Ne suppose pas qu'une fonctionnalité documentée est commercialement utile.
- Ne pénalise pas Atlas pour une capacité explicitement hors MVP sans expliquer pourquoi elle serait indispensable à la validation de la thèse.
- Challenge les hypothèses même lorsqu'elles sont cohérentes entre elles.
- Distingue clairement : problème de stratégie, problème d'exécution, inconnue nécessitant une preuve et préférence subjective.
- Lorsque tu affirmes qu'une information manque, indique si elle est réellement nécessaire avant de demander à lire davantage de fichiers.
- Avant toute lecture supplémentaire du dépôt, nomme les fichiers précis recherchés et la question qu'ils doivent résoudre.

## 18. Sources à ouvrir uniquement en cas de besoin

| Question | Source prioritaire |
|---|---|
| Mission / vision | `fondation/vision/mission.md`, `fondation/vision/vision.md` |
| Principes / limites | `fondation/vision/principles.md`, `fondation/vision/anti-goals.md` |
| Stratégie | `fondation/product/product-strategy.md` |
| Cible | `fondation/product/personnas/persona-primary.md` |
| Pricing détaillé | `fondation/product/pricing-strategy.md` |
| Preuves pricing | `evolution/governance/pricing-validation.md` |
| MVP | `evolution/roadmap/mvp-scope.md` |
| UX et fonctionnalités courantes | `implementation/UI-DEMO-SCOPE.md` |
| Terminologie | `fondation/language/README.md` puis ses sous-documents seulement si nécessaire |
| Architecture/domaines | `fondation/domain-map/README.md` ou blueprint uniquement si l'audit porte sur la cohérence fonctionnelle |

Pour une revue générale, **ne pas ouvrir toutes ces sources** : ce fichier est précisément conçu pour éviter cette consommation de contexte.