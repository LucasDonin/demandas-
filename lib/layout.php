<?php
declare(strict_types=1);

function icone(string $n): string
{
    $p = [
        'painel'   => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'minhas'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'agenda'   => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'todas'    => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'importar' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'sync'     => '<path d="M21 12a9 9 0 0 1-15.5 6.2M3 12a9 9 0 0 1 15.5-6.2"/><path d="M21 4v5h-5M3 20v-5h5"/>',
        'mais'     => '<path d="M12 5v14M5 12h14"/>',
        'busca'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'nota'     => '<path d="M5 3h10l4 4v14H5z"/><path d="M9 12h6M9 16h6M9 8h3"/>',
        'coment'   => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
        'hist'     => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 3"/>',
        'check'    => '<path d="M4 12l5 5L20 6"/>',
        'lista'    => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9l2 2 3-3M7 15l2 2 3-3M14 10h4M14 16h4"/>',
        'sair'     => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'pin'      => '<path d="M9 3h6l-1 6 4 4H6l4-4z"/><path d="M12 13v8"/>',
        'lixo'     => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'editar'   => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'seta-e'   => '<path d="M15 5l-7 7 7 7"/>',
        'seta-d'   => '<path d="M9 5l7 7-7 7"/>',
        'alerta'   => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 18h.01"/>',
        'fogo'     => '<path d="M12 22c4 0 7-3 7-7 0-5-5-7-5-12-3 2-5 5-5 8-1-1-2-2-2-4-2 2-2 5-2 8 0 4 3 7 7 7z"/>',
        'relogio'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'pausa'    => '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
    ][$n] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function cabecalho(string $titulo, string $ativo = ''): void
{
    $f = flash();
    $ult = ultima_importacao();
    $nav = [
        'painel'   => ['index.php', 'Painel'],
        'minhas'   => ['demandas.php', 'Minhas demandas'],
        'agenda'   => ['agenda.php', 'Agenda da semana'],
        'todas'    => ['demandas.php?pessoa=todas', 'Todas as demandas'],
        'importar' => ['importar.php', 'Importar planilha'],
    ];
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> · Demandas</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body>
<div class="app">
  <aside class="side">
    <a class="logo" href="index.php"><span class="logo-mark"><?= icone('check') ?></span><span>Demandas</span></a>
    <nav>
      <?php foreach ($nav as $k => [$url, $rot]): ?>
        <a href="<?= h($url) ?>" class="<?= $ativo === $k ? 'on' : '' ?>"><?= icone($k) ?><span><?= h($rot) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <form method="post" action="importar.php" class="sync-mini">
        <?= csrf_field() ?><input type="hidden" name="acao" value="link">
        <button type="submit" title="Buscar a planilha agora"><?= icone('sync') ?><span>Sincronizar</span></button>
      </form>
      <small class="muted"><?php if ($ult): ?>Última importação <?= h(data_hora_br($ult['criado_em'])) ?><?= $ult['ok'] ? '' : ' (falhou)' ?><?php else: ?>Nenhuma importação ainda<?php endif; ?></small>
      <a class="sair" href="login.php?sair=1"><?= icone('sair') ?> Sair</a>
    </div>
  </aside>
  <main class="main">
    <header class="top">
      <form class="busca" action="demandas.php" method="get">
        <?= icone('busca') ?>
        <input type="hidden" name="pessoa" value="todas"><input type="hidden" name="status" value="todos">
        <input name="q" placeholder="Buscar demanda, anotação ou comentário" value="<?= h($_GET['q'] ?? '') ?>">
      </form>
      <a class="btn btn-lime" href="nova.php"><?= icone('mais') ?> Nova demanda</a>
    </header>
    <?php if ($f): ?><div class="flash flash-<?= h($f[1]) ?>"><?= h($f[0]) ?></div><?php endif; ?>
<?php
}

function rodape(): void
{
    ?>
  </main>
</div>
<script src="assets/app.js?v=1"></script>
</body>
</html>
<?php
}

/** Linha de demanda usada nas listas. */
function linha_demanda(array $d, bool $mostrarPessoa = false): string
{
    $atrasada = $d['prazo'] && $d['prazo'] < hoje() && in_array($d['status'], STATUS_ABERTOS, true);
    $quando = '';
    if (!empty($d['proxima'])) $quando = 'Próx. ' . data_br($d['proxima'], true);
    elseif ($d['ultima_data']) $quando = 'Últ. ' . data_br($d['ultima_data'], true);
    ob_start(); ?>
    <a class="row <?= $d['status'] === 'concluida' || $d['status'] === 'cancelada' ? 'row-feita' : '' ?>" href="demanda.php?id=<?= (int) $d['id'] ?>">
      <span class="row-dot" style="--c:<?= h(cor_marcador($d['marcador'])) ?>"></span>
      <span class="row-main">
        <span class="row-tit"><?= h($d['titulo']) ?></span>
        <span class="row-meta">
          <?php if ($mostrarPessoa): ?><span><?= h($d['responsavel']) ?></span><?php endif; ?>
          <?php if ($d['marcador']): ?><span><?= h($d['marcador']) ?></span><?php endif; ?>
          <?php if ($quando): ?><span><?= h($quando) ?></span><?php endif; ?>
          <?php if ($d['prazo']): ?><span class="<?= $atrasada ? 'txt-red' : '' ?>">Prazo <?= h(data_br($d['prazo'])) ?></span><?php endif; ?>
          <?php if ($d['tipo'] === 'reuniao'): ?><span>Reunião</span><?php endif; ?>
          <?php if ($d['origem'] === 'manual'): ?><span>Criada aqui</span><?php elseif (!$d['na_planilha']): ?><span class="txt-red">Fora da planilha</span><?php endif; ?>
          <?php if ($d['riscada']): ?><span>Riscada na planilha</span><?php endif; ?>
        </span>
      </span>
      <span class="row-ind">
        <?php if (($d['n_notas'] ?? 0) > 0): ?><span title="Anotações"><?= icone('nota') ?><?= (int) $d['n_notas'] ?></span><?php endif; ?>
        <?php if (($d['n_coment'] ?? 0) > 0): ?><span title="Comentários"><?= icone('coment') ?><?= (int) $d['n_coment'] ?></span><?php endif; ?>
        <?php if (($d['n_check'] ?? 0) > 0): ?><span title="Checklist"><?= icone('lista') ?><?= (int) $d['n_feito'] ?>/<?= (int) $d['n_check'] ?></span><?php endif; ?>
      </span>
      <span class="row-prog"><span class="bar"><i style="width:<?= (int) $d['progresso'] ?>%"></i></span></span>
      <?= pill_status($d['status']) ?>
    </a>
    <?php return ob_get_clean();
}

/** SQL base com contadores para listas. */
function sql_demandas(string $where = '1', string $order = 'd.atualizado_em DESC'): string
{
    $hoje = db()->quote(hoje());
    return "SELECT d.*,
        (SELECT COUNT(*) FROM notas n WHERE n.demanda_id = d.id) AS n_notas,
        (SELECT COUNT(*) FROM comentarios c WHERE c.demanda_id = d.id) AS n_coment,
        (SELECT COUNT(*) FROM checklist k WHERE k.demanda_id = d.id) AS n_check,
        (SELECT COUNT(*) FROM checklist k WHERE k.demanda_id = d.id AND k.feito = 1) AS n_feito,
        (SELECT MIN(o.data) FROM ocorrencias o WHERE o.demanda_id = d.id AND o.data >= $hoje) AS proxima
      FROM demandas d WHERE $where ORDER BY $order";
}
