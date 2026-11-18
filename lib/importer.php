<?php
declare(strict_types=1);

/**
 * Lê o formato "SPRINT SEMANAL | 02 a 06 de Novembro" da planilha de sprint:
 * blocos semanais, coluna Responsável, colunas Segunda..Sexta (+ Recorrente)
 * e a legenda MARCADORES, cuja cor de fundo define o marcador de cada tarefa.
 */
class SprintParser
{
    private const MESES = [
        'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
        'jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12,
    ];
    private const DIAS = ['segunda' => 0, 'terca' => 1, 'quarta' => 2, 'quinta' => 3, 'sexta' => 4, 'sabado' => 5, 'domingo' => 6];

    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * @param array $abas saída de XlsxReader::abas()
     * @return array{itens:array, ausencias:array, marcadores:array, semanas:array, avisos:array}
     */
    public static function parse(array $abas): array
    {
        $marcadoresCfg = array_keys(cfg('marcadores', []));
        $marcNorm = [];
        foreach ($marcadoresCfg as $m) $marcNorm[self::norm($m)] = $m;
        $ausNorm = array_map([self::class, 'norm'], cfg('ausencias', []));

        $itens = []; $ausencias = []; $semanas = []; $avisos = [];
        $legendaGlobal = []; $coresMarcador = [];
        $blocos = [];

        foreach ($abas as $aba) {
            $cells = $aba['cells'];
            if (!$cells) continue;
            $ano = self::anoDaAba($cells);
            $mesAba = self::MESES[self::norm($aba['nome'])] ?? null;
            $bloco = null;

            foreach ($cells as $r => $linha) {
                $textoLinha = implode(' ', array_map(fn($c) => $c['v'], $linha));
                $nl = self::norm($textoLinha);

                if (str_contains($nl, 'sprint semanal')) {
                    $seg = self::segundaDoBloco($nl, $ano, $mesAba);
                    if (!$seg) { $avisos[] = "Aba {$aba['nome']}, linha $r: não entendi as datas de \"$textoLinha\""; $bloco = null; continue; }
                    $bloco = count($blocos);
                    $blocos[$bloco] = ['segunda' => $seg, 'cols' => null, 'pessoaCol' => null, 'pessoa' => null, 'legenda' => [], 'aba' => $aba['nome']];
                    $semanas[$seg] = true;
                    continue;
                }
                if ($bloco === null) continue;
                $B = &$blocos[$bloco];

                // cabeçalho
                $cab = [];
                $pessoaCol = null;
                foreach ($linha as $c => $cel) {
                    $n = self::norm($cel['v']);
                    if ($n === 'responsavel' || $n === 'responsaveis') $pessoaCol = $c;
                    foreach (self::DIAS as $dia => $off) if (str_starts_with($n, $dia)) $cab[$c] = $off;
                    if (str_starts_with($n, 'recorrente')) $cab[$c] = 'R';
                }
                if ($pessoaCol !== null || count($cab) >= 3) {
                    $B['cols'] = $cab;
                    $B['pessoaCol'] = $pessoaCol ?? 1;
                    $B['pessoa'] = null;
                    unset($B);
                    continue;
                }
                if ($B['cols'] === null) { unset($B); continue; }

                // legenda (MARCADORES) em qualquer coluna da linha
                $legendaCel = [];
                foreach ($linha as $c => $cel) {
                    $n = self::norm($cel['v']);
                    if (isset($marcNorm[$n])) {
                        $legendaCel[$c] = true;
                        if ($cel['fill']) {
                            $B['legenda'][$cel['fill']] = $marcNorm[$n];
                            $legendaGlobal[$cel['fill']] ??= $marcNorm[$n];
                            if ($cel['rgb']) $coresMarcador[$marcNorm[$n]] ??= $cel['rgb'];
                        }
                    }
                }

                $pv = trim($linha[$B['pessoaCol']]['v'] ?? '');
                if ($pv !== '' && !isset($marcNorm[self::norm($pv)])) $B['pessoa'] = $pv;
                if (!$B['pessoa']) { unset($B); continue; }

                foreach ($B['cols'] as $c => $off) {
                    if (isset($legendaCel[$c])) continue;
                    $cel = $linha[$c] ?? null;
                    if (!$cel || $cel['v'] === '') continue;
                    $nv = self::norm($cel['v']);
                    if ($nv === 'marcadores') continue;
                    $data = $off === 'R' ? null : mais_dias($B['segunda'], $off);
                    if ($off !== 'R' && in_array($nv, $ausNorm, true)) {
                        $ausencias[] = ['responsavel' => $B['pessoa'], 'semana' => $B['segunda'], 'data' => $data, 'texto' => $cel['v']];
                        continue;
                    }
                    $itens[] = [
                        'bloco' => $bloco,
                        'responsavel' => $B['pessoa'],
                        'titulo' => $cel['v'],
                        'semana' => $B['segunda'],
                        'data' => $data,
                        'recorrente' => $off === 'R' ? 1 : 0,
                        'fill' => $cel['fill'],
                        'riscada' => $cel['strike'] ? 1 : 0,
                        'aba' => $aba['nome'],
                        'celula' => XlsxReader::colLetra($c) . $r,
                    ];
                }
                unset($B);
            }
        }

        foreach ($itens as &$it) {
            $f = $it['fill'];
            $it['marcador'] = $f ? ($blocos[$it['bloco']]['legenda'][$f] ?? $legendaGlobal[$f] ?? null) : null;
            unset($it['fill'], $it['bloco']);
        }
        unset($it);

        if (!$blocos) $avisos[] = 'Nenhum bloco "SPRINT SEMANAL" encontrado. Confira se é a planilha certa.';

        return ['itens' => $itens, 'ausencias' => $ausencias, 'marcadores' => $coresMarcador, 'semanas' => array_keys($semanas), 'avisos' => $avisos];
    }

    private static function anoDaAba(array $cells): int
    {
        $outro = null;
        foreach ($cells as $linha) foreach ($linha as $c) {
            if (preg_match('/\b(20\d\d)\b/', $c['v'], $m)) {
                if (stripos($c['v'], 'sprint') !== false) return (int) $m[1];
                $outro ??= (int) $m[1];
            }
        }
        return $outro ?? (int) date('Y');
    }

    /** "sprint semanal | 02 a 06 de novembro" → segunda-feira daquela semana (Y-m-d) */
    private static function segundaDoBloco(string $nl, int $ano, ?int $mesAba): ?string
    {
        if (!preg_match('/(\d{1,2})\s*(?:de\s+)?([a-z]+)?\s*(?:a|ate|-|–)\s*(\d{1,2})\s*(?:de\s+)?([a-z]+)/u', $nl, $m)) return null;
        $mesFim = self::MESES[$m[4]] ?? null;
        $mesIni = ($m[2] ?? '') !== '' ? (self::MESES[$m[2]] ?? $mesFim) : $mesFim;
        if (!$mesFim || !$mesIni) return null;
        $anoIni = $ano;
        if ($mesIni > $mesFim && $mesAba === $mesFim) $anoIni = $ano - 1;
        if (!checkdate($mesIni, (int) $m[1], $anoIni)) return null;
        return segunda_de(sprintf('%04d-%02d-%02d', $anoIni, $mesIni, (int) $m[1]));
    }
}

class Importer
{
    public static function chave(string $responsavel, string $titulo): string
    {
        return SprintParser::norm($responsavel) . '|' . SprintParser::norm($titulo);
    }

    public static function link_export(): ?string
    {
        if (!preg_match('~/spreadsheets/d/([a-zA-Z0-9_-]+)~', (string) cfg('planilha_url'), $m)) return null;
        return 'https://docs.google.com/spreadsheets/d/' . $m[1] . '/export?format=xlsx';
    }

    public static function sincronizarLink(): array
    {
        $url = self::link_export();
        if (!$url) throw new RuntimeException('Link da planilha não configurado em config.php.');
        $tmp = tempnam(sys_get_temp_dir(), 'dem');
        try {
            $ok = false; $erro = '';
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                $fh = fopen($tmp, 'w');
                curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'demandas-sync']);
                $ok = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $erro = curl_error($ch);
                curl_close($ch); fclose($fh);
                if ($ok && ($code !== 200 || str_contains($tipo, 'text/html'))) { $ok = false; $erro = "o Google respondeu HTTP $code ($tipo). A planilha está compartilhada como \"qualquer pessoa com o link\"?"; }
            } else {
                $data = @file_get_contents($url);
                if ($data !== false) { file_put_contents($tmp, $data); $ok = true; } else $erro = 'falha ao baixar';
            }
            if (!$ok) {
                self::registrarFalha('link', 'Google Sheets', 'Não consegui baixar a planilha: ' . $erro);
                throw new RuntimeException('Não consegui baixar a planilha: ' . $erro);
            }
            return self::importarArquivo($tmp, 'planilha.xlsx', 'link');
        } finally {
            @unlink($tmp);
        }
    }

    private static function registrarFalha(string $origem, string $arquivo, string $msg): void
    {
        db()->prepare('INSERT INTO importacoes (origem, arquivo, criado_em, ok, resumo) VALUES (?,?,?,0,?)')
            ->execute([$origem, $arquivo, agora(), $msg]);
    }

    public static function importarArquivo(string $path, string $nomeOriginal, string $origem = 'upload'): array
    {
        try {
            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            if ($ext === 'csv') {
                $abas = [XlsxReader::csvComoAba($path, pathinfo($nomeOriginal, PATHINFO_FILENAME))];
            } else {
                $abas = (new XlsxReader($path))->abas();
            }
            $parsed = SprintParser::parse($abas);
            if (!$parsed['itens'] && !$parsed['ausencias']) {
                throw new RuntimeException(implode(' ', $parsed['avisos']) ?: 'Nenhuma demanda encontrada no arquivo.');
            }
            return self::aplicar($parsed, $origem, $nomeOriginal);
        } catch (Throwable $e) {
            if (!($e instanceof PDOException)) self::registrarFalha($origem, $nomeOriginal, $e->getMessage());
            throw $e;
        }
    }

    public static function aplicar(array $p, string $origem, string $arquivo): array
    {
        $db = db();
        $db->beginTransaction();
        try {
            $agora = agora();
            $autor = 'Planilha';
            $semanas = $p['semanas'];

            foreach ($p['marcadores'] as $nome => $cor) {
                $db->prepare('INSERT INTO marcadores (nome, cor) VALUES (?,?) ON CONFLICT(nome) DO UPDATE SET cor = excluded.cor')->execute([$nome, $cor]);
            }

            // ocorrências antigas das semanas presentes no arquivo (para comparar)
            $antes = [];
            if ($semanas) {
                $in = implode(',', array_fill(0, count($semanas), '?'));
                $st = $db->prepare("SELECT demanda_id, semana, data, recorrente FROM ocorrencias WHERE semana IN ($in)");
                $st->execute($semanas);
                foreach ($st as $o) $antes[$o['demanda_id']][self::rotulo($o)] = true;
                $db->prepare("DELETE FROM ocorrencias WHERE semana IN ($in)")->execute($semanas);
                $db->prepare("DELETE FROM ausencias WHERE semana IN ($in)")->execute($semanas);
            }

            // agrupa por demanda
            $grupos = [];
            foreach ($p['itens'] as $it) $grupos[self::chave($it['responsavel'], $it['titulo'])][] = $it;

            $sel = $db->prepare('SELECT * FROM demandas WHERE chave = ?');
            $ins = $db->prepare('INSERT INTO demandas (chave, titulo, responsavel, origem, tipo, marcador, riscada, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)');
            $insOc = $db->prepare('INSERT INTO ocorrencias (demanda_id, semana, data, recorrente, marcador, riscada, aba, celula, texto) VALUES (?,?,?,?,?,?,?,?,?)');
            $novas = 0; $atualizadas = 0; $tocadas = [];

            foreach ($grupos as $chave => $its) {
                usort($its, fn($a, $b) => strcmp(($a['data'] ?? $a['semana'] . 'R'), ($b['data'] ?? $b['semana'] . 'R')));
                $ult = end($its);
                $tipo = str_starts_with(SprintParser::norm($ult['titulo']), 'reuniao') ? 'reuniao' : 'tarefa';
                $sel->execute([$chave]);
                $d = $sel->fetch();
                if (!$d) {
                    $ins->execute([$chave, $ult['titulo'], $ult['responsavel'], 'planilha', $tipo, $ult['marcador'], $ult['riscada'], $agora, $agora]);
                    $id = (int) $db->lastInsertId();
                    $primeira = $its[0];
                    registrar($id, 'planilha', 'Demanda criada a partir da planilha (aba ' . $primeira['aba'] . ', ' . self::rotulo($primeira, true) . ').', $autor);
                    $novas++;
                    $d = ['id' => $id, 'marcador' => $ult['marcador'], 'na_planilha' => 1, 'novo' => true];
                }
                $id = (int) $d['id'];
                $tocadas[$id] = true;
                $depois = [];
                foreach ($its as $it) {
                    $insOc->execute([$id, $it['semana'], $it['data'], $it['recorrente'], $it['marcador'], $it['riscada'], $it['aba'], $it['celula'], $it['titulo']]);
                    $depois[self::rotulo($it)] = $it;
                }
                if (!empty($d['novo'])) continue;

                $mud = [];
                $add = array_diff_key($depois, $antes[$id] ?? []);
                $rem = array_diff_key($antes[$id] ?? [], $depois);
                if ($add) $mud[] = 'agendada para ' . implode(', ', array_map(fn($it) => self::rotulo($it, true), $add));
                if ($rem) $mud[] = 'saiu de ' . implode(', ', array_map(fn($k) => self::rotuloDeChave($k), array_keys($rem)));
                if (($d['marcador'] ?? null) !== $ult['marcador']) $mud[] = 'marcador: ' . ($d['marcador'] ?: 'nenhum') . ' → ' . ($ult['marcador'] ?: 'nenhum');
                if ((int) $d['na_planilha'] === 0) $mud[] = 'voltou a aparecer na planilha';
                if ((int) $d['riscada'] !== (int) $ult['riscada']) $mud[] = $ult['riscada'] ? 'foi riscada na planilha' : 'deixou de estar riscada na planilha';
                if ($mud) {
                    registrar($id, 'planilha', 'Planilha: ' . implode('; ', $mud) . '.', $autor);
                    $atualizadas++;
                }
                $db->prepare('UPDATE demandas SET titulo = ?, marcador = ?, riscada = ?, na_planilha = 1, atualizado_em = CASE WHEN ? THEN ? ELSE atualizado_em END WHERE id = ?')
                    ->execute([$ult['titulo'], $ult['marcador'], $ult['riscada'], $mud ? 1 : 0, $agora, $id]);
            }

            // demandas que tinham ocorrências nas semanas do arquivo e sumiram
            foreach ($antes as $id => $rots) {
                if (isset($tocadas[$id])) continue;
                registrar((int) $id, 'planilha', 'Planilha: saiu de ' . implode(', ', array_map(fn($k) => self::rotuloDeChave($k), array_keys($rots))) . '.', $autor);
                $atualizadas++;
            }

            foreach ($p['ausencias'] as $a) {
                $db->prepare('INSERT INTO ausencias (responsavel, semana, data, texto) VALUES (?,?,?,?)')->execute([$a['responsavel'], $a['semana'], $a['data'], $a['texto']]);
            }

            // datas e presença na planilha
            $db->exec("UPDATE demandas SET
                primeira_data = (SELECT MIN(COALESCE(data, semana)) FROM ocorrencias o WHERE o.demanda_id = demandas.id),
                ultima_data   = (SELECT MAX(COALESCE(data, date(semana, '+4 days'))) FROM ocorrencias o WHERE o.demanda_id = demandas.id)
                WHERE origem = 'planilha'");
            $sumiram = $db->query("SELECT id FROM demandas WHERE origem = 'planilha' AND na_planilha = 1 AND NOT EXISTS (SELECT 1 FROM ocorrencias o WHERE o.demanda_id = demandas.id)")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($sumiram as $id) {
                registrar((int) $id, 'planilha', 'Não aparece mais na planilha. As anotações e comentários foram mantidos.', $autor);
                $db->prepare('UPDATE demandas SET na_planilha = 0 WHERE id = ?')->execute([$id]);
            }

            $resumo = implode("\n", $p['avisos']);
            $db->prepare('INSERT INTO importacoes (origem, arquivo, criado_em, ok, novas, atualizadas, ocorrencias, semanas, resumo) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$origem, $arquivo, $agora, 1, $novas, $atualizadas, count($p['itens']), count($semanas), $resumo]);
            $db->commit();
            return ['novas' => $novas, 'atualizadas' => $atualizadas, 'ocorrencias' => count($p['itens']), 'semanas' => count($semanas), 'avisos' => $p['avisos']];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function rotulo(array $o, bool $legivel = false): string
    {
        if (!$legivel) return $o['recorrente'] ? 'R:' . $o['semana'] : 'D:' . $o['data'];
        return $o['recorrente'] ? 'recorrente na semana de ' . data_br($o['semana']) : data_br($o['data'], true);
    }
    private static function rotuloDeChave(string $k): string
    {
        [$t, $d] = explode(':', $k, 2);
        return $t === 'R' ? 'recorrente na semana de ' . data_br($d) : data_br($d, true);
    }
}
