<?php
require __DIR__ . '/lib/bootstrap.php';
exigir_login();

$db = db();
$seg = segunda_de(preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['semana'] ?? '') ? $_GET['semana'] : hoje());
$pessoa = $_GET['pessoa'] ?? eu();
$pes = $pessoa === 'todas' ? pessoas() : [$pessoa];

$st = $db->prepare("SELECT o.data, o.recorrente, o.marcador AS oc_marcador, d.id, d.titulo, d.status, d.responsavel, d.progresso, d.tipo,
    (SELECT COUNT(*) FROM notas n WHERE n.demanda_id = d.id) + (SELECT COUNT(*) FROM comentarios c WHERE c.demanda_id = d.id) AS n_anot
  FROM ocorrencias o JOIN demandas d ON d.id = o.demanda_id WHERE o.semana = ? ORDER BY o.id");
$st->execute([$seg]);
$grade = [];
foreach ($st as $r) $grade[$r['responsavel']][$r['recorrente'] ? 'R' : $r['data']][] = $r;
$st = $db->prepare('SELECT * FROM ausencias WHERE semana = ?');
$st->execute([$seg]);
$aus = [];
foreach ($st as $r) $aus[$r['responsavel']][$r['data']] = $r['texto'];
$temRec = false;
foreach ($pes as $n) if (!empty($grade[$n]['R'])) $temRec = true;

cabecalho('Agenda da semana', 'agenda');
?>
<div class="page-head">
  <div><h1>Agenda da semana</h1><p class="muted">Sprint de <?= h(semana_label($seg)) ?>, como está na planilha</p></div>
  <div class="nav-sem">
    <a class="btn btn-ghost" href="?semana=<?= h(mais_dias($seg, -7)) ?>&pessoa=<?= h(urlencode($pessoa)) ?>"><?= icone('seta-e') ?></a>
    <a class="btn btn-ghost" href="?pessoa=<?= h(urlencode($pessoa)) ?>">Esta semana</a>
    <a class="btn btn-ghost" href="?semana=<?= h(mais_dias($seg, 7)) ?>&pessoa=<?= h(urlencode($pessoa)) ?>"><?= icone('seta-d') ?></a>
  </div>
</div>
<div class="filtros">
  <div class="seg">
    <?php foreach (pessoas() as $n): ?><a class="<?= $pessoa === $n ? 'on' : '' ?>" href="?semana=<?= h($seg) ?>&pessoa=<?= h(urlencode($n)) ?>"><?= h($n === eu() ? 'Eu' : $n) ?></a><?php endforeach; ?>
    <a class="<?= $pessoa === 'todas' ? 'on' : '' ?>" href="?semana=<?= h($seg) ?>&pessoa=todas">Todos</a>
  </div>
  <div class="legenda"><?php foreach (array_keys(cfg('marcadores')) as $m) echo chip_marcador($m); ?></div>
</div>

<?php foreach ($pes as $n): ?>
  <?php if (count($pes) > 1): ?><h2 class="sec"><?= h($n) ?></h2><?php endif; ?>
  <div class="semana <?= $temRec ? 'com-rec' : '' ?>">
    <?php for ($i = 0; $i < 5; $i++): $d = mais_dias($seg, $i); $itens = $grade[$n][$d] ?? []; ?>
      <section class="col <?= $d === hoje() ? 'hoje' : '' ?>" id="d<?= $i ?>">
        <header><strong><?= h(DIAS_LONGOS[$i + 1]) ?></strong><span><?= h(data_br($d)) ?></span></header>
        <?php if (isset($aus[$n][$d])): ?><div class="ausencia"><?= h($aus[$n][$d]) ?></div><?php endif; ?>
        <?php foreach ($itens as $it): ?>
          <a class="tk <?= in_array($it['status'], ['concluida', 'cancelada'], true) ? 'tk-feita' : '' ?>" style="--c:<?= h(cor_marcador($it['oc_marcador'])) ?>" href="demanda.php?id=<?= (int) $it['id'] ?>" title="<?= h($it['oc_marcador'] ?: 'Sem marcador') ?>">
            <span class="tk-tit"><?= h($it['titulo']) ?></span>
            <span class="tk-meta"><?= pill_status($it['status']) ?><?php if ($it['n_anot']): ?><span class="muted"><?= icone('nota') ?><?= (int) $it['n_anot'] ?></span><?php endif; ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (!$itens && !isset($aus[$n][$d])): ?><p class="vazio small">Sem demandas</p><?php endif; ?>
      </section>
    <?php endfor; ?>
    <?php if ($temRec): ?>
      <section class="col"><header><strong>Recorrente</strong><span>toda a semana</span></header>
        <?php foreach ($grade[$n]['R'] ?? [] as $it): ?>
          <a class="tk" style="--c:<?= h(cor_marcador($it['oc_marcador'])) ?>" href="demanda.php?id=<?= (int) $it['id'] ?>"><span class="tk-tit"><?= h($it['titulo']) ?></span><span class="tk-meta"><?= pill_status($it['status']) ?></span></a>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php rodape();
