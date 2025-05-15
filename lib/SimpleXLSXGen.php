<?php
/**
  * Class SimpleXLSXGen
 * Export data to Excel XLSX file
 *
 * @category   SimpleXLSXGen
 * @package    SimpleXLSXGen
 * @copyright  Copyright (c) 2018 SimpleXLSXGen
 * @author     by Sergey Shuchkin (SHUCHKIN.com)
 * @license    MIT
 */

class SimpleXLSXGen {

    public $curSheet;
    protected $defaultFont;
    protected $defaultFontSize;
    protected $sheets;
    protected $template;
    protected $SI, $SI_KEYS; // shared strings index

    public function __construct() {
        $this->curSheet = -1;
        $this->defaultFont = 'Calibri';
        $this->defaultFontSize = 11;
        $this->sheets = [];
        $this->SI = [];        // sharedStrings index
        $this->SI_KEYS = []; //  & keys
        $this->template = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
__SHEETSCT__
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<Application>SimpleXLSXGen</Application>
<TotalTime>0</TotalTime>
</Properties>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dcterms:created xsi:type="dcterms:W3CDTF">' . date( 'Y-m-d\TH:i:s.00\Z' ) . '</dcterms:created>
<dc:language>en-US</dc:language>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . date( 'Y-m-d\TH:i:s.00\Z' ) . '</dcterms:modified>
<cp:revision>1</cp:revision>
</cp:coreProperties>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
__SHEETS__
<Relationship Id="rId__DIMAX__" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="1"><font><name val="'.$this->defaultFont.'"/><family val="2"/><sz val="'.$this->defaultFontSize.'"/></font></fonts>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" /></cellStyleXfs>
<cellXfs count="2">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
</cellXfs>
</styleSheet>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<fileVersion appName="SimpleXLSXGen"/>
<sheets>
__SHEETS__
</sheets>
</workbook>'
        ];
    }

    public static function fromArray( array $rows, $sheetName = null ) {
        $xlsx = new static();
        return $xlsx->addSheet( $rows, $sheetName );
    }

    public function addSheet( array $rows, $name = null ) {
        $this->curSheet++;
        
        $name = ($name === null) ? 'Sheet' . ($this->curSheet + 1) : $name;
        $this->sheets[$this->curSheet] = ['name' => $name, 'rows' => $rows];
        
        return $this;
    }

    public function __toString() {
        $fh = fopen( 'php://memory', 'wb' );
        if (!$fh) {
            return '';
        }
        
        if (!$this->_generate( $fh )) {
            fclose( $fh );
            return '';
        }
        
        $size = ftell( $fh );
        fseek( $fh, 0 );
        
        return (string) fread( $fh, $size );
    }

    public function saveAs( $filename ) {
        $fh = fopen( $filename, 'wb' );
        if (!$fh) {
            return false;
        }
        
        if (!$this->_generate( $fh )) {
            fclose( $fh );
            return false;
        }
        
        fclose( $fh );
        
        return true;
    }

    public function download() {
        return $this->downloadAs( 'export.xlsx' );
    }

    public function downloadAs( $filename ) {
        $fh = fopen( 'php://memory', 'wb' );
        if (!$fh) {
            return false;
        }
        
        if (!$this->_generate( $fh )) {
            fclose( $fh );
            return false;
        }
        
        $size = ftell( $fh );
        
        header( 'Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
        header( 'Content-Length: ' . $size );
        
        fseek( $fh, 0 );
        fpassthru( $fh );
        
        fclose( $fh );
        
        return true;
    }

    protected function _generate( $fh ) {
        
        if (empty($this->sheets)) {
            return false;
        }
        
        $zip = new ZipArchive();
        if (!$zip->open( $fh, ZipArchive::CREATE )) {
            return false;
        }
        
        $relationships = str_replace( '__SHEETS__', $this->_sheetsRels(), $this->template['xl/_rels/workbook.xml.rels'] );
        $relationships = str_replace( '__DIMAX__', (count($this->sheets) + 1), $relationships );
        
        $zip->addEmptyDir( '_rels' );
        $zip->addFromString( '_rels/.rels', $this->template['_rels/.rels'] );
        
        $zip->addEmptyDir( 'docProps' );
        $zip->addFromString( 'docProps/app.xml', $this->template['docProps/app.xml'] );
        $zip->addFromString( 'docProps/core.xml', $this->template['docProps/core.xml'] );
        
        $zip->addEmptyDir( 'xl' );
        $zip->addEmptyDir( 'xl/_rels' );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $relationships );
        
        $zip->addEmptyDir( 'xl/worksheets' );
        $zip->addEmptyDir( 'xl/worksheets/_rels' );
        $zip->addFromString( 'xl/workbook.xml', str_replace( '__SHEETS__', $this->_sheetsList(), $this->template['xl/workbook.xml']) );
        $zip->addFromString( 'xl/styles.xml', $this->template['xl/styles.xml'] );
        
        $ct = [];
        
        foreach ($this->sheets as $k => $v) {
            $filename = 'sheet' . ($k + 1) . '.xml';
            
            $zip->addFromString( 'xl/worksheets/' . $filename, $this->_sheet( $v['rows'] ) );
            
            $ct[] = '<Override PartName="/xl/worksheets/' . $filename . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        
        $zip->addFromString( '[Content_Types].xml', str_replace( '__SHEETSCT__', implode( "\r\n", $ct ), $this->template['[Content_Types].xml'] ) );
        
        $si = [];
        
        foreach ($this->SI as $v) {
            $si[] = '<si><t>' . $this->esc( $v ) . '</t></si>';
        }
        
        $zip->addFromString( 'xl/sharedStrings.xml', 
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $si ) . '" uniqueCount="' . count( $si ) . '">'
            . implode( "\r\n", $si ) .
            '</sst>');
        
        $zip->close();
        
        return true;
    }

    protected function _sheetsRels() {
        $s = '';
        for ($i = 0; $i < count($this->sheets); $i++) {
            $s .= '<Relationship Id="rId' . ($i + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>' . "\r\n";
        }
        return $s;
    }

    protected function _sheetsList() {
        $s = '';
        for ($i = 0; $i < count($this->sheets); $i++) {
            $s .= '<sheet name="' . $this->sheets[$i]['name'] . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 2) . '"/>';
        }
        return $s;
    }

    protected function _sheet( $rows ) {
        $s = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetData>';
        
        foreach ($rows as $r) {
            $s .= '<row>';
            
            foreach ($r as $c) {
                
                if (is_string($c) && $c[0] === '=') {
                    $s .= '<c><f>' . substr($c, 1) . '</f></c>';
                } elseif (is_numeric($c)) {
                    $s .= '<c t="n"><v>' . $c . '</v></c>';
                } else {
                    $s .= '<c t="s"><v>' . $this->_sAdd( $c ) . '</v></c>';
                }
            }
            
            $s .= '</row>';
        }
        
        $s .= '</sheetData></worksheet>';
        
        return $s;
    }

    protected function _sAdd( $s ) {
        $s = (string) $s;
        if (isset($this->SI_KEYS[$s])) {
            return $this->SI_KEYS[$s];
        }
        
        $this->SI[] = $s;
        $i = count($this->SI) - 1;
        $this->SI_KEYS[$s] = $i;
        
        return $i;
    }

    protected function esc( $s ) {
        // XML UTF-8 encoding
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        return htmlspecialchars($s, ENT_QUOTES);
    }
}
 