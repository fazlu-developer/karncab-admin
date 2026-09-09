<?php

namespace App\Support;

use Illuminate\Http\Response;
use ZipArchive;

final class ReportExporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function download(string $format, string $filename, string $title, array $rows): Response
    {
        $headers = $rows === [] ? ['message'] : array_keys($rows[0]);
        $matrix = $rows === [] ? [['No rows in scope']] : array_map(fn (array $row) => array_map(
            fn ($value) => is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value),
            $row
        ), $rows);

        return match ($format) {
            'xlsx' => self::xlsx($filename, $title, $headers, $matrix),
            'pdf' => self::pdf($filename, $title, $headers, $matrix),
            default => self::csv($filename, $headers, $matrix),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $matrix
     */
    private static function csv(string $filename, array $headers, array $matrix): Response
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($matrix as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $matrix
     */
    private static function xlsx(string $filename, string $title, array $headers, array $matrix): Response
    {
        $sheet = self::sheetXml($title, $headers, $matrix);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        @unlink($tmp);
        $zip = new ZipArchive;
        abort_unless($zip->open($tmp, ZipArchive::CREATE) === true, 500, 'Could not build Excel export');
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $binary = (string) file_get_contents($tmp);
        @unlink($tmp);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $matrix
     */
    private static function sheetXml(string $title, array $headers, array $matrix): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $xml .= self::xlsxRow(1, array_merge([$title], array_fill(0, max(0, count($headers) - 1), '')));
        $xml .= self::xlsxRow(2, $headers);
        $r = 3;
        foreach ($matrix as $row) {
            $xml .= self::xlsxRow($r, $row);
            $r++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<string>  $values
     */
    private static function xlsxRow(int $row, array $values): string
    {
        $cells = '';
        foreach (array_values($values) as $i => $value) {
            $ref = self::col($i).$row;
            $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.self::xml((string) $value).'</t></is></c>';
        }

        return '<row r="'.$row.'">'.$cells.'</row>';
    }

    private static function col(int $index): string
    {
        $name = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $name = chr(65 + ($n % 26)).$name;
            $n = intdiv($n, 26);
        }

        return $name;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $matrix
     */
    private static function pdf(string $filename, string $title, array $headers, array $matrix): Response
    {
        $lines = [$title, implode(' | ', $headers)];
        foreach (array_slice($matrix, 0, 80) as $row) {
            $lines[] = implode(' | ', $row);
        }
        $content = '';
        $y = 800;
        foreach ($lines as $line) {
            $text = self::pdfText(mb_substr($line, 0, 110));
            $content .= "BT /F1 9 Tf 36 {$y} Td ({$text}) Tj ET\n";
            $y -= 14;
            if ($y < 40) {
                break;
            }
        }
        $stream = "<< /Length ".strlen($content)." >>\nstream\n{$content}endstream";
        $pdf = "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
            ."4 0 obj {$stream} endobj\n"
            ."5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
            ."xref\n0 6\n0000000000 65535 f \n"
            ."trailer << /Size 6 /Root 1 0 R >>\nstartxref\n0\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function pdfText(string $value): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
