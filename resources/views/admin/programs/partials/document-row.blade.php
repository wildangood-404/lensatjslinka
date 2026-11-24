<div class="program-doc-row">
    <strong>{{ $label }}</strong>
    <span class="program-readonly">{{ $document->nama_file_asli }}</span>
    <div class="program-file-actions">
        <a href="{{ route($routePrefix.'.program-documents.view', $document) }}" target="_blank" rel="noopener">Lihat</a>
        <a href="{{ route($routePrefix.'.program-documents.download', $document) }}">Unduh</a>
        @if($editable)
            <button class="program-delete" type="submit" form="delete-document-{{ $document->id }}">Hapus</button>
        @endif
    </div>
</div>
