<?php

namespace App\Http\Controllers;

use App\Services\Pumk\PumkUmkmBinaanImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Phar;
use PharData;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkUmkmBinaanImportController extends Controller
{
    private const HEADERS = [
        'UMKM Binaan',
        'Alamat',
        'Wilayah',
        'Pemilik',
        'Kontak',
        'Sektor',
        'Pencairan',
        'Tahun',
        'Kolektibilitas',
        'Sisa Pinjaman (Pokok & Bunga)',
        'Sisa Pinjaman Pokok',
        'Sisa Pinjaman Bunga',
        'Denda',
        'Status',
    ];

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $basePath = tempnam(sys_get_temp_dir(), 'umkm-binaan-template-');
            if ($basePath === false) {
                throw new RuntimeException('Template Excel tidak dapat dibuat.');
            }
            if (! unlink($basePath)) {
                throw new RuntimeException('File sementara template Excel tidak dapat disiapkan.');
            }
            $archivePath = $basePath.'.zip';

            try {
                $archive = new PharData($archivePath, 0, null, Phar::ZIP);
                $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
                $archive->addFromString('_rels/.rels', $this->rootRelationshipsXml());
                $archive->addFromString('xl/workbook.xml', $this->workbookXml());
                $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
                $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml());
                unset($archive);

                $stream = fopen($archivePath, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('Template Excel tidak dapat dibaca.');
                }
                fpassthru($stream);
                fclose($stream);
            } finally {
                if (is_file($archivePath)) {
                    unlink($archivePath);
                }
            }
        }, 'template-umkm-binaan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function import(Request $request, PumkUmkmBinaanImportService $importer): RedirectResponse
    {
        $sourceCategory = $request->routeIs('pumk-admin.umkm-binaan-bri.*') ? 'bri' : 'reguler';
        $data = $request->validate([
            'import_file' => ['required', 'file', 'max:10240'],
        ]);
        $result = $importer->import($data['import_file'], $sourceCategory);

        return redirect()->route($sourceCategory === 'bri' ? 'pumk-admin.umkm-binaan-bri.create' : 'pumk-admin.mitra.create')
            ->with('import_result', $result);
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="UMKM Binaan" sheetId="1" r:id="rId1"/></sheets>'
            .'<calcPr calcMode="auto"/>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function worksheetXml(): string
    {
        $cells = '';
        foreach (self::HEADERS as $index => $header) {
            $column = $this->columnName($index + 1);
            $cells .= '<c r="'.$column.'1" t="inlineStr"><is><t>'
                .htmlspecialchars($header, ENT_QUOTES | ENT_XML1, 'UTF-8')
                .'</t></is></c>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData><row r="1">'.$cells.'</row></sheetData>'
            .'</worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
