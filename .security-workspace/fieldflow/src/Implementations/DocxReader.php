<?php
namespace RoutesPro\Implementations;

if (!defined('ABSPATH')) exit;

/**
 * Leitor DOCX mínimo e defensivo.
 *
 * Evita depender de DOM, SimpleXML ou XMLReader, porque nem todos os
 * alojamentos WordPress disponibilizam estas extensões. O DOCX usa OOXML
 * previsível, por isso basta ler o document.xml e percorrer os blocos de topo.
 */
class DocxReader {
    public static function read(string $path): array {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('O ficheiro Word não está disponível para leitura.');
        }

        [$get, $close] = self::archiveReader($path);
        try {
            $xml = $get('word/document.xml');
            if ($xml === false || trim((string)$xml) === '') {
                throw new \RuntimeException('Estrutura DOCX incompleta: word/document.xml.');
            }
            return self::parseDocument((string)$xml);
        } finally {
            $close();
        }
    }

    private static function archiveReader(string $path): array {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Não foi possível abrir o ficheiro DOCX.');
            }
            return [
                static function (string $name) use ($zip) { return $zip->getFromName($name); },
                static function () use ($zip): void { $zip->close(); },
            ];
        }

        if (class_exists('PharData')) {
            try {
                $phar = new \PharData($path);
                return [
                    static function (string $name) use ($phar) {
                        return isset($phar[$name]) ? $phar[$name]->getContent() : false;
                    },
                    static function (): void {},
                ];
            } catch (\Throwable $e) {
                throw new \RuntimeException('Não foi possível abrir o ficheiro DOCX: ' . $e->getMessage());
            }
        }

        $pcl = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/class-pclzip.php' : '';
        if ($pcl && is_file($pcl)) require_once $pcl;
        if (!class_exists('PclZip')) {
            throw new \RuntimeException('O servidor não tem suporte ZIP para importar ficheiros DOCX.');
        }
        $archive = new \PclZip($path);
        return [
            static function (string $name) use ($archive) {
                $list = $archive->extract(PCLZIP_OPT_BY_NAME, $name, PCLZIP_OPT_EXTRACT_AS_STRING);
                return is_array($list) && isset($list[0]['content']) ? $list[0]['content'] : false;
            },
            static function (): void {},
        ];
    }

    private static function parseDocument(string $xml): array {
        if (!preg_match('/<w:body\b[^>]*>(.*)<\/w:body>/is', $xml, $bodyMatch)) {
            throw new \RuntimeException('O documento Word não contém um corpo legível.');
        }

        $body = (string)$bodyMatch[1];
        $blocks = [];
        $paragraphs = [];
        $tables = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $pPos = self::findOpenTag($body, 'w:p', $offset);
            $tPos = self::findOpenTag($body, 'w:tbl', $offset);
            if ($pPos === false && $tPos === false) break;

            $isTable = $tPos !== false && ($pPos === false || $tPos < $pPos);
            $start = $isTable ? $tPos : $pPos;
            if ($start === false) break;

            if ($isTable) {
                [$raw, $next] = self::extractBalanced($body, $start, 'w:tbl');
                if ($raw === '') break;
                $table = self::parseTable($raw);
                if ($table['rows']) {
                    $index = count($tables);
                    $tables[] = $table;
                    $blocks[] = ['type' => 'table', 'index' => $index, 'rows' => $table['rows'], 'fills' => $table['fills']];
                }
                $offset = $next;
                continue;
            }

            $end = strpos($body, '</w:p>', $start);
            if ($end === false) break;
            $end += strlen('</w:p>');
            $raw = substr($body, $start, $end - $start);
            $text = self::paragraphText($raw);
            if ($text !== '') {
                $index = count($paragraphs);
                $paragraphs[] = $text;
                $blocks[] = ['type' => 'paragraph', 'index' => $index, 'text' => $text];
            }
            $offset = $end;
        }

        return [
            'blocks' => $blocks,
            'paragraphs' => $paragraphs,
            'tables' => $tables,
        ];
    }

    private static function parseTable(string $raw): array {
        $rows = [];
        $fills = [];
        $offset = 0;
        $length = strlen($raw);
        while ($offset < $length) {
            $start = self::findOpenTag($raw, 'w:tr', $offset);
            if ($start === false) break;
            [$rowRaw, $next] = self::extractBalanced($raw, $start, 'w:tr');
            if ($rowRaw === '') break;

            $row = [];
            $rowFills = [];
            $cellOffset = 0;
            $rowLength = strlen($rowRaw);
            while ($cellOffset < $rowLength) {
                $cellStart = self::findOpenTag($rowRaw, 'w:tc', $cellOffset);
                if ($cellStart === false) break;
                [$cellRaw, $cellNext] = self::extractBalanced($rowRaw, $cellStart, 'w:tc');
                if ($cellRaw === '') break;
                $row[] = self::cellText($cellRaw);
                $rowFills[] = self::cellFill($cellRaw);
                $cellOffset = $cellNext;
            }

            if ($row) {
                $rows[] = $row;
                $fills[] = $rowFills;
            }
            $offset = $next;
        }

        return ['rows' => $rows, 'fills' => $fills];
    }

    private static function findOpenTag(string $xml, string $tag, int $offset = 0) {
        $needle = '<' . $tag;
        $length = strlen($xml);
        while ($offset < $length) {
            $position = strpos($xml, $needle, $offset);
            if ($position === false) return false;
            $next = substr($xml, $position + strlen($needle), 1);
            if ($next === '' || $next === '>' || preg_match('/\s/', $next)) return $position;
            $offset = $position + strlen($needle);
        }
        return false;
    }

    private static function extractBalanced(string $xml, int $start, string $tag): array {
        $openNeedle = '<' . $tag;
        $closeNeedle = '</' . $tag . '>';
        $cursor = $start;
        $depth = 0;
        $length = strlen($xml);

        while ($cursor < $length) {
            $open = self::findOpenTag($xml, $tag, $cursor);
            $close = strpos($xml, $closeNeedle, $cursor);
            if ($close === false) return ['', $length];

            if ($open !== false && $open < $close) {
                $tagEnd = strpos($xml, '>', $open);
                if ($tagEnd === false) return ['', $length];
                $selfClosing = substr($xml, max($open, $tagEnd - 1), 1) === '/';
                if (!$selfClosing) $depth++;
                $cursor = $tagEnd + 1;
                continue;
            }

            $depth--;
            $cursor = $close + strlen($closeNeedle);
            if ($depth <= 0) return [substr($xml, $start, $cursor - $start), $cursor];
        }

        return ['', $length];
    }

    private static function cellText(string $raw): string {
        $parts = [];
        if (preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/is', $raw, $matches)) {
            foreach ($matches[0] as $paragraph) {
                $text = self::paragraphText((string)$paragraph);
                if ($text !== '') $parts[] = $text;
            }
        }
        if (!$parts) {
            $text = self::paragraphText($raw);
            if ($text !== '') $parts[] = $text;
        }
        return self::cleanValue(implode("\n", $parts));
    }

    private static function paragraphText(string $raw): string {
        $raw = preg_replace('/<w:tab\b[^>]*\/?\s*>/i', "\t", $raw);
        $raw = preg_replace('/<w:(?:br|cr)\b[^>]*\/?\s*>/i', "\n", (string)$raw);
        $parts = [];
        if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/is', (string)$raw, $matches)) {
            foreach ($matches[1] as $text) $parts[] = self::decode((string)$text);
        }
        return self::cleanValue(implode('', $parts));
    }

    private static function cellFill(string $raw): string {
        if (preg_match('/<w:shd\b[^>]*\bw:fill=(?:"([^"]+)"|\'([^\']+)\')/i', $raw, $match)) {
            return strtoupper(trim((string)($match[1] !== '' ? $match[1] : ($match[2] ?? ''))));
        }
        return '';
    }

    private static function decode(string $value): string {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function cleanValue(string $value): string {
        $value = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $value);
        $value = preg_replace('/[\x{E000}-\x{F8FF}]/u', '', $value);
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string)$value);
        $value = preg_replace('/[ \t]+/u', ' ', (string)$value);
        $value = preg_replace('/\h*\R\h*/u', "\n", (string)$value);
        return trim((string)$value);
    }
}
