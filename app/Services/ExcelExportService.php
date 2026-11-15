<?php

namespace App\Services;

use ZipArchive;

class ExcelExportService
{
    /**
     * Genera un archivo Microsoft Excel (.xlsx) nativo (OpenXML) con múltiples hojas,
     * estilos corporativos, anchos de columna ajustados y tipos numéricos/monetarios reales.
     *
     * @param  array<int, array{
     *     title: string,
     *     subtitle?: string,
     *     meta?: array<string, string>,
     *     headers: array<int, string>,
     *     widths: array<int, int|float>,
     *     rows: array<int, array<int, mixed>>,
     *     currency_cols?: array<int, int>,
     *     summary_rows?: array<int, array<int, mixed>>
     * }>  $sheets
     */
    public function generateXlsx(array $sheets): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'farmanet_xlsx_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->buildRootRels());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        foreach ($sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $zip->addFromString(
                "xl/worksheets/sheet{$sheetNumber}.xml",
                $this->buildWorksheet($sheet)
            );
        }

        $zip->close();

        return $tempFile;
    }

    private function buildContentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function buildRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(array $sheets): string
    {
        $sheetsXml = '';
        foreach ($sheets as $index => $sheet) {
            $sheetId = $index + 1;
            $name = $this->escapeXml(mb_substr($sheet['title'], 0, 31));
            $sheetsXml .= '<sheet name="' . $name . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRels(int $sheetCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $stylesId = $sheetCount + 1;
        $rels .= '<Relationship Id="rId' . $stylesId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    /**
     * Estilos (cellXfs):
     * 0: Normal
     * 1: Título Principal (14pt Bold Azul Clínico #1E40AF)
     * 2: Subtítulo / Metadatos (10pt Gris Oscuro #475569)
     * 3: Encabezado de Tabla (10pt Bold Blanco sobre Azul #1E40AF con bordes)
     * 4: Celda de Datos Texto/General con bordes finos
     * 5: Celda de Datos Moneda CLP ($#,##0) con bordes finos
     * 6: Fila Resumen / Total Texto (10pt Bold con fondo suave #EFF6FF y bordes)
     * 7: Fila Resumen / Total Moneda CLP ($#,##0 Bold con fondo suave #EFF6FF y bordes)
     */
    private function buildStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1">'
            . '<numFmt numFmtId="164" formatCode="$#,##0;($#,##0);&quot;$0&quot;"/>'
            . '</numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="10"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF1E40AF"/><name val="Calibri"/></font>'
            . '<font><sz val="10"/><color rgb="FF475569"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E40AF"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function buildWorksheet(array $sheet): string
    {
        $widths = $sheet['widths'] ?? [];
        $colsXml = '';
        if (! empty($widths)) {
            $colsXml .= '<cols>';
            foreach ($widths as $idx => $width) {
                $colNum = $idx + 1;
                $colsXml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $width . '" customWidth="1"/>';
            }
            $colsXml .= '</cols>';
        }

        $currencyCols = $sheet['currency_cols'] ?? [];
        $rowsXml = '';
        $rowNumber = 1;

        // Fila 1: Título de la hoja
        $headerTitle = $sheet['header_title'] ?? ('FarmaNet Pro — ' . $sheet['title']);
        $rowsXml .= '<row r="' . $rowNumber . '" ht="24" customHeight="1">'
            . $this->buildCell(0, $rowNumber, $headerTitle, 1)
            . '</row>';
        $rowNumber++;

        // Fila 2: Subtítulo / Fecha de emisión
        if (! empty($sheet['subtitle'])) {
            $rowsXml .= '<row r="' . $rowNumber . '" ht="18" customHeight="1">'
                . $this->buildCell(0, $rowNumber, $sheet['subtitle'], 2)
                . '</row>';
            $rowNumber++;
        }

        // Fila en blanco de separación
        $rowsXml .= '<row r="' . $rowNumber . '"/>';
        $rowNumber++;

        // Fila de Encabezados de Tabla
        $headers = $sheet['headers'] ?? [];
        $headerCells = '';
        foreach ($headers as $colIdx => $label) {
            $headerCells .= $this->buildCell($colIdx, $rowNumber, $label, 3);
        }
        $rowsXml .= '<row r="' . $rowNumber . '" ht="22" customHeight="1">' . $headerCells . '</row>';
        $rowNumber++;

        // Filas de Datos
        foreach ($sheet['rows'] ?? [] as $row) {
            $cellsXml = '';
            foreach ($row as $colIdx => $val) {
                $isCurrency = in_array($colIdx, $currencyCols, true);
                $style = ($isCurrency && is_numeric($val)) ? 5 : 4;
                $cellsXml .= $this->buildCell($colIdx, $rowNumber, $val, $style, $isCurrency);
            }
            $rowsXml .= '<row r="' . $rowNumber . '" ht="19" customHeight="1">' . $cellsXml . '</row>';
            $rowNumber++;
        }

        // Filas de Resumen / Totales al final
        if (! empty($sheet['summary_rows'])) {
            foreach ($sheet['summary_rows'] as $sRow) {
                $cellsXml = '';
                foreach ($sRow as $colIdx => $val) {
                    $isCurrency = in_array($colIdx, $currencyCols, true);
                    $style = ($isCurrency && is_numeric($val)) ? 7 : 6;
                    $cellsXml .= $this->buildCell($colIdx, $rowNumber, $val, $style, $isCurrency);
                }
                $rowsXml .= '<row r="' . $rowNumber . '" ht="21" customHeight="1">' . $cellsXml . '</row>';
                $rowNumber++;
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $colsXml
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';
    }

    private function buildCell(int $colIdx, int $rowNumber, mixed $value, int $styleIndex, bool $forceNumeric = false): string
    {
        $cellRef = $this->columnLetter($colIdx) . $rowNumber;

        if ($value === null || $value === '') {
            return '<c r="' . $cellRef . '" s="' . $styleIndex . '" t="inlineStr"><is><t></t></is></c>';
        }

        if ((is_int($value) || is_float($value)) || ($forceNumeric && is_numeric($value))) {
            return '<c r="' . $cellRef . '" s="' . $styleIndex . '"><v>' . ($value + 0) . '</v></c>';
        }

        return '<c r="' . $cellRef . '" s="' . $styleIndex . '" t="inlineStr"><is><t>'
            . $this->escapeXml((string) $value)
            . '</t></is></c>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - $mod, 26);
        }

        return $letter;
    }

    private function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
