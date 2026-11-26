<?php

namespace App\Services\Pumk;

use App\Models\PumkSektorUsaha;
use Illuminate\Http\Request;

class PumkMitraInput
{
    public function validateMitra(Request $request): array
    {
        $contractDocumentMaxMb = (int) ceil((int) config('pumk.contract_document_max_kb') / 1024);

        return $request->validate([
            'nama_mitra' => ['required', 'string', 'max:255'],
            'jenis_usaha' => ['nullable', 'string', 'max:255'],
            'sektor_usaha_id' => ['nullable', 'integer', 'exists:pumk_sektor_usaha,id'],
            'sektor_usaha' => ['nullable', 'string', 'max:255'],
            'kabupaten_kota' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:3000'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'no_ktp' => ['nullable', 'string', 'max:64'],
            'nik' => ['nullable', 'digits:16'],
            'jenis_kelamin' => ['nullable', 'in:Laki-laki,Perempuan'],
            'tempat_lahir' => ['nullable', 'string', 'max:150'],
            'tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'no_telepon' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'no_rekening' => ['nullable', 'string', 'max:64'],
            'pasfoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'dokumen_pendukung' => ['nullable', 'array', 'max:20'],
            'dokumen_pendukung.*' => ['file', 'max:10240'],
            'financial_snapshot' => ['nullable', 'boolean'],
            'alamat_domisili_sama_ktp' => ['nullable', 'boolean'],
            'alamat_domisili_berbeda_ktp' => ['nullable', 'boolean'],
            'alamat_usaha_sama_ktp' => ['nullable', 'boolean'],
            'spj_awal' => ['nullable', 'string', 'max:255'],
            'reschedule_ke1' => ['nullable', 'string', 'max:255'],
            'reschedule_ke2' => ['nullable', 'string', 'max:255'],
            'reschedule_ke3' => ['nullable', 'string', 'max:255'],
            'reschedule_ke4' => ['nullable', 'string', 'max:255'],
            'jenis_jaminan' => ['nullable', 'string', 'max:3000'],
            'jaminan_no_pol' => ['nullable', 'string', 'max:255'],
            'jaminan_no_bpkb' => ['nullable', 'string', 'max:255'],
            'jaminan_merk' => ['nullable', 'string', 'max:255'],
            'jaminan_type' => ['nullable', 'string', 'max:255'],
            'jaminan_tahun_kendaraan' => ['nullable', 'string', 'max:255'],
            'jaminan_no_sertifikat' => ['nullable', 'string', 'max:255'],
            'jaminan_luas' => ['nullable', 'string', 'max:255'],
            'jaminan_atas_nama' => ['nullable', 'string', 'max:255'],
            'jaminan_alamat' => ['nullable', 'string', 'max:3000'],
            'tanggal_pencairan' => ['nullable', 'date'],
            'tahun_pencairan' => ['nullable', 'integer', 'digits:4', 'between:1900,2200'],
            'mulai_angsuran' => ['nullable', 'date'],
            'selesai_angsuran' => ['nullable', 'date', 'after_or_equal:mulai_angsuran'],
            'pinjaman_pokok' => ['nullable', 'numeric', 'min:0'],
            'persen_bunga' => ['nullable', 'numeric', 'min:0'],
            'pinjaman_bunga' => ['nullable', 'numeric', 'min:0'],
            'nilai_angsuran_bulanan' => ['nullable', 'numeric', 'min:0'],
            'kolektibilitas' => ['nullable', 'in:lancar,kurang_lancar,diragukan,macet'],
            'sisa_pokok' => ['nullable', 'numeric'],
            'sisa_bunga' => ['nullable', 'numeric'],
            'total_sisa' => ['nullable', 'numeric'],
            'denda' => ['nullable', 'numeric', 'min:0'],
            'status_pinjaman' => ['nullable', 'in:aktif,lunas,reschedule,bermasalah'],
            ...$this->addressRules(),
            'dokumen_spj_awal' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_1' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_2' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_3' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
            'dokumen_reschedule_4' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('pumk.contract_document_max_kb')],
        ], [
            'dokumen_spj_awal.max' => "Ukuran Dokumen SPJ Awal maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_1.max' => "Ukuran Dokumen Reschedule Ke-1 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_2.max' => "Ukuran Dokumen Reschedule Ke-2 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_3.max' => "Ukuran Dokumen Reschedule Ke-3 maksimal {$contractDocumentMaxMb} MB.",
            'dokumen_reschedule_4.max' => "Ukuran Dokumen Reschedule Ke-4 maksimal {$contractDocumentMaxMb} MB.",
        ]);
    }

    public function mitraPayload(array $data): array
    {
        $sektor = filled($data['sektor_usaha_id'] ?? null)
            ? PumkSektorUsaha::find($data['sektor_usaha_id'])
            : PumkSektorUsaha::query()->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim((string) ($data['sektor_usaha'] ?? '')))])
                ->first();
        $ktp = $this->addressPayload($data, 'ktp', [
            'provinsi' => 'provinsi',
            'kabupaten_kota' => 'kabupaten_kota',
            'lengkap' => 'alamat',
        ]);
        $domicileSame = array_key_exists('alamat_domisili_berbeda_ktp', $data)
            ? ! $this->booleanValue($data['alamat_domisili_berbeda_ktp'])
            : $this->booleanValue($data['alamat_domisili_sama_ktp'] ?? true);
        $businessSame = $this->booleanValue($data['alamat_usaha_sama_ktp'] ?? true);
        $domicile = $domicileSame ? $ktp : $this->addressPayload($data, 'domisili');
        $business = $businessSame ? $ktp : $this->addressPayload($data, 'usaha');
        $kabupatenKota = filled($ktp['kabupaten_kota']) ? trim((string) $ktp['kabupaten_kota']) : null;
        $provinsi = filled($ktp['provinsi']) ? trim((string) $ktp['provinsi']) : null;
        $wilayahLabel = collect([$kabupatenKota, $provinsi])
            ->filter()
            ->implode(', ');
        $nik = $data['nik'] ?? $data['no_ktp'] ?? null;

        return [
            'nama_mitra' => trim($data['nama_mitra']),
            'jenis_usaha' => $data['jenis_usaha'] ?? null,
            'sektor_usaha_id' => $sektor?->id,
            'sektor_sumber' => $sektor?->nama ?? ($data['sektor_usaha'] ?? null),
            'wilayah_id' => null,
            'wilayah_sumber' => $wilayahLabel !== '' ? $wilayahLabel : null,
            'kabupaten_kota' => $kabupatenKota,
            'provinsi' => $provinsi,
            'alamat' => $ktp['lengkap'],
            'nama_pemilik' => $data['nama_pemilik'] ?? null,
            'nik' => $nik,
            'no_ktp_encrypted' => $nik,
            'no_telepon_encrypted' => $data['no_telepon'] ?? null,
            'no_rekening_encrypted' => $data['no_rekening'] ?? null,
            'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            'tempat_lahir' => $data['tempat_lahir'] ?? null,
            'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'email' => $data['email'] ?? null,
            'alamat_domisili_sama_ktp' => $domicileSame,
            'alamat_usaha_sama_ktp' => $businessSame,
            ...$this->prefixedAddress('ktp', $ktp),
            ...$this->prefixedAddress('domisili', $domicile),
            ...$this->prefixedAddress('usaha', $business),
        ];
    }

    public function pinjamanPayload(array $data): array
    {
        return [
            'spj_awal' => $data['spj_awal'] ?? null,
            'reschedule_ke1' => $data['reschedule_ke1'] ?? null,
            'reschedule_ke2' => $data['reschedule_ke2'] ?? null,
            'reschedule_ke3' => $data['reschedule_ke3'] ?? null,
            'reschedule_ke4' => $data['reschedule_ke4'] ?? null,
            'jenis_jaminan' => $data['jenis_jaminan'] ?? null,
            'jaminan_no_pol' => $data['jaminan_no_pol'] ?? null,
            'jaminan_no_bpkb' => $data['jaminan_no_bpkb'] ?? null,
            'jaminan_merk' => $data['jaminan_merk'] ?? null,
            'jaminan_type' => $data['jaminan_type'] ?? null,
            'jaminan_tahun_kendaraan' => $data['jaminan_tahun_kendaraan'] ?? null,
            'jaminan_no_sertifikat' => $data['jaminan_no_sertifikat'] ?? null,
            'jaminan_luas' => $data['jaminan_luas'] ?? null,
            'jaminan_atas_nama' => $data['jaminan_atas_nama'] ?? null,
            'jaminan_alamat' => $data['jaminan_alamat'] ?? null,
            'tanggal_pencairan' => $data['tanggal_pencairan'] ?? null,
            'tahun_pencairan' => $data['tahun_pencairan'] ?? null,
            'mulai_angsuran' => $data['mulai_angsuran'] ?? null,
            'selesai_angsuran' => $data['selesai_angsuran'] ?? null,
            'pinjaman_pokok' => $data['pinjaman_pokok'] ?? null,
            'persen_bunga' => $data['persen_bunga'] ?? null,
            'pinjaman_bunga' => $data['pinjaman_bunga'] ?? null,
            'nilai_angsuran_bulanan' => $data['nilai_angsuran_bulanan'] ?? null,
            'kolektibilitas' => $data['kolektibilitas'] ?? null,
            'sisa_pokok' => $data['sisa_pokok'] ?? null,
            'sisa_bunga' => $data['sisa_bunga'] ?? null,
            'total_sisa' => $data['total_sisa'] ?? null,
            'denda' => $data['denda'] ?? '0.00',
            'status_pinjaman' => $data['status_pinjaman'] ?? 'aktif',
        ];
    }

    /** @return array<string, array<int, string>> */
    private function addressRules(): array
    {
        $rules = [];
        foreach (['ktp', 'domisili', 'usaha'] as $address) {
            foreach (['provinsi', 'kabupaten_kota', 'kecamatan', 'desa', 'rt', 'rw'] as $field) {
                $rules["alamat_{$address}_{$field}"] = ['nullable', 'string', 'max:255'];
            }
            $rules["alamat_{$address}_lengkap"] = ['nullable', 'string', 'max:3000'];
            $rules["alamat_{$address}_share_lok"] = [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filter_var($value, FILTER_VALIDATE_URL) === false
                        && ! preg_match('/^-?\d{1,2}(?:\.\d+)?\s*,\s*-?\d{1,3}(?:\.\d+)?$/', trim((string) $value))) {
                        $fail('Masukkan URL Google Maps atau koordinat latitude, longitude.');
                    }
                },
            ];
        }

        return $rules;
    }

    /** @param array<string, mixed> $data
     * @param  array<string, string>  $fallbacks
     * @return array<string, ?string>
     */
    private function addressPayload(array $data, string $address, array $fallbacks = []): array
    {
        $payload = [];
        foreach (['provinsi', 'kabupaten_kota', 'kecamatan', 'desa', 'rt', 'rw', 'lengkap', 'share_lok'] as $field) {
            $key = "alamat_{$address}_{$field}";
            $fallbackKey = $fallbacks[$field] ?? null;
            $value = array_key_exists($key, $data) && $data[$key] !== null
                ? $data[$key]
                : ($fallbackKey !== null ? ($data[$fallbackKey] ?? null) : null);
            $payload[$field] = filled($value) ? trim((string) $value) : null;
        }

        return $payload;
    }

    /** @param array<string, ?string> $address
     * @return array<string, ?string>
     */
    private function prefixedAddress(string $prefix, array $address): array
    {
        $payload = [];
        foreach ($address as $field => $value) {
            $payload["alamat_{$prefix}_{$field}"] = $value;
        }

        return $payload;
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
