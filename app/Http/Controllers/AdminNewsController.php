<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdminNewsController extends Controller
{
    public function index(): View
    {
        return view('admin.news.index', [
            'newsItems' => NewsItem::query()->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.news.form', ['newsItem' => new NewsItem]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request, true);
        $imagePath = $request->file('image')->store('news', 'public');

        try {
            NewsItem::create([
                ...$data,
                'image_path' => $imagePath,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);
            throw $exception;
        }

        return redirect()
            ->route('admin.news.index')
            ->with('success', 'Berita TJSL INKA berhasil ditambahkan.');
    }

    public function edit(NewsItem $berita): View
    {
        return view('admin.news.form', ['newsItem' => $berita]);
    }

    public function update(Request $request, NewsItem $berita): RedirectResponse
    {
        $data = $this->validatedData($request, false);
        $newImagePath = $request->hasFile('image')
            ? $request->file('image')->store('news', 'public')
            : null;
        $oldImagePath = $berita->image_path;

        try {
            $berita->update([
                ...$data,
                ...($newImagePath ? ['image_path' => $newImagePath] : []),
            ]);
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }

        if ($newImagePath) {
            $this->deleteUploadedImage($oldImagePath);
        }

        return redirect()
            ->route('admin.news.index')
            ->with('success', 'Berita TJSL INKA berhasil diperbarui.');
    }

    public function destroy(NewsItem $berita): RedirectResponse
    {
        $imagePath = $berita->image_path;
        $berita->delete();
        $this->deleteUploadedImage($imagePath);

        return back()->with('success', 'Berita TJSL INKA berhasil dihapus.');
    }

    private function validatedData(Request $request, bool $imageRequired): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'published_date' => ['required', 'string', 'max:100'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    private function deleteUploadedImage(string $path): void
    {
        if (! str_starts_with($path, 'images/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
