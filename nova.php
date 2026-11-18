<?php
require __DIR__ . '/lib/bootstrap.php';
exigir_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tit = trim((string) ($_POST['titulo'] ?? ''));
    if ($tit === '') { flash('Dê um título para a demanda.', 'erro'); voltar('nova.php'); }
    $resp = trim((string) ($_POST['responsavel'] ?? '')) ?: eu();
    $prazo = preg_match('/^\d{4}-\d\d-\d\d$/', $_POST['prazo'] ?? '') ? $_POST['prazo'] : null;
    $marc = array_key_exists($_POST['marcador'] ?? '', cfg('marcadores')) ? $_POST['marcador'] : null;
    db()->prepare('INSERT INTO demandas (chave, titulo, responsavel, origem, marcador, prazo, descricao, na_planilha, criado_em, atualizado_em) VALUES (NULL,?,?,?,?,?,?,0,?,?)')
        ->execute([$tit, $resp, 'manual', $marc, $prazo, trim((string) ($_POST['descricao'] ?? '')), agora(), agora()]);
    $id = (int) db()->lastInsertId();
    registrar($id, 'criacao', 'Demanda criada no sistema.');
    voltar('demanda.php?id=' . $id);
}
cabecalho('Nova demanda');
?>
<div class="page-head"><div><h1>Nova demanda</h1><p class="muted">Para o que chega por fora da planilha. Ela fica só aqui no sistema.</p></div></div>
<form method="post" class="card form"><?= csrf_field() ?>
  <label>Título<input name="titulo" required autofocus maxlength="200"></label>
  <div class="grid g3">
    <label>Responsável<select name="responsavel"><?php foreach (pessoas() as $n): ?><option><?= h($n) ?></option><?php endforeach; ?></select></label>
    <label>Marcador<select name="marcador"><option value="">Nenhum</option><?php foreach (array_keys(cfg('marcadores')) as $m): ?><option><?= h($m) ?></option><?php endforeach; ?></select></label>
    <label>Prazo<input type="date" name="prazo"></label>
  </div>
  <label>Descrição<textarea name="descricao" rows="4"></textarea></label>
  <div><button class="btn btn-lime" type="submit">Criar demanda</button> <a class="btn btn-ghost" href="demandas.php">Cancelar</a></div>
</form>
<?php rodape();
