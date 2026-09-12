<?php

/**
 * Minimal, dependency-free PDF writer.
 *
 * Etheron's shared hosting gives us plain PHP with no Composer, no shell
 * access, and no guarantee of any PDF extension — so this hand-writes the
 * PDF file format directly (objects, an xref table, a trailer) rather than
 * relying on a third-party library. It only supports what the hours export
 * needs: simple left-aligned text tables using the built-in Helvetica
 * fonts (no font embedding required, since Helvetica is one of the 14
 * standard PDF fonts every viewer must support).
 */

// Adobe's published Helvetica AFM advance widths (1/1000 em) for the
// printable ASCII range. Used only to word-wrap text to a column width;
// approximate widths are fine since this never affects PDF validity.
const PDF_HELVETICA_WIDTHS = [
    32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667,
    39 => 191, 40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333,
    46 => 278, 47 => 278, 48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556,
    53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556, 58 => 278, 59 => 278,
    60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015, 65 => 667, 66 => 667,
    67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278,
    74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667,
    81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
    88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469,
    95 => 556, 96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556,
    102 => 278, 103 => 556, 104 => 556, 105 => 222, 106 => 222, 107 => 500,
    108 => 222, 109 => 833, 110 => 556, 111 => 556, 112 => 556, 113 => 556,
    114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
    120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334,
    126 => 584,
];

function pdf_char_width(string $char, float $fontSize): float
{
    $code = ord($char);
    $units = PDF_HELVETICA_WIDTHS[$code] ?? 556;
    return $units / 1000 * $fontSize;
}

function pdf_text_width(string $text, float $fontSize): float
{
    $width = 0.0;
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $width += pdf_char_width($text[$i], $fontSize);
    }
    return $width;
}

/**
 * Word-wraps $text to fit within $maxWidth points at $fontSize, honoring
 * explicit newlines as paragraph breaks. Returns a list of lines.
 */
function pdf_wrap_text(string $text, float $maxWidth, float $fontSize): array
{
    $lines = [];
    $paragraphs = preg_split('/\r\n|\r|\n/', $text);

    foreach ($paragraphs as $paragraph) {
        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }
        $words = preg_split('/\s+/', trim($paragraph));
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (pdf_text_width($candidate, $fontSize) <= $maxWidth || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
    }

    return $lines;
}

/**
 * Converts UTF-8 text to the Latin-1-ish subset PDF's standard fonts can
 * display (WinAnsiEncoding), replacing anything outside that range so the
 * PDF never ends up with corrupted or unrenderable bytes.
 */
function pdf_to_winansi(string $text): string
{
    $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $text);
}

function pdf_escape_string(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

/**
 * Flows headings, plain lines, and tables onto pages in order, breaking
 * pages as needed (re-printing a table's header row when a table spans
 * onto a new page), then renders the whole thing into a PDF file.
 *
 * Used for the hours export, which needs several sections (a summary, the
 * weekly history table, the logged entries table) in one flowing document
 * rather than a single fixed table.
 */
class PdfReport
{
    private float $pageWidth = 595.28;
    private float $pageHeight = 841.89;
    private float $margin = 40.0;
    private float $usableWidth;
    private float $usableHeight;
    private float $bodyFontSize = 9.0;
    private float $lineHeight = 12.0;
    private float $rowPadding = 4.0;
    private float $titleBlockHeight = 50.0;
    private float $bottomReserve = 20.0;

    private string $title;
    private string $subtitle;
    /** sprintf template for the per-page footer label; takes (page, totalPages). */
    private string $pageLabelFormat = 'Page %d of %d';
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    public function __construct(string $title, string $subtitle)
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->usableWidth = $this->pageWidth - 2 * $this->margin;
        $this->usableHeight = $this->pageHeight - 2 * $this->margin;
    }

    /** @param string $format sprintf template taking (page number, total pages), e.g. 'Pagina %d van %d'. */
    public function setPageLabelFormat(string $format): void
    {
        $this->pageLabelFormat = $format;
    }

    public function addHeading(string $text): void
    {
        $this->items[] = ['type' => 'heading', 'text' => $text, 'height' => 22.0];
    }

    public function addLine(string $text, bool $bold = false): void
    {
        $this->items[] = ['type' => 'line', 'text' => $text, 'bold' => $bold, 'height' => $this->lineHeight + 4];
    }

    public function addSpacer(float $height = 10.0): void
    {
        $this->items[] = ['type' => 'spacer', 'height' => $height];
    }

    /**
     * @param array $columns List of ['label' => string, 'width' => float, 'wrap' => bool].
     *              Widths should sum to the usable page width (515pt at these margins).
     * @param array $rows List of rows; each row is a list of string cell values matching $columns.
     * @param string|null $footer Optional bold line printed right after the table.
     */
    public function addTable(array $columns, array $rows, ?string $footer = null): void
    {
        $headerRowHeight = $this->lineHeight + $this->rowPadding;
        $this->items[] = ['type' => 'table_header', 'columns' => $columns, 'height' => $headerRowHeight];

        foreach ($rows as $row) {
            $cellLines = [];
            $maxLines = 1;
            foreach ($columns as $i => $col) {
                $value = pdf_to_winansi((string) ($row[$i] ?? ''));
                if (!empty($col['wrap'])) {
                    $lines = pdf_wrap_text($value, $col['width'] - 2 * $this->rowPadding, $this->bodyFontSize);
                    if (empty($lines)) {
                        $lines = [''];
                    }
                } else {
                    $lines = [$value];
                }
                $cellLines[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $this->items[] = [
                'type' => 'table_row',
                'columns' => $columns,
                'lines' => $cellLines,
                'height' => $maxLines * $this->lineHeight + $this->rowPadding,
            ];
        }

        if ($footer !== null) {
            $this->addLine($footer, true);
        }
    }

    public function render(): string
    {
        $pages = $this->paginate();
        $totalPages = count($pages);

        $pageContents = [];
        foreach ($pages as $pageIndex => $pageItems) {
            $pageContents[] = $this->renderPage($pageItems, $pageIndex, $totalPages);
        }

        return pdf_assemble($this->pageWidth, $this->pageHeight, $pageContents);
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    private function paginate(): array
    {
        $pages = [];
        $currentPage = [];
        $y = $this->usableHeight - $this->titleBlockHeight;
        $activeTableColumns = null;

        foreach ($this->items as $item) {
            $needed = $item['height'];

            if ($y - $needed < $this->bottomReserve && !empty($currentPage)) {
                $pages[] = $currentPage;
                $currentPage = [];
                $y = $this->usableHeight;

                if ($item['type'] === 'table_row' && $activeTableColumns !== null) {
                    $headerItem = [
                        'type' => 'table_header',
                        'columns' => $activeTableColumns,
                        'height' => $this->lineHeight + $this->rowPadding,
                    ];
                    $currentPage[] = $headerItem;
                    $y -= $headerItem['height'];
                }
            }

            if ($item['type'] === 'table_header') {
                $activeTableColumns = $item['columns'];
            } elseif ($item['type'] !== 'table_row') {
                $activeTableColumns = null;
            }

            $currentPage[] = $item;
            $y -= $needed;
        }

        $pages[] = $currentPage;
        return $pages;
    }

    private function renderPage(array $pageItems, int $pageIndex, int $totalPages): string
    {
        $stream = "q\n";
        $y = $this->usableHeight + $this->margin;

        if ($pageIndex === 0) {
            $stream .= pdf_text_op($this->margin, $y - 16, 16, true, pdf_to_winansi($this->title));
            $stream .= pdf_text_op($this->margin, $y - 32, 10, false, pdf_to_winansi($this->subtitle));
            $y -= $this->titleBlockHeight;
        }

        foreach ($pageItems as $item) {
            switch ($item['type']) {
                case 'heading':
                    $stream .= pdf_text_op($this->margin, $y - 12, 12, true, pdf_to_winansi($item['text']));
                    break;

                case 'line':
                    $stream .= pdf_text_op($this->margin, $y - $this->lineHeight + 2, 10, $item['bold'], pdf_to_winansi($item['text']));
                    break;

                case 'spacer':
                    break;

                case 'table_header':
                    $x = $this->margin;
                    foreach ($item['columns'] as $col) {
                        $stream .= pdf_text_op($x + $this->rowPadding, $y - $this->lineHeight + 2, $this->bodyFontSize, true, pdf_to_winansi($col['label']));
                        $x += $col['width'];
                    }
                    $stream .= pdf_line_op($this->margin, $y - $item['height'] + $this->rowPadding, $this->margin + $this->usableWidth, $y - $item['height'] + $this->rowPadding);
                    break;

                case 'table_row':
                    $x = $this->margin;
                    foreach ($item['columns'] as $i => $col) {
                        $lines = $item['lines'][$i];
                        foreach ($lines as $lineIndex => $line) {
                            if ($line === '') continue;
                            $ly = $y - $this->lineHeight * ($lineIndex + 1) + 2;
                            $stream .= pdf_text_op($x + $this->rowPadding, $ly, $this->bodyFontSize, false, $line);
                        }
                        $x += $col['width'];
                    }
                    $stream .= pdf_line_op($this->margin, $y - $item['height'] + $this->rowPadding, $this->margin + $this->usableWidth, $y - $item['height'] + $this->rowPadding);
                    break;
            }

            $y -= $item['height'];
        }

        $pageLabel = sprintf($this->pageLabelFormat, $pageIndex + 1, $totalPages);
        $stream .= pdf_text_op($this->pageWidth - $this->margin - pdf_text_width($pageLabel, 8), $this->margin - 20, 8, false, $pageLabel);

        $stream .= "Q\n";
        return $stream;
    }
}

/**
 * Builds a complete PDF file (as a raw byte string) for a simple
 * single-table report. Thin wrapper around PdfReport for callers that
 * only need one table.
 *
 * @param string $title Report title, shown at the top of the first page.
 * @param string $subtitle Smaller line under the title (e.g. a generated date).
 * @param array $columns List of ['label' => string, 'width' => float points, 'wrap' => bool].
 * @param array $rows List of rows; each row is a list of string cell values matching $columns.
 * @param string $footer Optional line printed after the table (e.g. a total).
 */
function build_table_pdf(string $title, string $subtitle, array $columns, array $rows, string $footer = ''): string
{
    $report = new PdfReport($title, $subtitle);
    $report->addTable($columns, $rows, $footer !== '' ? $footer : null);
    return $report->render();
}

function pdf_text_op(float $x, float $y, float $size, bool $bold, string $text): string
{
    $font = $bold ? '/F2' : '/F1';
    $escaped = pdf_escape_string($text);
    return sprintf("BT\n%s %.2F Tf\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n", $font, $size, $x, $y, $escaped);
}

function pdf_line_op(float $x1, float $y1, float $x2, float $y2): string
{
    return sprintf("0.5 w\n%.2F %.2F m\n%.2F %.2F l\nS\n", $x1, $y1, $x2, $y2);
}

/**
 * Assembles the final PDF byte string from rendered page content streams:
 * writes the header, every indirect object (catalog, pages tree, fonts,
 * each page and its content stream), then a byte-accurate xref table and
 * trailer pointing back at them.
 */
function pdf_assemble(float $pageWidth, float $pageHeight, array $pageContents): string
{
    $objects = [];
    // Reserve object numbers: 1=Catalog, 2=Pages, 3=Font regular, 4=Font bold
    $catalogNum = 1;
    $pagesNum = 2;
    $fontRegularNum = 3;
    $fontBoldNum = 4;
    $nextObjNum = 5;

    $pageNums = [];
    $contentNums = [];
    foreach ($pageContents as $content) {
        $pageNums[] = $nextObjNum++;
        $contentNums[] = $nextObjNum++;
    }

    $objects[$catalogNum] = "<< /Type /Catalog /Pages {$pagesNum} 0 R >>";

    $kids = implode(' ', array_map(fn($n) => "{$n} 0 R", $pageNums));
    $objects[$pagesNum] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageNums) . " >>";

    $objects[$fontRegularNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $objects[$fontBoldNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

    foreach ($pageContents as $i => $content) {
        $pageNum = $pageNums[$i];
        $contentNum = $contentNums[$i];
        $objects[$pageNum] =
            "<< /Type /Page /Parent {$pagesNum} 0 R "
            . "/MediaBox [0 0 " . round($pageWidth, 2) . " " . round($pageHeight, 2) . "] "
            . "/Resources << /Font << /F1 {$fontRegularNum} 0 R /F2 {$fontBoldNum} 0 R >> >> "
            . "/Contents {$contentNum} 0 R >>";

        $length = strlen($content);
        $objects[$contentNum] = "<< /Length {$length} >>\nstream\n{$content}endstream";
    }

    ksort($objects);

    $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($out);
        $out .= "{$num} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($out);
    $maxNum = max(array_keys($objects));
    $out .= "xref\n0 " . ($maxNum + 1) . "\n";
    $out .= "0000000000 65535 f \n";
    for ($n = 1; $n <= $maxNum; $n++) {
        if (isset($offsets[$n])) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$n]);
        } else {
            $out .= "0000000000 00000 f \n";
        }
    }

    $out .= "trailer\n<< /Size " . ($maxNum + 1) . " /Root {$catalogNum} 0 R >>\n";
    $out .= "startxref\n{$xrefOffset}\n%%EOF";

    return $out;
}
