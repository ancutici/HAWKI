<?php

namespace App\Services\FileConverter\Handlers;

use App\Services\FileConverter\Handlers\Interfaces\FileConverterInterface;
use DOMDocument;
use Exception;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Finder\SplFileInfo;
use ZipArchive;

class LocalFileConverter implements FileConverterInterface
{
    public function convert(UploadedFile|SplFileInfo|string $file): array
    {
        [$path, $filename] = $this->resolvePath($file);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        return match ($ext) {
            'txt', 'md' => $this->passthrough($path, $baseName),
            'html', 'htm' => $this->htmlToMarkdown($path, $baseName),
            'csv'       => $this->csvToMarkdown($path, $baseName),
            'xlsx', 'xls' => $this->excelToMarkdown($path, $baseName),
            'pptx'      => $this->pptxToMarkdown($path, $baseName),
            default     => throw new InvalidArgumentException("LocalFileConverter: unsupported type '$ext'"),
        };
    }

    // -----------------------------------------------------------------------

    private function resolvePath(UploadedFile|SplFileInfo|string $file): array
    {
        if ($file instanceof UploadedFile) {
            return [$file->getRealPath(), $file->getClientOriginalName()];
        }
        if ($file instanceof \SplFileInfo) {
            return [$file->getPathname(), $file->getFilename()];
        }
        // String: raw file contents – write to temp file, extension unknown
        $tmp = tempnam(sys_get_temp_dir(), 'lcv_');
        file_put_contents($tmp, $file);
        return [$tmp, 'document.txt'];
    }

    private function passthrough(string $path, string $base): array
    {
        return [$base . '.md' => file_get_contents($path)];
    }

    private function csvToMarkdown(string $path, string $base): array
    {
        $handle = fopen($path, 'r');
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return [$base . '.md' => ''];
        }

        $colCount = max(array_map('count', $rows));

        // Pad all rows to the same column count
        $rows = array_map(function ($row) use ($colCount) {
            return array_pad($row, $colCount, '');
        }, $rows);

        $escape = fn($v) => str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) $v);

        $md  = '| ' . implode(' | ', array_map($escape, $rows[0])) . " |\n";
        $md .= '| ' . implode(' | ', array_fill(0, $colCount, '---')) . " |\n";
        foreach (array_slice($rows, 1) as $row) {
            $md .= '| ' . implode(' | ', array_map($escape, $row)) . " |\n";
        }

        return [$base . '.md' => $md];
    }

    private function excelToMarkdown(string $path, string $base): array
    {
        $spreadsheet = IOFactory::load($path);
        $md = '';

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = $sheet->getTitle();
            $data  = $sheet->toArray(null, true, true, false);

            if (empty($data)) {
                continue;
            }

            $md .= "## $title\n\n";

            $colCount = max(array_map('count', $data));
            $escape   = fn($v) => str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) ($v ?? ''));

            $header = array_shift($data);
            $header = array_pad($header, $colCount, '');

            $md .= '| ' . implode(' | ', array_map($escape, $header)) . " |\n";
            $md .= '| ' . implode(' | ', array_fill(0, $colCount, '---')) . " |\n";

            foreach ($data as $row) {
                $row = array_pad($row, $colCount, '');
                $md .= '| ' . implode(' | ', array_map($escape, $row)) . " |\n";
            }

            $md .= "\n";
        }

        return [$base . '.md' => $md];
    }

    private function htmlToMarkdown(string $path, string $base): array
    {
        $html = file_get_contents($path);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);

        // Remove script and style nodes
        foreach (['script', 'style', 'noscript'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        $md = $this->domNodeToMarkdown($dom->documentElement ?? $dom);

        // Collapse excessive blank lines
        $md = preg_replace("/\n{3,}/", "\n\n", trim($md));

        return [$base . '.md' => $md];
    }

    private function domNodeToMarkdown(\DOMNode $node, int $headingOffset = 0): string
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/', ' ', $node->textContent);
            return $text === ' ' ? '' : $text;
        }

        if (!($node instanceof \DOMElement)) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $this->domNodeToMarkdown($child, $headingOffset);
        }
        $inner = trim($inner);

        return match (true) {
            in_array($tag, ['h1','h2','h3','h4','h5','h6']) => "\n\n" . str_repeat('#', (int)$tag[1] + $headingOffset) . ' ' . $inner . "\n\n",
            $tag === 'p'        => "\n\n" . $inner . "\n\n",
            $tag === 'br'       => "\n",
            $tag === 'strong' || $tag === 'b' => "**$inner**",
            $tag === 'em' || $tag === 'i'     => "_${inner}_",
            $tag === 'code'     => "`$inner`",
            $tag === 'pre'      => "\n\n```\n$inner\n```\n\n",
            $tag === 'blockquote' => "\n\n" . implode("\n", array_map(fn($l) => "> $l", explode("\n", $inner))) . "\n\n",
            $tag === 'li'       => "- $inner\n",
            in_array($tag, ['ul','ol']) => "\n\n" . $inner . "\n\n",
            $tag === 'a'        => $inner,
            in_array($tag, ['div','section','article','main','header','footer','nav','aside']) => "\n\n" . $inner . "\n\n",
            in_array($tag, ['html','body','head','title','meta','link','span','td','th']) => $inner,
            $tag === 'tr'       => $inner . "\n",
            $tag === 'table'    => "\n\n" . $inner . "\n\n",
            default             => $inner,
        };
    }

    private function pptxToMarkdown(string $path, string $base): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("LocalFileConverter: cannot open PPTX file '$path'");
        }

        $md = '';
        $slideIndex = 1;

        while (($xml = $zip->getFromName("ppt/slides/slide{$slideIndex}.xml")) !== false) {
            $md .= "## Folie $slideIndex\n\n";

            $dom = new DOMDocument();
            @$dom->loadXML($xml);

            // Collect paragraph texts; <a:t> holds text runs
            $paragraphs = $dom->getElementsByTagNameNS(
                'http://schemas.openxmlformats.org/drawingml/2006/main', 'p'
            );

            foreach ($paragraphs as $para) {
                $texts = $para->getElementsByTagNameNS(
                    'http://schemas.openxmlformats.org/drawingml/2006/main', 't'
                );
                $line = '';
                foreach ($texts as $t) {
                    $line .= $t->textContent;
                }
                $line = trim($line);
                if ($line !== '') {
                    $md .= $line . "\n";
                }
            }

            $md .= "\n";
            $slideIndex++;
        }

        $zip->close();

        return [$base . '.md' => $md];
    }
}
