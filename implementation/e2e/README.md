# Recette UI locale

Ce harnais vérifie le compte démo dans un vrai navigateur, en desktop et en
mobile. Il reste séparé des dépendances Vite de l’application et utilise Edge
installé sur Windows. Un runtime Node 22 local au dossier garantit la version
requise par Playwright, même si le Node global est plus ancien.

Préparer l’application et les données depuis le terminal habituel :

```bash
make up
make demo-seed
make serve
```

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

Variables disponibles : `PLAYWRIGHT_BASE_URL`, `PLAYWRIGHT_CHANNEL`,
`ATLAS_DEMO_EMAIL` et `ATLAS_DEMO_PASSWORD`.
