        <div class="receivable-toolbar">
            <div>
                <h1 class="pumk-page-title">Kartu Piutang Mitra</h1>
                <p class="pumk-page-subtitle">Jadwal angsuran lengkap {{ $mitra->nama_mitra }}</p>
            </div>
            <div class="receivable-toolbar-actions">
                <a href="{{ route($listRouteName) }}" class="pumk-secondary-button">Kembali</a>
                @if($pinjaman)
                    <a href="{{ route($mitraRoutePrefix.'.kartu.excel', [$mitra, $pinjaman, 'tahun' => $kartu['tahun_terpilih']]) }}" class="pumk-secondary-button">Unduh Excel</a>
                    <a href="{{ route($mitraRoutePrefix.'.kartu.pdf', [$mitra, $pinjaman, 'tahun' => $kartu['tahun_terpilih']]) }}" class="pumk-secondary-button">Unduh PDF</a>
                @endif
                <a href="{{ route($editRouteName, [$mitra, 'pinjaman' => $pinjaman?->id]) }}" class="pumk-primary-button">{{ $loanIsActive ? 'Edit Data dan Dokumen' : 'Edit Arsip dan Dokumen' }}</a>
                @if($loanIsActive)
                    <button type="button" class="danger-button" data-open-paid-dialog>Tandai Lunas</button>
                @endif
            </div>
        </div>
