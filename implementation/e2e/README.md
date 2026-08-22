# Recette UI locale

Ce harnais vérifie le compte démo dans un vrai navigateur, en desktop et en
mobile. Il reste séparé des dépendances Vite de l’application.

- **Linux / macOS** : Chromium Playwright (canal par défaut).
- **Windows** : Edge (`msedge`) si `PLAYWRIGHT_CHANNEL` n’est pas défini.

Un runtime Node 22 local au dossier garantit la version requise par Playwright,
même si le Node global est plus ancien.

La commande standard prépare l’application et les données, démarre le serveur
si nécessaire, installe Chromium puis exécute Pest et Playwright :

```bash
make test
```

Pour ne lancer que la recette navigateur :

```bash
make e2e
```

Les tests E2E sont aussi exécutés systématiquement par le workflow
`Application quality gates` sur chaque pull request et push vers `main`.
La suite backend réinitialisant sa base, les seeds démo sont rejoués juste avant
Playwright.

Windows (PowerShell), pour forcer Edge et des captures :

```powershell
npm --prefix implementation/e2e ci
$env:ATLAS_E2E_SCREENSHOTS='true'
npm --prefix implementation/e2e test
```

Le profil complet vérifie les parcours métier. Le profil vide utilise
`demo-empty@atlas.test` / `DemoEmpty2026!` et couvre le premier démarrage sans
supprimer de données existantes. La recette d’inscription intercepte uniquement
ses appels HTTP : elle ne crée donc aucun utilisateur à chaque exécution.
Le scénario complet couvre aussi la chronologie commerciale seedée d’un client,
l’ouverture de son formulaire d’activité, d’une correction auditée et d’un
retrait terminal avec motif obligatoire. Le scénario v4 fournit une correction
et un retrait réels pour vérifier la vue d’audit séparée. La recette ouvre aussi
la confirmation de gain manuel d’une opportunité qualifiée sans la clôturer.

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
