<?php
declare(strict_types=1);

/**
 * Leitor de .xlsx sem dependências (ZipArchive + SimpleXML).
 * Devolve, por aba, o texto de cada célula com a cor de fundo e se o texto está riscado.
 * Células mescladas recebem o conteúdo da célula de origem.
 */
class XlsxReader
{
    private ZipArchive $zip;
    private array $shared = [];
    private array $xf = [];
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException('O arquivo não é um .xlsx válido.');
        }
        $this->loadShared();
        $this->loadStyles();
    }

    private function xml(string $name): ?SimpleXMLElement
    {
        $s = $this->zip->getFromName($name);
        if ($s === false) return null;
        $x = @simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        return $x ?: null;
    }

    private static function texto(SimpleXMLElement $node): string
    {
        if (isset($node->t)) return (string) $node->t;
        $s = '';
        foreach ($node->r as $r) $s .= (string) $r->t;
        return $s;
    }

    private function loadShared(): void
    {
        $x = $this->xml('xl/sharedStrings.xml');
        if (!$x) return;
        foreach ($x->si as $si) $this->shared[] = self::texto($si);
    }

    private function loadStyles(): void
    {
        $x = $this->xml('xl/styles.xml');
        if (!$x) return;
        $fills = [];
        if (isset($x->fills)) {
            foreach ($x->fills->fill as $f) {
                $fills[] = self::fillInfo($f);
            }
        }
        $fonts = [];
        if (isset($x->fonts)) {
            foreach ($x->fonts->font as $f) {
                $strike = false;
                if (isset($f->strike)) {
                    $v = (string) ($f->strike['val'] ?? '1');
                    $strike = !in_array(strtolower($v), ['0', 'false'], true);
                }
                $fonts[] = $strike;
            }
        }
        if (isset($x->cellXfs)) {
            foreach ($x->cellXfs->xf as $xf) {
                $fill = $fills[(int) ($xf['fillId'] ?? 0)] ?? [null, null];
                $this->xf[] = [
                    'fill'   => $fill[0],
                    'rgb'    => $fill[1],
                    'strike' => $fonts[(int) ($xf['fontId'] ?? 0)] ?? false,
                ];
            }
        }
    }

    /** @return array{0:?string,1:?string} [chave de comparação, cor #rrggbb se conhecida] */
    private static function fillInfo(SimpleXMLElement $f): array
    {
        if (!isset($f->patternFill)) return [null, null];
        $p = $f->patternFill;
        $type = isset($p['patternType']) ? (string) $p['patternType'] : (isset($p->fgColor) ? 'solid' : 'none');
        if ($type === 'none' || $type === 'gray125') return [null, null];
        if (isset($p->fgColor)) $c = $p->fgColor;
        elseif (isset($p->bgColor)) $c = $p->bgColor;
        else return [null, null];
        if (isset($c['rgb'])) {
            $rgb = strtoupper(substr((string) $c['rgb'], -6));
            if ($rgb === 'FFFFFF') return [null, null];
            return ['rgb:' . $rgb, '#' . strtolower($rgb)];
        }
        if (isset($c['theme'])) return ['theme:' . (string) $c['theme'] . ':' . (string) ($c['tint'] ?? '0'), null];
        if (isset($c['indexed'])) {
            $i = (string) $c['indexed'];
            if ($i === '64' || $i === '9' || $i === '1') return [null, null];
            return ['idx:' . $i, null];
        }
        return [null, null];
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n;
    }

    public static function colLetra(int $n): string
    {
        $s = '';
        while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = intdiv($n - 1, 26); }
        return $s;
    }

    /** @return array<int, array{nome:string, cells:array}> */
    public function abas(): array
    {
        $wb = $this->xml('xl/workbook.xml');
        $rels = $this->xml('xl/_rels/workbook.xml.rels');
        if (!$wb) throw new RuntimeException('Planilha sem workbook.xml.');
        $alvo = [];
        if ($rels) {
            foreach ($rels->Relationship as $r) {
                $t = (string) $r['Target'];
                $t = str_starts_with($t, '/') ? ltrim($t, '/') : 'xl/' . $t;
                $alvo[(string) $r['Id']] = $t;
            }
        }
        $out = [];
        $i = 0;
        foreach ($wb->sheets->sheet as $s) {
            $i++;
            $rid = (string) ($s->attributes(self::NS_R)['id'] ?? '');
            $path = $alvo[$rid] ?? "xl/worksheets/sheet{$i}.xml";
            $state = (string) ($s['state'] ?? '');
            $out[] = ['nome' => (string) $s['name'], 'oculta' => $state !== '' && $state !== 'visible', 'cells' => $this->lerAba($path)];
        }
        return $out;
    }

    private function lerAba(string $path): array
    {
        $x = $this->xml($path);
        if (!$x || !isset($x->sheetData)) return [];
        $cells = [];
        $rowNum = 0;
        foreach ($x->sheetData->row as $row) {
            $rowNum = isset($row['r']) ? (int) $row['r'] : $rowNum + 1;
            $col = 0;
            foreach ($row->c as $c) {
                if (isset($c['r']) && preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m)) {
                    $col = self::colIndex($m[1]);
                } else {
                    $col++;
                }
                $t = (string) ($c['t'] ?? '');
                if ($t === 's') {
                    $v = $this->shared[(int) $c->v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = isset($c->is) ? self::texto($c->is) : '';
                } else {
                    $v = (string) ($c->v ?? '');
                }
                $v = trim(preg_replace('/\s+/u', ' ', $v));
                $st = $this->xf[(int) ($c['s'] ?? 0)] ?? ['fill' => null, 'rgb' => null, 'strike' => false];
                if ($v === '' && $st['fill'] === null) continue;
                $cells[$rowNum][$col] = ['v' => $v, 'fill' => $st['fill'], 'rgb' => $st['rgb'], 'strike' => $st['strike']];
            }
        }
        // Mesclagens: replica o conteúdo da célula de origem
        if (isset($x->mergeCells)) {
            foreach ($x->mergeCells->mergeCell as $mc) {
                if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', (string) $mc['ref'], $m)) continue;
                [$c1, $r1, $c2, $r2] = [self::colIndex($m[1]), (int) $m[2], self::colIndex($m[3]), (int) $m[4]];
                $orig = $cells[$r1][$c1] ?? null;
                if (!$orig || $orig['v'] === '') continue;
                for ($r = $r1; $r <= $r2; $r++) {
                    for ($cc = $c1; $cc <= $c2; $cc++) {
                        if ($r === $r1 && $cc === $c1) continue;
                        if (($cells[$r][$cc]['v'] ?? '') === '') {
                            $cells[$r][$cc] = $orig + ['merged' => true];
                            $cells[$r][$cc]['merged'] = true;
                        }
                    }
                }
            }
        }
        ksort($cells);
        foreach ($cells as &$r) ksort($r);
        return $cells;
    }

    /** Converte um CSV para a mesma estrutura (sem cores). */
    public static function csvComoAba(string $path, string $nome): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) throw new RuntimeException('Não foi possível ler o CSV.');
        $primeira = fgets($fh) ?: '';
        rewind($fh);
        $sep = substr_count($primeira, ';') > substr_count($primeira, ',') ? ';' : ',';
        $cells = [];
        $r = 0;
        while (($linha = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
            $r++;
            foreach ($linha as $i => $v) {
                $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
                if ($r === 1 && $i === 0) $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);
                if ($v === '') continue;
                $cells[$r][$i + 1] = ['v' => $v, 'fill' => null, 'rgb' => null, 'strike' => false];
            }
        }
        fclose($fh);
        return ['nome' => $nome, 'oculta' => false, 'cells' => $cells];
    }
}
