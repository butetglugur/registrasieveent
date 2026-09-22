<?php

declare(strict_types=1);

namespace App\Core;

use ZipArchive;

/**
 * Penulis file Excel (.xlsx) minimalis tanpa library eksternal.
 * Semua sel ditulis sebagai teks (inline string) sehingga nomor WA
 * tidak berubah format dan tidak ada risiko formula injection.
 */
final class XlsxWriter
{
    /** @var resource */
    private $sheet;
    private string $sheetPath;
    private int $row = 0;
    private int $cols = 0;
    /** @var int[] */
    private array $widths = [];

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public function __construct()
    {
        $this->sheetPath = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        $fh = fopen($this->sheetPath, 'w');
        if ($fh === false) {
            throw new \RuntimeException('Tidak dapat membuat file sementara');
        }
        $this->sheet = $fh;
    }

    public function addRow(array $cells, bool $header = false): void
    {
        $this->row++;
        $this->cols = max($this->cols, count($cells));
        $xml = '<row r="' . $this->row . '">';
        $i = 0;
        foreach ($cells as $cell) {
            $ref = self::col($i) . $this->row;
            $text = self::clean((string) ($cell ?? ''));
            $len = min(60, max(6, mb_strlen($text) + 2));
            $this->widths[$i] = max($this->widths[$i] ?? 0, $len);
            $xml .= '<c r="' . $ref . '" t="inlineStr"' . ($header ? ' s="1"' : '') . '><is><t xml:space="preserve">'
                . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
            $i++;
        }
        fwrite($this->sheet, $xml . '</row>');
    }

    /** Hasilkan path file .xlsx sementara (hapus setelah dikirim). */
    public function finish(): string
    {
        fclose($this->sheet);
        $cols = '';
        foreach ($this->widths as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $out = (string) tempnam(sys_get_temp_dir(), 'xlsxout');
        $zip = new ZipArchive();
        if ($zip->open($out, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file xlsx');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Peserta" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF7C3AED"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>');

        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>';
        $tail = '</sheetData>'
            . ($this->row > 0 && $this->cols > 0 ? '<autoFilter ref="A1:' . self::col($this->cols - 1) . $this->row . '"/>' : '')
            . '</worksheet>';
        $full = (string) tempnam(sys_get_temp_dir(), 'xlsxsheet');
        $fh = fopen($full, 'w');
        fwrite($fh, $head);
        $src = fopen($this->sheetPath, 'r');
        stream_copy_to_stream($src, $fh);
        fclose($src);
        fwrite($fh, $tail);
        fclose($fh);
        $zip->addFile($full, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($full);
        @unlink($this->sheetPath);
        return $out;
    }

    private static function col(int $index): string
    {
        $s = '';
        $index++;
        while ($index > 0) {
            $m = ($index - 1) % 26;
            $s = chr(65 + $m) . $s;
            $index = intdiv($index - $m - 1, 26);
        }
        return $s;
    }

    /** Buang karakter yang tidak valid di XML. */
    private static function clean(string $s): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
    }
}
