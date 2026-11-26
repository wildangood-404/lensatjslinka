<x-layouts.pumk-admin title="Home — LENSA TJSL INKA">
    @include('components.home-showcase')

    <div class="mx-auto -mt-8 flex w-full max-w-7xl flex-wrap justify-end gap-3 px-4 pb-8 sm:px-6 lg:px-8">
        <a href="{{ route('pumk-admin.umkm-binaan.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
            Lihat Daftar Mitra
        </a>
        <a href="{{ route('pumk-admin.umkm-binaan.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">
            Tambah Mitra Binaan
        </a>
    </div>
</x-layouts.pumk-admin>
