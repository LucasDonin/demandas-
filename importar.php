<?php
require __DIR__ . '/lib/bootstrap.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['acao'] ?? '') === 'link') {
            $r = Importer::sincronizarLink();
        } else {
            $f = $_FILES['arquivo'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Escolha um arquivo .xlsx (ou .csv) para enviar.');
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'csv'], true)) throw new RuntimeException('Envie a planilha em .xlsx (Arquivo > Fazer download > Microsoft Excel).');
            $r = Importer::importarArquivo($f['tmp_name'], $f['name'], 'upload');
        }
        $msg = "Importação concluída: {$r['novas']} demandas novas, {$r['atualizadas']} com mudanças, {$r['semanas']} semanas lidas. Suas anotações foram preservadas.";
        if ($r['avisos']) $msg .= ' Avisos: ' . implode(' ', $r['avisos']);
        flash($msg);
    } catch (Throwable $e) {
        flash($e->getMessage(), 'erro');
    }
    voltar('importar.php');
}

$hist = db()->query('SELECT * FROM importacoes ORDER BY id DESC LIMIT 15')->fetchAll();
cabecalho('Importar planilha', 'importar');
?>
<div class="page-head"><div><h1>Importar planilha</h1><p class="muted">A importação só acrescenta e atualiza. Status, anotações, comentários, checklist e histórico nunca são apagados.</p></div></div>

<div class="grid g2">
  <section class="card">
    <h2><?= icone('sync') ?> Direto do Google Sheets</h2>
    <p class="muted">Busca a planilha pelo link configurado. Funciona enquanto ela estiver compartilhada como "qualquer pessoa com o link pode ver". O sistema também tenta sozinho a cada <?= h(cfg('auto_sync_horas')) ?> h quando você abre o painel.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="link">
      <button class="btn btn-lime" type="submit"><?= icone('sync') ?> Sincronizar agora</button>
    </form>
  </section>
  <section class="card">
    <h2><?= icone('importar') ?> Enviar arquivo</h2>
    <p class="muted">No Google Sheets: Arquivo, Fazer download, Microsoft Excel (.xlsx). O .xlsx traz as cores dos marcadores; o .csv funciona, mas sem marcadores.</p>
    <form method="post" enctype="multipart/form-data" class="upload"><?= csrf_field() ?>
      <label class="drop"><input type="file" name="arquivo" accept=".xlsx,.csv" required><span>Clique ou arraste o arquivo aqui</span></label>
      <button class="btn" type="submit">Importar arquivo</button>
    </form>
  </section>
</div>

<section class="card">
  <h2><?= icone('hist') ?> Últimas importações</h2>
  <?php if (!$hist): ?><p class="muted">Nenhuma importação ainda.</p><?php else: ?>
  <table class="tab">
    <thead><tr><th>Quando</th><th>Origem</th><th>Resultado</th></tr></thead>
    <tbody>
    <?php foreach ($hist as $i): ?>
      <tr>
        <td><?= h(data_hora_br($i['criado_em'])) ?></td>
        <td><?= $i['origem'] === 'link' ? 'Google Sheets' : 'Arquivo ' . h($i['arquivo']) ?></td>
        <td><?php if ($i['ok']): ?><?= (int) $i['novas'] ?> novas, <?= (int) $i['atualizadas'] ?> com mudanças, <?= (int) $i['semanas'] ?> semanas<?= $i['resumo'] ? '<br><span class="muted">' . nl2br(h($i['resumo'])) . '</span>' : '' ?><?php else: ?><span class="txt-red"><?= h($i['resumo']) ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php rodape();
