<x-layouts.admin title="Kelola Berita TJSL INKA">
    <div class="mx-auto max-w-6xl">
        <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Berita TJSL INKA</h1>
                <p class="mt-1 text-sm text-slate-500">Berita berstatus Terbit ditampilkan di Home publik dan dashboard seluruh role.</p>
            </div>
            <a href="{{ route('admin.news.create') }}" class="rounded-lg bg-action-primary px-5 py-3 font-semibold text-white hover:bg-action-primary-hover">
                + Tambah Berita
            </a>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-slate-700">
                    <tr>
                        <th class="px-5 py-4">Gambar</th>
                        <th class="px-5 py-4">Judul</th>
                        <th class="px-5 py-4">Sumber</th>
                        <th class="px-5 py-4">Caption</th>
                        <th class="px-5 py-4">Tanggal</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($newsItems as $newsItem)
                        <tr>
                            <td class="px-5 py-3">
                                <img src="{{ $newsItem->image_url }}" alt="{{ $newsItem->title }}" class="h-14 w-14 rounded object-cover">
                            </td>
                            <td class="px-5 py-3 font-semibold text-slate-900">{{ $newsItem->title }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $newsItem->source }}</td>
                            <td class="max-w-xs px-5 py-3 text-slate-600">{{ $newsItem->caption }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $newsItem->published_date }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $newsItem->status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $newsItem->status === 'published' ? 'Terbit' : 'Draft' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.news.edit', $newsItem) }}" class="rounded border border-action-primary px-3 py-1.5 text-xs font-semibold text-action-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.news.destroy', $newsItem) }}" onsubmit="return confirm('Hapus berita ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">Belum ada berita TJSL INKA.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $newsItems->links() }}</div>
    </div>
</x-layouts.admin>
