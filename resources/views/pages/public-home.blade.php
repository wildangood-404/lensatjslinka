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
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body
    class="min-h-screen bg-slate-50 font-sans text-slate-900"
    x-data="{ loginOpen: @js($errors->any() || request()->boolean('login')) }"
    @keydown.escape.window="loginOpen = false"
>
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <nav class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-5 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi portal">
            <a href="{{ route('entry') }}" class="flex min-w-0 items-center">
                <img src="{{ asset('images/LOGO TJSL.png') }}" class="h-9 w-auto max-w-[160px] shrink-0 object-contain" alt="TJSL INKA">
            </a>
            <button type="button" @click="loginOpen = true" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">
                Login
            </button>
        </nav>
    </header>
    <main>
        @include('components.home-showcase', ['showCharts' => false])
    </main>
    <x-footer />

    <div
        x-show="loginOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4"
        @click.self="loginOpen = false"
        role="presentation"
    >
        <section
            x-show="loginOpen"
            x-transition
            class="my-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl sm:p-8"
            role="dialog"
            aria-modal="true"
            aria-labelledby="login-modal-title"
        >
            <div class="mb-5 text-center">
                <img src="{{ asset('images/LOGO TJSL.png') }}" class="mx-auto h-16 w-auto max-w-[220px] object-contain" alt="TJSL INKA">
                <h1 id="login-modal-title" class="mt-4 text-2xl font-bold text-slate-900">Login</h1>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="login-username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                    <input
                        id="login-username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        required
                        autocomplete="username"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    >
                </div>
                <div>
                    <label for="login-password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="login-password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    >
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                    Ingat sesi saya
                </label>
                <button type="submit" class="w-full rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">
                    Login
                </button>
            </form>
        </section>
    </div>
</body>
</html>
