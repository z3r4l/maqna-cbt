<?php

namespace App\Services;

use Phar;
use PharData;

final class XlsxFallbackWriter
{
    public function write(array $headers, array $rows): string
    {
        $values = []; $lookup = []; $sheet = '';
        $all = array_merge([$headers], $rows);
        foreach ($all as $rowIndex => $cells) {
            $sheet .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($cells as $column => $cell) {
                $safe = (string) $cell;
                if (preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $safe)) $safe = "'" . $safe;
                if (! isset($lookup[$safe])) {
                    $lookup[$safe] = count($values);
                    $values[] = $safe;
                }
                $letter = '';
                $index = $column + 1;
                while ($index > 0) {
                    $index--;
                    $letter = chr(65 + $index % 26) . $letter;
                    $index = intdiv($index, 26);
                }
                $sheet .= '<c r="' . $letter . ($rowIndex + 1) . '" t="s"><v>' . $lookup[$safe] . '</v></c>';
            }
            $sheet .= '</row>';
        }
        $escape = static fn ($value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lastColumn = '';
        $lastIndex = count($headers);
        while ($lastIndex > 0) {
            $lastIndex--;
            $lastColumn = chr(65 + $lastIndex % 26) . $lastColumn;
            $lastIndex = intdiv($lastIndex, 26);
        }
        $shared = '';
        foreach ($values as $value) $shared .= '<si><t xml:space="preserve">' . $escape($value) . '</t></si>';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hasil ujian" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $lastColumn . count($all) . '"/><sheetData>' . $sheet . '</sheetData></worksheet>',
            'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . array_sum(array_map('count', $all)) . '" uniqueCount="' . count($values) . '">' . $shared . '</sst>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        ];
        $base = tempnam(WRITEPATH . 'cache', 'maqna-xlsx-');
        if ($base === false) throw new \RuntimeException('Berkas sementara tidak tersedia.');
        unlink($base);
        $path = $base . '.zip';
        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            foreach ($files as $name => $content) $archive->addFromString($name, $content);
            unset($archive);
            $body = file_get_contents($path);
            if ($body === false) throw new \RuntimeException('XLSX gagal dibaca.');
            return $body;
        } finally {
            if (is_file($path)) unlink($path);
        }
    }
}
