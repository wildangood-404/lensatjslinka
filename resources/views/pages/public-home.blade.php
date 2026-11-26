<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal informasi TJSL dan PUMK PT INKA (Persero).">
    <title>LENSA TJSL INKA — Portal Informasi TJSL &amp; PUMK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <nav class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-5 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi portal">
            <a href="{{ route('entry') }}" class="flex min-w-0 items-center gap-4">
                <img src="{{ asset('images/logo/lensa-tjsl-inka-navbar.png') }}" class="h-11 w-auto shrink-0 object-contain" alt="LENSA TJSL INKA">
                <span class="hidden border-l border-slate-200 pl-4 text-sm font-medium leading-5 text-slate-600 sm:block">
                    Portal Informasi TJSL &amp; PUMK<br>PT INKA (Persero)
                </span>
            </a>
            <a href="{{ route('login') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">
                Masuk / Login
            </a>
        </nav>
    </header>
    <main>
        @include('components.home-showcase')
    </main>
    <footer class="border-t border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
        LENSA TJSL INKA · PT INKA (Persero)
    </footer>
</body>
</html>
