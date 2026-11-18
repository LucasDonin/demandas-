<?php
declare(strict_types=1);

$GLOBALS['CFG'] = require __DIR__ . '/../config.php';
date_default_timezone_set(cfg('timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('demandas_sess');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/importer.php';
require_once __DIR__ . '/layout.php';

const STATUS = [
    'nova'       => 'A fazer',
    'andamento'  => 'Em andamento',
    'aguardando' => 'Aguardando',
    'concluida'  => 'Concluída',
    'cancelada'  => 'Cancelada',
];
const STATUS_ABERTOS = ['nova', 'andamento', 'aguardando'];
const PRIORIDADES = ['' => 'Sem prioridade', 'alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'];
const DIAS_CURTOS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const DIAS_LONGOS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const MESES_CURTOS = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

function cfg(string $k, $default = null) { return $GLOBALS['CFG'][$k] ?? $default; }
function eu(): string { return (string) cfg('meu_nome', ''); }
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function agora(): string { return date('Y-m-d H:i:s'); }
function hoje(): string { return date('Y-m-d'); }

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = cfg('db');
    $novo = !file_exists($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    migrar($pdo);
    return $pdo;
}

function migrar(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS demandas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chave TEXT UNIQUE,
    titulo TEXT NOT NULL,
    responsavel TEXT NOT NULL,
    origem TEXT NOT NULL DEFAULT 'planilha',
    tipo TEXT NOT NULL DEFAULT 'tarefa',
    status TEXT NOT NULL DEFAULT 'nova',
    prioridade TEXT NOT NULL DEFAULT '',
    progresso INTEGER NOT NULL DEFAULT 0,
    prazo TEXT,
    marcador TEXT,
    riscada INTEGER NOT NULL DEFAULT 0,
    na_planilha INTEGER NOT NULL DEFAULT 1,
    primeira_data TEXT,
    ultima_data TEXT,
    descricao TEXT NOT NULL DEFAULT '',
    criado_em TEXT NOT NULL,
    atualizado_em TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS ocorrencias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    demanda_id INTEGER NOT NULL REFERENCES demandas(id) ON DELETE CASCADE,
    semana TEXT NOT NULL,
    data TEXT,
    recorrente INTEGER NOT NULL DEFAULT 0,
    marcador TEXT,
    riscada INTEGER NOT NULL DEFAULT 0,
    aba TEXT,
    celula TEXT,
    texto TEXT
);
CREATE INDEX IF NOT EXISTS ix_oc_dem ON ocorrencias(demanda_id);
CREATE INDEX IF NOT EXISTS ix_oc_sem ON ocorrencias(semana);
CREATE TABLE IF NOT EXISTS ausencias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    responsavel TEXT NOT NULL,
    semana TEXT NOT NULL,
    data TEXT NOT NULL,
    texto TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS notas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    demanda_id INTEGER NOT NULL REFERENCES demandas(id) ON DELETE CASCADE,
    titulo TEXT NOT NULL DEFAULT '',
    corpo TEXT NOT NULL DEFAULT '',
    fixada INTEGER NOT NULL DEFAULT 0,
    criado_em TEXT NOT NULL,
    atualizado_em TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS comentarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    demanda_id INTEGER NOT NULL REFERENCES demandas(id) ON DELETE CASCADE,
    autor TEXT NOT NULL,
    texto TEXT NOT NULL,
    criado_em TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS checklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    demanda_id INTEGER NOT NULL REFERENCES demandas(id) ON DELETE CASCADE,
    texto TEXT NOT NULL,
    feito INTEGER NOT NULL DEFAULT 0,
    ordem INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS historico (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    demanda_id INTEGER NOT NULL REFERENCES demandas(id) ON DELETE CASCADE,
    tipo TEXT NOT NULL,
    descricao TEXT NOT NULL,
    autor TEXT NOT NULL,
    criado_em TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_hist_dem ON historico(demanda_id);
CREATE TABLE IF NOT EXISTS marcadores (
    nome TEXT PRIMARY KEY,
    cor TEXT
);
CREATE TABLE IF NOT EXISTS importacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    origem TEXT NOT NULL,
    arquivo TEXT,
    criado_em TEXT NOT NULL,
    ok INTEGER NOT NULL DEFAULT 1,
    novas INTEGER NOT NULL DEFAULT 0,
    atualizadas INTEGER NOT NULL DEFAULT 0,
    ocorrencias INTEGER NOT NULL DEFAULT 0,
    semanas INTEGER NOT NULL DEFAULT 0,
    resumo TEXT
);
SQL);
}

/* ---------- histórico ---------- */
function registrar(int $demandaId, string $tipo, string $descricao, ?string $autor = null): void
{
    db()->prepare('INSERT INTO historico (demanda_id, tipo, descricao, autor, criado_em) VALUES (?,?,?,?,?)')
        ->execute([$demandaId, $tipo, $descricao, $autor ?? eu(), agora()]);
}

/* ---------- segurança ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_t" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_t'] ?? ''))) {
        http_response_code(400);
        exit('Sessão expirada. Volte e tente de novo.');
    }
}
function senha_configurada(): bool { return (string) cfg('senha', '') !== ''; }
function senha_confere(string $digitada): bool
{
    $senha = (string) cfg('senha', '');
    if ($senha === '' || $digitada === '') return false;
    if (preg_match('~^\$(2y|argon2)~', $senha)) return password_verify($digitada, $senha);
    return hash_equals($senha, $digitada);
}
function exigir_login(): void
{
    if (!empty($_SESSION['logado'])) return;
    header('Location: login.php?volta=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    exit;
}

function voltar(string $url): void { header('Location: ' . $url); exit; }
function flash(?string $msg = null, string $tipo = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $tipo]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

/* ---------- datas ---------- */
function segunda_de(string $data): string
{
    $t = strtotime($data);
    $dow = (int) date('N', $t);
    return date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $t));
}
function mais_dias(string $data, int $n): string { return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days', strtotime($data))); }
function data_br(?string $d, bool $comDia = false): string
{
    if (!$d) return '';
    $t = strtotime($d);
    $s = date('d', $t) . ' ' . MESES_CURTOS[(int) date('n', $t)];
    if (date('Y', $t) !== date('Y')) $s .= ' ' . date('Y', $t);
    return $comDia ? DIAS_CURTOS[(int) date('w', $t)] . ', ' . $s : $s;
}
function data_hora_br(string $d): string
{
    $t = strtotime($d);
    if (date('Y-m-d', $t) === hoje()) return 'hoje, ' . date('H:i', $t);
    if (date('Y-m-d', $t) === mais_dias(hoje(), -1)) return 'ontem, ' . date('H:i', $t);
    return data_br(date('Y-m-d', $t)) . ', ' . date('H:i', $t);
}
function semana_label(string $segunda): string
{
    $sexta = mais_dias($segunda, 4);
    return data_br($segunda) . ' a ' . data_br($sexta);
}

/* ---------- marcadores ---------- */
function cor_marcador(?string $nome): string
{
    static $cores = null;
    if ($cores === null) {
        $cores = cfg('marcadores', []);
        try {
            foreach (db()->query('SELECT nome, cor FROM marcadores WHERE cor IS NOT NULL') as $r) $cores[$r['nome']] = $r['cor'];
        } catch (Throwable $e) {}
    }
    return $cores[$nome ?? ''] ?? '#5b5f57';
}
function chip_marcador(?string $nome): string
{
    if (!$nome) return '';
    return '<span class="chip" style="--c:' . h(cor_marcador($nome)) . '"><i></i>' . h($nome) . '</span>';
}
function pill_status(string $s): string { return '<span class="st st-' . h($s) . '">' . h(STATUS[$s] ?? $s) . '</span>'; }

/* ---------- consultas ---------- */
function pessoas(): array
{
    $l = db()->query('SELECT DISTINCT responsavel FROM demandas ORDER BY responsavel')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array(eu(), $l, true)) array_unshift($l, eu());
    usort($l, fn($a, $b) => ($b === eu()) <=> ($a === eu()) ?: strcmp($a, $b));
    return $l;
}

function ultima_importacao(?string $origem = null): ?array
{
    $sql = 'SELECT * FROM importacoes' . ($origem ? ' WHERE origem = ' . db()->quote($origem) : '') . ' ORDER BY id DESC LIMIT 1';
    return db()->query($sql)->fetch() ?: null;
}

/** Sincroniza sozinho se a última sincronização pela URL for antiga. Falha em silêncio. */
function auto_sync(): void
{
    $horas = (float) cfg('auto_sync_horas', 0);
    if ($horas <= 0 || !cfg('planilha_url')) return;
    $u = db()->query("SELECT criado_em FROM importacoes WHERE origem = 'link' ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($u && (time() - strtotime($u)) < $horas * 3600) return;
    if (!empty($_SESSION['auto_sync_tentou']) && time() - $_SESSION['auto_sync_tentou'] < 600) return;
    $_SESSION['auto_sync_tentou'] = time();
    try { Importer::sincronizarLink(); } catch (Throwable $e) { /* registrado em importacoes */ }
}
