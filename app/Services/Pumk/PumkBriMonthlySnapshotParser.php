<?php

namespace App\Services\Pumk;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Str;

class PumkBriMonthlySnapshotParser
{
    private const HEADERS = [
        'kode_unit',
        'bri_unit',
        'nama_mitra_binaan',
        'alamat',
        'provinsi',
        'kabupaten_kota',
        'sektor_ekonomi_bumn',
        'jangka_waktu',
        'tanggal_realisasi',
        'tanggal_jatuh_tempo',
        'jumlah_realisasi_rp',
        'outstanding_rp',
        'kolektibilitas_bumn',
        'tanggal_lunas',
        'jenis_usaha',
        'accrued_interest',
        'angsuran_bunga_yang_diterima_giro',
    ];

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{records:list<array<string, mixed>>,total:string}
     */
    public function parse(array $rows): array
    {
        [$headerRow, $columns] = $this->findHeader($rows);
        $records = [];
        $total = '0.00';

        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            $rawName = $cells[$columns['nama_mitra_binaan']] ?? null;
            if (trim((string) $rawName) === '') {
                continue;
            }

            $name = $this->requiredText($rawName, 'Nama Mitra Binaan', $rowNumber, 255);
            $outstanding = $this->decimal(
                $cells[$columns['outstanding_rp']] ?? null,
                'Outstanding',
                $rowNumber,
                required: true,
            );
            [$collectibilityCode, $collectibilityLabel] = $this->collectibility(
                $cells[$columns['kolektibilitas_bumn']] ?? null,
                $rowNumber,
            );

            $province = $this->nullableText($cells[$columns['provinsi']] ?? null);
            $regency = $this->nullableText($cells[$columns['kabupaten_kota']] ?? null);
            $sector = $this->nullableText($cells[$columns['sektor_ekonomi_bumn']] ?? null);
            $businessType = $this->nullableText($cells[$columns['jenis_usaha']] ?? null);
            $loanAmount = $this->decimal(
                $cells[$columns['jumlah_realisasi_rp']] ?? null,
                'Jumlah Realisasi',
                $rowNumber,
            );
            $accruedInterest = $this->decimal(
                $cells[$columns['accrued_interest']] ?? null,
                'Accrued Interest',
                $rowNumber,
            );
            $giroInterest = $this->decimal(
                $cells[$columns['angsuran_bunga_yang_diterima_giro']] ?? null,
                'Angsuran Bunga yang Diterima Giro',
                $rowNumber,
            );
            $location = collect([$regency, $province])->filter()->implode(', ');
            $payload = [
                'kode_unit' => $this->nullableText($cells[$columns['kode_unit']] ?? null),
                'bri_unit' => $this->nullableText($cells[$columns['bri_unit']] ?? null),
                'nama_mitra_binaan' => $name,
                'alamat' => $this->nullableLongText($cells[$columns['alamat']] ?? null),
                'provinsi' => $province,
                'kabupaten_kota' => $regency,
                'sektor_ekonomi_bumn' => $sector,
                'jangka_waktu' => $this->nullableText($cells[$columns['jangka_waktu']] ?? null),
                'tanggal_realisasi' => $this->date($cells[$columns['tanggal_realisasi']] ?? null, 'Tanggal Realisasi', $rowNumber),
                'tanggal_jatuh_tempo' => $this->date($cells[$columns['tanggal_jatuh_tempo']] ?? null, 'Tanggal Jatuh Tempo', $rowNumber),
                'jumlah_realisasi' => $loanAmount,
                'outstanding' => $outstanding,
                'kolektibilitas_bumn' => $collectibilityLabel,
                'tanggal_lunas' => $this->date($cells[$columns['tanggal_lunas']] ?? null, 'Tanggal Lunas', $rowNumber),
                'jenis_usaha' => $businessType,
                'accrued_interest' => $accruedInterest,
                'angsuran_bunga_diterima_giro' => $giroInterest,
            ];

            $records[] = [
                'nama_mitra' => $name,
                'alamat' => $payload['alamat'],
                'wilayah' => $location !== '' ? $location : null,
                'kabupaten_kota' => $regency,
                'provinsi' => $province,
                'sektor_usaha' => $sector ?? $businessType,
                'sektor_ekonomi_bumn' => $sector,
                'pinjaman' => $loanAmount,
                'tenor_raw' => $payload['jangka_waktu'],
                'saldo_piutang' => $outstanding,
                'kolektibilitas_kode' => $collectibilityCode,
                'kolektibilitas_label' => $collectibilityLabel,
                'source_row' => $rowNumber,
                'no_urut_sumber' => null,
                'kode_unit' => $payload['kode_unit'],
                'bri_unit' => $payload['bri_unit'],
                'tanggal_pencairan_sumber' => $payload['tanggal_realisasi'],
                'tanggal_jatuh_tempo_sumber' => $payload['tanggal_jatuh_tempo'],
                'tanggal_lunas' => $payload['tanggal_lunas'],
                'jenis_usaha' => $businessType,
                'accrued_interest' => $accruedInterest,
                'angsuran_bunga_diterima_giro' => $giroInterest,
                'source_payload' => $payload,
            ];
            $total = bcadd($total, $outstanding, 2);
        }

        if ($records === []) {
            throw new InvalidArgumentException('File tidak memiliki baris data mitra setelah header.');
        }

        return ['records' => $records, 'total' => $total];
    }

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{int,array<string,string>}
     */
    private function findHeader(array $rows): array
    {
        $bestMissing = self::HEADERS;
        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber > 20) {
                break;
            }

            $columns = [];
            foreach ($cells as $column => $value) {
                $header = $this->normalizeHeader($value);
                if ($header !== '') {
                    $columns[$header] = $column;
                }
            }

            $missing = array_values(array_diff(self::HEADERS, array_keys($columns)));
            if ($missing === []) {
                return [$rowNumber, $columns];
            }
            if (count($missing) < count($bestMissing)) {
                $bestMissing = $missing;
            }
        }

        throw new InvalidArgumentException(
            'Header wajib 17 kolom tidak ditemukan pada 20 baris pertama. Kolom yang tidak dikenali: '.implode(', ', $bestMissing).'.',
        );
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = Str::of((string) $value)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ($header) {
            'jumlah_realisasi' => 'jumlah_realisasi_rp',
            'outstanding' => 'outstanding_rp',
            'angsuran_bunga_diterima_giro' => 'angsuran_bunga_yang_diterima_giro',
            'kolektabilitas_bumn' => 'kolektibilitas_bumn',
            default => $header,
        };
    }

    private function requiredText(mixed $value, string $field, int $row, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            throw new InvalidArgumentException("Baris {$row}: {$field} wajib diisi.");
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function nullableLongText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function decimal(mixed $value, string $field, int $row, bool $required = false): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            if ($required) {
                throw new InvalidArgumentException("Baris {$row}: {$field} wajib berupa angka.");
            }

            return null;
        }

        $normalized = str_ireplace(['rp', ' ', "\u{00A0}"], '', $normalized);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $parts = explode(',', $normalized);
            $normalized = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0].'.'.$parts[1]
                : implode('', $parts);
        } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Baris {$row}: {$field} harus berupa angka nol atau positif.");
        }

        [$integer] = explode('.', $normalized, 2);
        if (strlen(ltrim($integer, '0') ?: '0') > 16) {
            throw new InvalidArgumentException("Baris {$row}: {$field} melebihi batas database.");
        }

        return bcadd($normalized, '0', 2);
    }

    private function date(mixed $value, string $field, int $row): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', $raw);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        } elseif (is_numeric($raw) && (float) $raw >= 1 && (float) $raw <= 100000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable) {
                throw new InvalidArgumentException("Baris {$row}: {$field} tidak valid.");
            }
        }

        foreach (['!d/m/Y', '!d-m-Y', '!Y-m-d', '!m/d/Y', '!d/m/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $raw);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException("Baris {$row}: {$field} tidak valid.");
    }

    /** @return array{string,string} */
    private function collectibility(mixed $value, int $row): array
    {
        $normalized = Str::of((string) $value)
            ->trim()
            ->upper()
            ->replaceMatches('/[^A-Z]+/', ' ')
            ->trim()
            ->toString();

        return match (true) {
            $normalized === 'L', str_starts_with($normalized, 'LANCAR') => ['L', 'Lancar'],
            $normalized === 'KL', str_starts_with($normalized, 'KURANG LANCAR') => ['KL', 'Kurang Lancar'],
            $normalized === 'D', str_starts_with($normalized, 'DIRAGUKAN') => ['D', 'Diragukan'],
            $normalized === 'M', str_starts_with($normalized, 'MACET') => ['M', 'Macet'],
            default => throw new InvalidArgumentException("Baris {$row}: Kolektabilitas BUMN tidak dikenali: {$value}."),
        };
    }
}
