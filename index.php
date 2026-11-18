<?php
require __DIR__ . '/lib/bootstrap.php';
exigir_login();
auto_sync();

$db = db();
$eu = eu();
$hoje = hoje();
$seg = segunda_de($hoje);
$abertos = "'" . implode("','", STATUS_ABERTOS) . "'";
$q = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s; })();

$nAbertas   = (int) $q("SELECT COUNT(*) FROM demandas WHERE responsavel = ? AND status IN ($abertos)", [$eu])->fetchColumn();
$nAndamento = (int) $q("SELECT COUNT(*) FROM demandas WHERE responsavel = ? AND status = 'andamento'", [$eu])->fetchColumn();
$nAguard    = (int) $q("SELECT COUNT(*) FROM demandas WHERE responsavel = ? AND status = 'aguardando'", [$eu])->fetchColumn();
$nSemana    = (int) $q("SELECT COUNT(DISTINCT d.id) FROM demandas d JOIN ocorrencias o ON o.demanda_id = d.id WHERE d.responsavel = ? AND o.semana = ?", [$eu, $seg])->fetchColumn();
$nSemanaOk  = (int) $q("SELECT COUNT(DISTINCT d.id) FROM demandas d JOIN ocorrencias o ON o.demanda_id = d.id WHERE d.responsavel = ? AND o.semana = ? AND d.status IN ('concluida','cancelada')", [$eu, $seg])->fetchColumn();
$pct = $nSemana ? (int) round($nSemanaOk * 100 / $nSemana) : 0;

$deHoje = $q(sql_demandas("d.responsavel = ? AND d.id IN (SELECT demanda_id FROM ocorrencias WHERE data = ?)", "d.status IN ('concluida','cancelada'), d.titulo"), [$eu, $hoje])->fetchAll();
$atrasadas = $q(sql_demandas("d.responsavel = ? AND d.status IN ($abertos) AND ((d.prazo IS NOT NULL AND d.prazo < ?) OR (d.prazo IS NULL AND d.ultima_data IS NOT NULL AND d.ultima_data < ?))", 'COALESCE(d.prazo, d.ultima_data)'), [$eu, $hoje, $hoje])->fetchAll();
$prior = $q(sql_demandas("d.responsavel = ? AND d.status IN ($abertos) AND (d.marcador IN ('Prioridade I','Não prevista') OR d.prioridade = 'alta')", "d.marcador = 'Prioridade I' DESC, d.ultima_data"), [$eu])->fetchAll();
$ativ = $q("SELECT h.*, d.titulo FROM historico h JOIN demandas d ON d.id = h.demanda_id WHERE d.responsavel = ? ORDER BY h.id DESC LIMIT 8", [$eu])->fetchAll();

// tira de dias da semana
$porDia = [];
foreach ($q("SELECT o.data, COUNT(DISTINCT d.id) n, SUM(d.status IN ('concluida','cancelada')) ok FROM ocorrencias o JOIN demandas d ON d.id = o.demanda_id WHERE d.responsavel = ? AND o.semana = ? AND o.data IS NOT NULL GROUP BY o.data", [$eu, $seg]) as $r) $porDia[$r['data']] = $r;
$aus = [];
foreach ($q("SELECT data, texto FROM ausencias WHERE responsavel = ? AND semana = ?", [$eu, $seg]) as $r) $aus[$r['data']] = $r['texto'];

$hora = (int) date('G');
$saud = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
cabecalho('Painel', 'painel');
?>
<div class="page-head">
  <div><p class="muted"><?= h(DIAS_LONGOS[(int) date('w')]) ?>, <?= h(data_br($hoje)) ?></p><h1><?= h($saud) ?>, <?= h($eu) ?></h1></div>
</div>

<div class="grid hero">
  <section class="card card-lime">
    <div class="lime-top"><span class="tag-dark">Semana de <?= h(semana_label($seg)) ?></span><a class="tag-dark" href="agenda.php">Abrir agenda</a></div>
    <div class="lime-body">
      <div><div class="big"><?= $pct ?>%</div><div>da semana concluída</div><div class="lime-sub"><?= $nSemanaOk ?> de <?= $nSemana ?> demandas</div></div>
      <div class="ring" style="--p:<?= $pct ?>"></div>
    </div>
    <div class="bar bar-dark"><i style="width:<?= $pct ?>%"></i></div>
  </section>
  <a class="card stat" href="demandas.php"><span class="stat-ico"><?= icone('minhas') ?></span><div class="big"><?= $nAbertas ?></div><div class="muted">em aberto</div></a>
  <a class="card stat" href="demandas.php?status=andamento"><span class="stat-ico"><?= icone('relogio') ?></span><div class="big"><?= $nAndamento ?></div><div class="muted">em andamento</div></a>
  <a class="card stat" href="demandas.php?status=aguardando"><span class="stat-ico"><?= icone('pausa') ?></span><div class="big"><?= $nAguard ?></div><div class="muted">aguardando</div></a>
</div>

<div class="dias">
  <?php for ($i = 0; $i < 5; $i++): $d = mais_dias($seg, $i); $r = $porDia[$d] ?? null; ?>
    <a class="dia <?= $d === $hoje ? 'on' : '' ?>" href="agenda.php?semana=<?= h($seg) ?>#d<?= $i ?>">
      <span class="dia-nome"><?= h(DIAS_CURTOS[$i + 1]) ?></span>
      <span class="dia-num"><?= date('d', strtotime($d)) ?></span>
      <span class="dia-info"><?php if (isset($aus[$d])): ?><?= h($aus[$d]) ?><?php elseif ($r): ?><?= (int) $r['ok'] ?>/<?= (int) $r['n'] ?> feitas<?php else: ?>livre<?php endif; ?></span>
    </a>
  <?php endfor; ?>
</div>

<div class="grid g2">
  <section class="card">
    <h2><?= icone('agenda') ?> Para hoje <span class="count"><?= count($deHoje) ?></span></h2>
    <?php if (isset($aus[$hoje])): ?><p class="muted">Hoje está marcado como <?= h($aus[$hoje]) ?> na planilha.</p><?php endif; ?>
    <?php if (!$deHoje): ?><p class="vazio">Nada agendado para hoje na planilha.</p><?php endif; ?>
    <div class="rows"><?php foreach ($deHoje as $d) echo linha_demanda($d); ?></div>
  </section>
  <section class="card">
    <h2><?= icone('fogo') ?> Prioridades e não previstas <span class="count"><?= count($prior) ?></span></h2>
    <?php if (!$prior): ?><p class="vazio">Nenhuma prioridade em aberto.</p><?php endif; ?>
    <div class="rows"><?php foreach (array_slice($prior, 0, 8) as $d) echo linha_demanda($d); ?></div>
  </section>
  <section class="card">
    <h2><?= icone('alerta') ?> Ficaram para trás <span class="count"><?= count($atrasadas) ?></span></h2>
    <p class="muted small">Em aberto e com a última data na planilha (ou o prazo) já passada.</p>
    <?php if (!$atrasadas): ?><p class="vazio">Nada pendente de dias anteriores.</p><?php endif; ?>
    <div class="rows"><?php foreach (array_slice($atrasadas, 0, 8) as $d) echo linha_demanda($d); ?></div>
    <?php if (count($atrasadas) > 8): ?><a class="mais" href="demandas.php?filtro=atrasadas">Ver todas</a><?php endif; ?>
  </section>
  <section class="card">
    <h2><?= icone('hist') ?> Últimas alterações</h2>
    <?php if (!$ativ): ?><p class="vazio">Sem movimentações ainda.</p><?php endif; ?>
    <ul class="timeline">
      <?php foreach ($ativ as $a): ?>
        <li class="tl-<?= h($a['tipo']) ?>"><a href="demanda.php?id=<?= (int) $a['demanda_id'] ?>"><strong><?= h($a['titulo']) ?></strong></a><span><?= h($a['descricao']) ?></span><small class="muted"><?= h($a['autor']) ?> · <?= h(data_hora_br($a['criado_em'])) ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php rodape();
