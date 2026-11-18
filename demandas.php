<?php
require __DIR__ . '/lib/bootstrap.php';
exigir_login();

$db = db();
$pessoa = $_GET['pessoa'] ?? eu();
$status = $_GET['status'] ?? 'abertas';
$marc   = $_GET['marcador'] ?? '';
$filtro = $_GET['filtro'] ?? '';
$busca  = trim((string) ($_GET['q'] ?? ''));
$ordem  = $_GET['ordem'] ?? 'data';

$w = ['1']; $p = [];
if ($pessoa !== 'todas') { $w[] = 'd.responsavel = ?'; $p[] = $pessoa; }
if ($status === 'abertas') $w[] = "d.status IN ('nova','andamento','aguardando')";
elseif (isset(STATUS[$status])) { $w[] = 'd.status = ?'; $p[] = $status; }
if ($marc === '-') $w[] = 'd.marcador IS NULL';
elseif ($marc !== '') { $w[] = 'd.marcador = ?'; $p[] = $marc; }
if ($filtro === 'atrasadas') { $w[] = '((d.prazo IS NOT NULL AND d.prazo < ?) OR (d.prazo IS NULL AND d.ultima_data < ?))'; $p[] = hoje(); $p[] = hoje(); }
if ($busca !== '') {
    $w[] = '(d.titulo LIKE ? OR d.descricao LIKE ? OR d.id IN (SELECT demanda_id FROM notas WHERE corpo LIKE ? OR titulo LIKE ?) OR d.id IN (SELECT demanda_id FROM comentarios WHERE texto LIKE ?))';
    array_push($p, ...array_fill(0, 5, '%' . $busca . '%'));
}
$ordens = [
    'data'   => ['Data na planilha', 'd.ultima_data IS NULL, d.ultima_data DESC, d.titulo'],
    'recente'=> ['Alteração recente', 'd.atualizado_em DESC'],
    'prazo'  => ['Prazo', 'd.prazo IS NULL, d.prazo, d.titulo'],
    'titulo' => ['Título', 'd.titulo COLLATE NOCASE'],
];
if (!isset($ordens[$ordem])) $ordem = 'data';
$st = $db->prepare(sql_demandas(implode(' AND ', $w), $ordens[$ordem][1]));
$st->execute($p);
$lista = $st->fetchAll();

$url = function (array $troca) {
    $g = array_merge($_GET, $troca);
    $g = array_filter($g, fn($v) => $v !== '' && $v !== null);
    return 'demandas.php' . ($g ? '?' . http_build_query($g) : '');
};
$todas = $pessoa === 'todas';
cabecalho($todas ? 'Todas as demandas' : 'Minhas demandas', $pessoa === eu() ? 'minhas' : ($todas ? 'todas' : ''));
?>
<div class="page-head">
  <div><h1><?= $todas ? 'Todas as demandas' : ($pessoa === eu() ? 'Minhas demandas' : 'Demandas de ' . h($pessoa)) ?></h1>
  <p class="muted"><?= count($lista) ?> <?= count($lista) === 1 ? 'demanda' : 'demandas' ?><?= $busca !== '' ? ' para "' . h($busca) . '"' : '' ?></p></div>
</div>

<div class="filtros">
  <div class="seg">
    <?php foreach (pessoas() as $n): ?><a class="<?= $pessoa === $n ? 'on' : '' ?>" href="<?= h($url(['pessoa' => $n])) ?>"><?= h($n === eu() ? 'Eu' : $n) ?></a><?php endforeach; ?>
    <a class="<?= $todas ? 'on' : '' ?>" href="<?= h($url(['pessoa' => 'todas'])) ?>">Todos</a>
  </div>
  <div class="seg">
    <a class="<?= $status === 'abertas' ? 'on' : '' ?>" href="<?= h($url(['status' => 'abertas'])) ?>">Em aberto</a>
    <?php foreach (STATUS as $k => $v): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= h($url(['status' => $k])) ?>"><?= h($v) ?></a><?php endforeach; ?>
    <a class="<?= $status === 'todos' ? 'on' : '' ?>" href="<?= h($url(['status' => 'todos'])) ?>">Todos</a>
  </div>
  <form method="get" class="selects">
    <?php foreach (['pessoa' => $pessoa, 'status' => $status, 'q' => $busca, 'filtro' => $filtro] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= h($v) ?>"><?php endif; endforeach; ?>
    <select name="marcador" onchange="this.form.submit()">
      <option value="">Todos os marcadores</option>
      <?php foreach (array_keys(cfg('marcadores')) as $m): ?><option <?= $marc === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?>
      <option value="-" <?= $marc === '-' ? 'selected' : '' ?>>Sem marcador</option>
    </select>
    <select name="ordem" onchange="this.form.submit()">
      <?php foreach ($ordens as $k => $o): ?><option value="<?= $k ?>" <?= $ordem === $k ? 'selected' : '' ?>>Ordenar: <?= h($o[0]) ?></option><?php endforeach; ?>
    </select>
    <?php if ($filtro || $busca !== '' || $marc !== ''): ?><a class="btn btn-ghost" href="demandas.php?pessoa=<?= h(urlencode($pessoa)) ?>">Limpar filtros</a><?php endif; ?>
  </form>
</div>

<section class="card">
  <?php if (!$lista): ?><p class="vazio">Nenhuma demanda com esses filtros.<?php if (!ultima_importacao()): ?> <a href="importar.php">Importe a planilha</a> para começar.<?php endif; ?></p><?php endif; ?>
  <div class="rows"><?php foreach ($lista as $d) echo linha_demanda($d, $todas); ?></div>
</section>
<?php rodape();
