---
title: Fondations UI Atlas
owner: Product + Engineering
last_updated: 2026-08-22
---

# Fondations UI Atlas

Ce document fixe les conventions visuelles de l’application React. Il évite
qu’un nouvel écran réintroduise ses propres couleurs, espacements ou états.

## Direction visuelle

- **Sobre et décisionnelle** : peu de couleurs, une hiérarchie forte et une
  densité adaptée aux écrans métier.
- **Vert Atlas** pour les actions principales et les données positives ; navy
  profond pour la navigation et les actions à fort contraste.
- **Surfaces chaudes et légères** : fond gris-vert, cartes blanches, bordures
  discrètes et ombres diffuses. Une bordure reste visible sans l’ombre.
- **Information avant décoration** : chaque accent visuel doit aider à repérer
  une action, un statut ou une donnée importante.

Les tokens autoritatifs vivent dans `resources/css/app.css`. Une page ne doit
pas introduire une nouvelle couleur de marque en dur. Les couleurs rouge,
ambre, bleu et émeraude restent réservées aux états sémantiques.

## Composants communs

| Besoin | Composant |
|---|---|
| Signature Atlas | `components/ui/Brand.tsx` |
| Icônes fonctionnelles | `components/ui/Icon.tsx` |
| Titre et actions de page | `components/ui/PageHeader.tsx` |
| Champs, boutons et feedbacks | `components/auth/AuthLayout.tsx` |
| Statuts métier | `components/crm/StatusBadge.tsx` |
| Absence de données | `components/ui/EmptyState.tsx` |
| Chargement | `components/ui/PageSkeleton.tsx` |

Les pages authentifiées utilisent `atlas-page` sur leur conteneur racine. Les
fiches métier commencent par un retour explicite, puis une surface d’identité
contenant le type de document, le titre, le statut et les actions principales.

## Règles UX

1. Une page présente un seul titre principal visible et une description courte.
2. L’action principale utilise le vert Atlas ; les actions secondaires restent
   blanches avec une bordure. Une action destructive n’est jamais verte.
3. Les formulaires conservent un libellé visible, une cible tactile d’au moins
   44 px et un état de chargement explicite.
4. Un écran asynchrone distingue chargement, erreur, absence de données et
   succès. Aucun espace vide ambigu ne remplace un état.
5. Le focus clavier, les rôles ARIA et `prefers-reduced-motion` sont conservés.
6. Le viewport mobile ne doit jamais produire de débordement horizontal ; les
   groupes d’actions passent en colonne avant de réduire leur cible tactile.

## Gate d’une nouvelle page

- typecheck et build Vite verts ;
- titre et navigation accessibles par leur nom ;
- contrôle à 390 px et à 1440 px ;
- parcours clavier complet ;
- états Data, Empty, Loading et Error vérifiés lorsque le contrat les prévoit.
