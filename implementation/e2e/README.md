# Recette UI locale

Ce harnais vérifie le compte démo dans un vrai navigateur, en desktop et en
mobile. Il reste séparé des dépendances Vite de l’application et utilise Edge
installé sur Windows. Un runtime Node 22 local au dossier garantit la version
requise par Playwright, même si le Node global est plus ancien.

Préparer l’application et les données depuis le terminal habituel :

```bash
make up
make demo-seed
make demo-seed-empty
make serve
```

Si la suite backend est exécutée après ces commandes, rejouer les deux seeds :
les tests d’intégration réinitialisent leur base avant la recette navigateur.

Puis lancer la recette depuis PowerShell, à la racine du dépôt :

```powershell
npm --prefix implementation/e2e ci
npm --prefix implementation/e2e test
```

Pour conserver des captures temporaires de chaque écran dans le dossier
système `%TEMP%/atlas-playwright-results` :

```powershell
$env:ATLAS_E2E_SCREENSHOTS='true'
npm --prefix implementation/e2e test
```

Le profil complet vérifie les parcours métier. Le profil vide utilise
`demo-empty@atlas.test` / `DemoEmpty2026!` et couvre le premier démarrage sans
supprimer de données existantes. La recette d’inscription intercepte uniquement
ses appels HTTP : elle ne crée donc aucun utilisateur à chaque exécution.
Le scénario complet couvre aussi la chronologie commerciale seedée d’un client,
l’ouverture de son formulaire d’activité et celle d’une correction auditée.

Variables disponibles : `PLAYWRIGHT_BASE_URL`, `PLAYWRIGHT_CHANNEL`,
`ATLAS_DEMO_EMAIL`, `ATLAS_DEMO_PASSWORD`, `ATLAS_EMPTY_DEMO_EMAIL` et
`ATLAS_EMPTY_DEMO_PASSWORD`.

## Recette humaine du dashboard

Le dernier critère qualitatif ne doit pas être déduit d’un test automatisé.
Présenter le dashboard complet pendant 30 secondes, sans commentaire, puis
demander à la personne testée :

1. quelle action doit être traitée en premier ;
2. comment se porte l’activité ;
3. où ouvrir le dossier concerné.

Le critère est validé si ces trois réponses sont trouvées sans aide dans le
temps imparti.
