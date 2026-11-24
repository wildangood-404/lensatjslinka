@php($stageDocuments = $documents->where('kategori_tahap', $stage))
@if($stageDocuments->isNotEmpty())
    <div class="mt-5 space-y-3">
        <h3 class="program-subsection-title">{{ $documentsTitle ?? 'Berkas Dokumen' }}</h3>
        @foreach($stageDocuments as $document)
            @php($extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)))
            <div class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    @if($document->document_type === 'foto_kegiatan' && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
                        <a href="{{ route($routePrefix.'.program-documents.view', $document) }}" target="_blank" rel="noopener">
                            <img src="{{ route($routePrefix.'.program-documents.view', $document) }}" alt="{{ $document->nama_file_asli }}" class="h-16 w-20 rounded object-cover">
                        </a>
                    @endif
                    <div class="min-w-0">
                        <strong class="block">{{ $document->nama_dokumen }}</strong>
                        <span class="block truncate text-sm text-slate-500">{{ $document->nama_file_asli }}</span>
                    </div>
                </div>
                <div class="program-file-actions shrink-0">
                    <a href="{{ route($routePrefix.'.program-documents.view', $document) }}" target="_blank" rel="noopener">Lihat</a>
                    <a href="{{ route($routePrefix.'.program-documents.download', $document) }}">Unduh</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
