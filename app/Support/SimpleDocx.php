<?php

namespace App\Support;

/**
 * One-page Word file for a joining letter. Text runs may be bold. Images are JPEG.
 */
final class SimpleDocx
{
    /** @var list<string> */
    private array $blocks = [];

    /** @var list<array{name: string, bytes: string}> */
    private array $images = [];

    /**
     * @param  list<array{0: string, 1: bool}>  $runs
     */
    public function paragraph(array $runs, int $sizeHalfPoints = 22, int $after = 160): void
    {
        $body = '';
        foreach ($runs as [$text, $bold]) {
            $text = (string) $text;
            if ($text === '') {
                continue;
            }
            $props = '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="'.$sizeHalfPoints.'"/><w:szCs w:val="'.$sizeHalfPoints.'"/>';
            if ($bold) {
                $props .= '<w:b/>';
            }
            $body .= '<w:r><w:rPr>'.$props.'</w:rPr><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r>';
        }
        $this->blocks[] = '<w:p><w:pPr><w:spacing w:after="'.$after.'" w:line="276" w:lineRule="auto"/></w:pPr>'.$body.'</w:p>';
    }

    public function image(string $jpeg, int $widthPx, int $heightPx): void
    {
        $n = count($this->images) + 1;
        $name = 'image'.$n.'.jpeg';
        $this->images[] = ['name' => $name, 'bytes' => $jpeg];
        $cx = max(1, $widthPx) * 9525;
        $cy = max(1, $heightPx) * 9525;
        $id = $n;
        $this->blocks[] = '<w:p><w:r><w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:docPr id="'.$id.'" name="Picture'.$id.'"/>'
            .'<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr><pic:cNvPr id="'.$id.'" name="Picture'.$id.'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="rIdImg'.$id.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    public function render(): string
    {
        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($this->images as $i => $image) {
            $rels .= '<Relationship Id="rIdImg'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$image['name'].'"/>';
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<w:body>'.implode('', $this->blocks)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="851" w:right="851" w:bottom="851" w:left="851" w:header="454" w:footer="454"/></w:sectPr>'
            .'</w:body></w:document>';

        $zip = new \ZipArchive();
        $path = tempnam(sys_get_temp_dir(), 'krdocx');
        if ($path === false || $zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Word file could not be created.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>');
        foreach ($this->images as $image) {
            $zip->addFromString('word/media/'.$image['name'], $image['bytes']);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);
        if (! is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Word file could not be created.');
        }

        return $bytes;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
