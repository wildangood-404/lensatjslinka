<x-layouts.admin :title="$newsItem->exists ? 'Edit Berita TJSL INKA' : 'Tambah Berita TJSL INKA'">
    <div class="mx-auto max-w-3xl">
        <div class="mb-7 flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold">{{ $newsItem->exists ? 'Edit Berita' : 'Tambah Berita' }}</h1>
            <a href="{{ route('admin.news.index') }}" class="text-sm font-semibold text-slate-600">Kembali ke Berita</a>
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ $newsItem->exists ? route('admin.news.update', $newsItem) : route('admin.news.store') }}"
            class="space-y-5 rounded-xl bg-white p-6 shadow"
        >
            @csrf
            @if ($newsItem->exists) @method('PUT') @endif

            <label class="block">
                <span class="mb-2 block font-semibold">Judul Berita</span>
                <input name="title" value="{{ old('title', $newsItem->title) }}" required maxlength="255" class="w-full rounded-lg border px-3 py-2.5">
                @error('title') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block font-semibold">Kategori / Sumber Berita</span>
                <input name="source" value="{{ old('source', $newsItem->source) }}" required maxlength="255" class="w-full rounded-lg border px-3 py-2.5" placeholder="Contoh: Kompas">
                @error('source') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block font-semibold">Caption Singkat</span>
                <textarea name="caption" required maxlength="180" rows="3" class="w-full rounded-lg border px-3 py-2.5">{{ old('caption', $newsItem->caption) }}</textarea>
                <small class="text-slate-500">Ringkasan untuk kartu berita, maksimal 180 karakter.</small>
                @error('caption') <small class="block text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block font-semibold">Isi Berita</span>
                <textarea name="content" required rows="8" class="w-full rounded-lg border px-3 py-2.5">{{ old('content', $newsItem->content) }}</textarea>
                @error('content') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block font-semibold">Tanggal Berita</span>
                <input name="published_date" value="{{ old('published_date', $newsItem->published_date) }}" required maxlength="100" class="w-full rounded-lg border px-3 py-2.5" placeholder="Contoh: 5 Maret 2026">
                @error('published_date') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <label class="block">
                <span class="mb-2 block font-semibold">Status Publikasi</span>
                <select name="status" required class="w-full rounded-lg border px-3 py-2.5">
                    @foreach(['published' => 'Terbit', 'draft' => 'Draft'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $newsItem->status ?? 'published') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <small class="text-red-600">{{ $message }}</small> @enderror
            </label>

            <div>
                <span class="mb-2 block font-semibold">Gambar Berita</span>
                @if ($newsItem->exists)
                    <img src="{{ $newsItem->image_url }}" alt="Gambar berita saat ini" class="mb-3 h-32 w-32 rounded-lg object-cover">
                @endif
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="block w-full rounded-lg border p-2">
                <small class="text-slate-500">Opsional. Jika kosong, halaman Home menggunakan ilustrasi TJSL INKA. JPG, PNG, atau WEBP; maksimal 10 MB.</small>
                @error('image') <small class="block text-red-600">{{ $message }}</small> @enderror
            </div>

            <button class="w-full rounded-lg bg-action-primary px-5 py-3 font-semibold text-white hover:bg-action-primary-hover">Simpan Berita</button>
        </form>
    </div>
</x-layouts.admin>
