<?php

namespace App\Support;

/**
 * Small A4 PDF writer for one or more text pages, with optional JPEG images.
 */
final class SimplePdf
{
    private const PAGE_W = 595.0;

    private const PAGE_H = 842.0;

    /** @var list<string> */
    private array $pages = [];

    private string $stream = '';

    /** @var list<array{bytes: string, w: int, h: int, cs: string}> */
    private array $images = [];

    public function addPage(): void
    {
        if ($this->pages !== [] || $this->stream !== '') {
            $this->pages[] = $this->stream;
            $this->stream = '';
        }
    }

    public function fill(float $x, float $top, float $w, float $h, string $hex): void
    {
        [$r, $g, $b] = $this->rgb($hex);
        $y = self::PAGE_H - $top - $h;
        $this->stream .= sprintf("%.3F %.3F %.3F rg\n%.2F %.2F %.2F %.2F re\nf\n", $r, $g, $b, $x, $y, $w, $h);
    }

    public function text(float $x, float $top, float $size, string $text, string $hex = '111111', bool $bold = false): void
    {
        [$r, $g, $b] = $this->rgb($hex);
        $y = self::PAGE_H - $top - $size;
        $font = $bold ? 'F2' : 'F1';
        $this->stream .= sprintf("BT\n/%s %.2F Tf\n%.3F %.3F %.3F rg\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n", $font, $size, $r, $g, $b, $x, $y, $this->escape($text));
    }

    public function stroke(float $x, float $top, float $w, float $h, string $hex, float $width = 0.8): void
    {
        [$r, $g, $b] = $this->rgb($hex);
        $y = self::PAGE_H - $top - $h;
        $this->stream .= sprintf("%.3F %.3F %.3F RG\n%.2F w\n%.2F %.2F %.2F %.2F re\nS\n", $r, $g, $b, $width, $x, $y, $w, $h);
    }

    /**
     * Flowing paragraph. Each part is [text, bold]. Returns the next top position.
     *
     * @param  list<array{0: string, 1: bool}>  $parts
     */
    public function writeRich(float $x, float $top, float $size, float $width, array $parts, string $hex = '333333', float $gap = 6): float
    {
        $words = [];
        foreach ($parts as [$text, $bold]) {
            foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
                if ($word !== '') {
                    $words[] = [$word, $bold];
                }
            }
        }
        $line = [];
        $lineWidth = 0.0;
        $space = $size * 0.28;
        $y = $top;
        $flush = function () use (&$line, &$lineWidth, &$y, $x, $size, $hex, $gap, $space): void {
            $cursor = $x;
            foreach ($line as [$word, $bold]) {
                $this->text($cursor, $y, $size, $word, $hex, $bold);
                $cursor += $this->textWidth($word, $size, $bold) + $space;
            }
            $line = [];
            $lineWidth = 0;
            $y += $size + $gap;
        };
        foreach ($words as [$word, $bold]) {
            $wordWidth = $this->textWidth($word, $size, $bold);
            $stick = $line !== [] && preg_match('/^[,.;:]+$/', $word) === 1;
            if (! $stick && $line !== [] && $lineWidth + $space + $wordWidth > $width) {
                $flush();
                $stick = false;
            }
            if ($line !== [] && ! $stick) {
                $lineWidth += $space;
            }
            if ($stick) {
                $line[count($line) - 1][0] .= $word;
                $lineWidth += $wordWidth;
            } else {
                $line[] = [$word, $bold];
                $lineWidth += $wordWidth;
            }
        }
        if ($line !== []) {
            $flush();
        }

        return $y;
    }

    public function textWidth(string $text, float $size, bool $bold = false): float
    {
        $units = 0;
        $bytes = $this->latin($text);
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $units += $this->glyphWidth(ord($bytes[$i]), $bold);
        }

        return $units / 1000 * $size;
    }

    private function glyphWidth(int $code, bool $bold): int
    {
        $regular = [
            32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
            40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
            58 => 278, 59 => 278, 63 => 556, 64 => 1015,
            65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 500,
            75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
            85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611,
            97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556, 104 => 556, 105 => 222, 106 => 222,
            107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278,
            117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500,
        ];
        $heavy = [
            32 => 278, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 556,
            75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
            85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611,
            97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611, 104 => 611, 105 => 278, 106 => 278,
            107 => 556, 108 => 278, 109 => 889, 110 => 611, 111 => 611, 112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333,
            117 => 611, 118 => 556, 119 => 778, 120 => 556, 121 => 556, 122 => 500,
        ];
        if ($bold && isset($heavy[$code])) {
            return $heavy[$code];
        }

        return $regular[$code] ?? 500;
    }

    /**
     * @return list<string>
     */
    public function wrap(string $text, float $size, float $width): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }
        $max = max(8, (int) floor($width / ($size * 0.48)));
        $words = explode(' ', $text);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $next = $line === '' ? $word : $line.' '.$word;
            if (strlen($this->latin($next)) > $max && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $next;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return int image index
     */
    public function addJpeg(string $bytes): int
    {
        $info = @getimagesizefromstring($bytes);
        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        $cs = (($info['channels'] ?? 3) === 1) ? 'DeviceGray' : 'DeviceRGB';
        if ($w < 1 || $h < 1 || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
            throw new \InvalidArgumentException('Signature must be a JPEG image.');
        }
        $this->images[] = ['bytes' => $bytes, 'w' => $w, 'h' => $h, 'cs' => $cs];

        return count($this->images) - 1;
    }

    public function drawImage(int $index, float $x, float $top, float $w, float $h): void
    {
        $y = self::PAGE_H - $top - $h;
        $name = 'Im'.($index + 1);
        $this->stream .= sprintf("q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n", $w, $h, $x, $y, $name);
    }

    public function render(): string
    {
        $this->pages[] = $this->stream;
        $this->stream = '';
        $objects = [];
        $pageIds = [];
        $fontId = 3;
        $boldId = 4;
        $contentStart = 5;
        $imageStart = $contentStart + count($this->pages);
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$boldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $i => $content) {
            $contentId = $contentStart + $i;
            $pageId = 10 + $i;
            $pageIds[] = $pageId;
            $resources = '/Font << /F1 '.$fontId.' 0 R /F2 '.$boldId.' 0 R >>';
            if ($this->images !== []) {
                $xobj = '';
                foreach ($this->images as $n => $image) {
                    $xobj .= '/Im'.($n + 1).' '.($imageStart + $n).' 0 R ';
                }
                $resources .= ' /XObject << '.$xobj.'>>';
            }
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << '.$resources.' >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
        }
        foreach ($this->images as $n => $image) {
            $objects[$imageStart + $n] = '<< /Type /XObject /Subtype /Image /Width '.$image['w'].' /Height '.$image['h']
                .' /ColorSpace /'.$image['cs'].' /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($image['bytes'])." >>\nstream\n"
                .$image['bytes'].'endstream';
        }
        $kids = implode(' ', array_map(fn ($id) => $id.' 0 R', $pageIds));
        $objects[2] = '<< /Type /Pages /Count '.count($pageIds).' /Kids ['.$kids.'] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $max = max(array_keys($objects));
        for ($id = 1; $id <= $max; $id++) {
            $offsets[$id] = strlen($pdf);
            $body = $objects[$id] ?? '<< >>';
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".($max + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function escape(string $text): string
    {
        $latin = $this->latin($text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin);
    }

    private function latin(string $text): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) ?? '' : $converted;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [0.1, 0.1, 0.1];
        }

        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }
}
