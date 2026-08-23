<!DOCTYPE html>
<html lang="fr">
<head>
    @php($isLanding = request()->path() === '/')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="{{ $isLanding ? '#f7f9f6' : '#f4f6f3' }}">
    <title>{{ $isLanding ? 'Atlas — Pilotez votre activité avec confiance' : 'Atlas' }}</title>
    @if ($isLanding)
        <meta name="description" content="Atlas relie clients, devis, factures et données réelles pour aider les indépendants à comprendre leur activité et décider quoi faire ensuite.">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="fr_FR">
        <meta property="og:title" content="Atlas — Votre activité, enfin lisible">
        <meta property="og:description" content="Le système de pilotage quotidien des indépendants et petites entreprises de services. Early Access de 30 jours, sans carte bancaire.">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="canonical" href="{{ url('/') }}">
    @endif
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/web/main.tsx'])
</head>
<body class="antialiased">
    <div id="root"></div>
</body>
</html>
