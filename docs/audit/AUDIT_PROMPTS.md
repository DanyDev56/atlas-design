# Atlas — Prompts d'audit Astra

Ces prompts sont conçus pour obtenir un raisonnement approfondi sans déclencher une lecture exhaustive du dépôt.

## Règle commune

Ajouter cette consigne à toute passe :

> Utilise `docs/audit/PRODUCT_AUDIT_CONTEXT.md` comme contexte principal. Ne parcours pas le dépôt récursivement. Ne consulte un autre document que si une information précise manque pour trancher un point important. Dans ce cas, ouvre au maximum 3 fichiers supplémentaires et explique brièvement ce que chacun doit permettre de vérifier. Ne relis pas les fichiers déjà étudiés. Distingue faits stabilisés, hypothèses documentées et preuves de marché observées.

---

## 1. Audit stratégique initial

> Agis comme un comité critique composé d'un fondateur SaaS B2B expérimenté, d'un Product Strategist et d'un spécialiste du go-to-market pour indépendants. À partir uniquement de `docs/audit/PRODUCT_AUDIT_CONTEXT.md`, évalue Atlas comme si tu devais décider d'investir du temps et de l'argent dans son lancement. Juge : problème, cible, proposition de valeur, différenciation, MVP, adoption, pricing, branding, crédibilité commerciale et risques de sur-construction. Ne propose pas encore de refonte détaillée. Donne une note /10 par dimension, les 10 constats ayant le plus fort impact, puis classe-les en `Critique`, `Important` ou `À surveiller`. Termine par les 5 hypothèses qui doivent être validées avant toute accélération. N'ouvre aucun autre fichier lors de cette première passe.

Ce prompt doit être la première utilisation d'Astra.

---

## 2. Audit problème / marché

> Analyse uniquement la force du problème, le persona, les Jobs To Be Done et la probabilité qu'un indépendant change ses habitudes pour Atlas. Cherche particulièrement les problèmes “nice to have”, les fréquences d'usage insuffisantes, le coût de migration et les alternatives réelles. Ne juge pas l'architecture technique. Donne les preuves terrain nécessaires pour confirmer ou invalider chaque hypothèse importante.

---

## 3. Audit proposition de valeur et différenciation

> Évalue si `Business Health + Advisor + boucle Gérer -> Comprendre -> Décider -> Agir -> Mesurer` constitue une différenciation compréhensible, désirable et défendable. Compare mentalement cette proposition à un assemblage logiciel de facturation + tableur/Notion + ChatGPT/assistant IA. Identifie précisément ce qu'Atlas doit faire mieux pour mériter d'exister comme produit distinct.

---

## 4. Audit branding

> Agis comme un Brand Strategist SaaS B2B. Évalue le nom Atlas, le territoire de marque “pilotage / orientation / décision”, la promesse, la mémorisation, la différenciation et la capacité à expliquer le produit en 5, 15 et 60 secondes. Distingue la qualité du Product Language interne de l'efficacité du branding externe. Identifie les ambiguïtés ou formulations trop abstraites. Ne propose une refonte de marque que si le diagnostic la justifie.

---

## 5. Audit pricing

> Agis comme un spécialiste pricing SaaS. Challenge l'offre unique Atlas Solo, 24 € HT/mois, 240 € HT/an, l'essai de 30 jours sans carte, l'absence de freemium et la métrique Workspace. Ne considère pas le benchmark concurrentiel comme une validation de disposition à payer. Analyse willingness-to-pay, ancrage, packaging, friction, perception premium et capacité du produit à démontrer son ROI avant la fin de l'essai. Évalue aussi la qualité du protocole de validation 19/24/29 € sans supposer que ses résultats existent.

Si une vérification est indispensable, les seules sources prioritaires sont `fondation/product/pricing-strategy.md` et `evolution/governance/pricing-validation.md`.

---

## 6. Audit MVP / focus

> Évalue si le MVP est le chemin minimal crédible pour valider la thèse Atlas. Cherche les capacités trop ambitieuses, celles qui ne participent pas directement à la preuve de valeur et les dépendances manquantes qui pourraient empêcher l'Advisor ou Business Health d'être utiles. Pour chaque capacité, classe-la `Indispensable à la thèse`, `Utile mais différable` ou `Distraction potentielle`. Ne transforme pas cet audit en revue de code.

---

## 7. Audit documentation / over-engineering

> Juge la documentation comme un actif produit, pas comme un exercice rédactionnel. Évalue si le niveau de formalisation aide réellement une petite équipe à construire et apprendre plus vite, ou s'il risque de figer des hypothèses non validées et de créer de l'over-engineering. Cherche les zones où Atlas semble plus certain dans sa conception interne que dans ses preuves de marché. Propose uniquement les simplifications ayant un impact concret sur la vitesse d'apprentissage.

Pour cette passe, ne parcours pas toute la documentation. Commence par le contexte compact et demande explicitement les 3 fichiers maximum dont tu as besoin pour vérifier ton diagnostic.

---

## 8. Audit final après passes ciblées

> Reprends uniquement les conclusions déjà produites dans cette session. Ne relis pas le dépôt. Consolide-les en une décision de niveau fondateur : `GO`, `GO sous conditions`, `PIVOT partiel` ou `STOP`. Présente les forces défendables, les risques existentiels, les inconnues, puis un plan de validation sur 30/60/90 jours. Chaque action doit être liée à une hypothèse à réduire, pas à une envie d'ajouter des fonctionnalités.

---

## Workflow recommandé

Pour limiter fortement la consommation :

```text
Passe 1 : Audit stratégique initial
              |
              v
Sélectionner uniquement 2 ou 3 problèmes importants
              |
              v
Passes ciblées correspondantes
              |
              v
Audit final sans relecture du dépôt
```

Ne pas exécuter automatiquement les huit audits. La première passe doit déterminer lesquels sont réellement utiles.

## Ce qu'il faut éviter

Éviter les prompts suivants :

- « analyse tout mon dépôt » ;
- « lis toute la documentation » ;
- « trouve tous les problèmes possibles » ;
- « analyse puis corrige tout » ;
- « compare chaque fichier avec tous les autres ».

Ils augmentent fortement le contexte sans garantir un meilleur jugement produit.

Préférer une question décisionnelle précise, un contexte compact et un nombre borné de lectures supplémentaires.