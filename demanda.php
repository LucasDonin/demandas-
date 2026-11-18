<?php
require __DIR__ . '/lib/bootstrap.php';
exigir_login();

$db = db();
$id = (int) ($_GET['id'] ?? 0);
$st = $db->prepare('SELECT * FROM demandas WHERE id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { flash('Demanda não encontrada.', 'erro'); voltar('demandas.php'); }
$get = function (string $sql) use ($db, $id) { $s = $db->prepare($sql); $s->execute([$id]); return $s->fetchAll(); };
$notas  = $get('SELECT * FROM notas WHERE demanda_id = ? ORDER BY fixada DESC, id DESC');
$coms   = $get('SELECT * FROM comentarios WHERE demanda_id = ? ORDER BY id');
$checks = $get('SELECT * FROM checklist WHERE demanda_id = ? ORDER BY ordem, id');
$hist   = $get('SELECT * FROM historico WHERE demanda_id = ? ORDER BY id DESC');
$ocs    = $get('SELECT * FROM ocorrencias WHERE demanda_id = ? ORDER BY COALESCE(data, semana) DESC');
$feitos = count(array_filter($checks, fn($k) => $k['feito']));
$f = fn(string $acao, string $extra = '') => '<form method="post" action="acao.php" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="acao" value="' . $acao . '">' . $extra;
$manual = $d['origem'] === 'manual';

cabecalho($d['titulo'], $d['responsavel'] === eu() ? 'minhas' : 'todas');
?>
<a class="voltar" href="javascript:history.back()"><?= icone('seta-e') ?> Voltar</a>
<div class="page-head dem-head">
  <div>
    <div class="chips"><?= chip_marcador($d['marcador']) ?><?= pill_status($d['status']) ?>
      <span class="tag"><?= h($d['responsavel']) ?></span>
      <?php if ($d['tipo'] === 'reuniao'): ?><span class="tag">Reunião</span><?php endif; ?>
      <?php if ($manual): ?><span class="tag">Criada aqui</span><?php elseif (!$d['na_planilha']): ?><span class="tag tag-red">Não está mais na planilha</span><?php endif; ?>
      <?php if ($d['riscada']): ?><span class="tag">Riscada na planilha</span><?php endif; ?>
    </div>
    <h1><?= h($d['titulo']) ?></h1>
    <p class="muted">#<?= $id ?> · atualizada <?= h(data_hora_br($d['atualizado_em'])) ?></p>
  </div>
  <div class="rapido">
    <?php foreach (['andamento' => 'Iniciar', 'aguardando' => 'Aguardar', 'concluida' => 'Concluir'] as $s => $rot): if ($d['status'] !== $s): ?>
      <?= $f('status_rapido', '<input type="hidden" name="status" value="' . $s . '">') ?><button class="btn <?= $s === 'concluida' ? 'btn-lime' : '' ?>"><?= $rot ?></button></form>
    <?php endif; endforeach; ?>
  </div>
</div>

<div class="dem">
  <div class="dem-main">
    <section class="card" id="descricao">
      <h2><?= icone('editar') ?> Descrição</h2>
      <?= $f('descricao') ?>
        <textarea name="descricao" rows="3" placeholder="Contexto, objetivo, links, o que foi combinado..." data-auto><?= h($d['descricao']) ?></textarea>
        <button class="btn btn-sm so-sujo">Salvar descrição</button>
      </form>
    </section>

    <section class="card" id="notas">
      <h2><?= icone('nota') ?> Anotações <span class="count"><?= count($notas) ?></span></h2>
      <?= $f('nota_nova') ?>
        <div class="nota-nova">
          <input name="titulo" placeholder="Título (opcional)" maxlength="120">
          <textarea name="corpo" rows="2" placeholder="Escreva uma anotação..." data-auto></textarea>
          <button class="btn btn-lime btn-sm">Adicionar anotação</button>
        </div>
      </form>
      <div class="notas">
        <?php foreach ($notas as $n): ?>
          <article class="nota <?= $n['fixada'] ? 'fixada' : '' ?>">
            <header>
              <strong><?= h($n['titulo'] ?: 'Anotação') ?></strong>
              <small class="muted"><?= h(data_hora_br($n['atualizado_em'])) ?></small>
              <span class="nota-acoes">
                <?= $f('nota_fixar', '<input type="hidden" name="nota_id" value="' . (int) $n['id'] . '">') ?><button class="ib" title="<?= $n['fixada'] ? 'Desafixar' : 'Fixar no topo' ?>"><?= icone('pin') ?></button></form>
                <button type="button" class="ib" data-editar title="Editar"><?= icone('editar') ?></button>
                <?= $f('nota_excluir', '<input type="hidden" name="nota_id" value="' . (int) $n['id'] . '">') ?><button class="ib" title="Excluir" data-confirma="Excluir esta anotação?"><?= icone('lixo') ?></button></form>
              </span>
            </header>
            <div class="nota-corpo"><?= nl2br(h($n['corpo'])) ?></div>
            <?= str_replace('class="inline"', 'class="nota-edit" hidden', $f('nota_editar', '<input type="hidden" name="nota_id" value="' . (int) $n['id'] . '">')) ?>
              <input name="titulo" value="<?= h($n['titulo']) ?>" placeholder="Título (opcional)">
              <textarea name="corpo" rows="3" data-auto><?= h($n['corpo']) ?></textarea>
              <button class="btn btn-lime btn-sm">Salvar</button> <button type="button" class="btn btn-ghost btn-sm" data-cancelar>Cancelar</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card" id="checklist">
      <h2><?= icone('lista') ?> Checklist <span class="count"><?= $feitos ?>/<?= count($checks) ?></span></h2>
      <?php if ($checks): ?><div class="bar"><i style="width:<?= (int) round($feitos * 100 / count($checks)) ?>%"></i></div><?php endif; ?>
      <ul class="checks">
        <?php foreach ($checks as $k): ?>
          <li class="<?= $k['feito'] ? 'feito' : '' ?>">
            <?= $f('check_alternar', '<input type="hidden" name="item_id" value="' . (int) $k['id'] . '">') ?><button class="cb" aria-label="Marcar"><?= $k['feito'] ? icone('check') : '' ?></button></form>
            <span><?= h($k['texto']) ?></span>
            <?= $f('check_excluir', '<input type="hidden" name="item_id" value="' . (int) $k['id'] . '">') ?><button class="ib" title="Remover"><?= icone('lixo') ?></button></form>
          </li>
        <?php endforeach; ?>
      </ul>
      <?= str_replace('class="inline"', 'class="linha"', $f('check_novo')) ?><input name="texto" placeholder="Novo item..." required><button class="btn btn-sm">Adicionar</button></form>
    </section>

    <section class="card" id="comentarios">
      <h2><?= icone('coment') ?> Comentários <span class="count"><?= count($coms) ?></span></h2>
      <div class="coms">
        <?php foreach ($coms as $c): ?>
          <div class="com">
            <span class="avatar"><?= h(mb_strtoupper(mb_substr($c['autor'], 0, 1))) ?></span>
            <div><header><strong><?= h($c['autor']) ?></strong><small class="muted"><?= h(data_hora_br($c['criado_em'])) ?></small>
              <?= $f('comentario_excluir', '<input type="hidden" name="comentario_id" value="' . (int) $c['id'] . '">') ?><button class="ib" title="Excluir" data-confirma="Excluir este comentário?"><?= icone('lixo') ?></button></form></header>
              <p><?= nl2br(h($c['texto'])) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?= str_replace('class="inline"', 'class="com-novo"', $f('comentario')) ?>
        <textarea name="texto" rows="2" placeholder="Registrar um comentário, retorno de alguém, decisão..." required data-auto></textarea>
        <button class="btn btn-lime btn-sm">Comentar</button>
      </form>
    </section>
  </div>

  <aside class="dem-side">
    <section class="card">
      <h2>Acompanhamento</h2>
      <?= str_replace('class="inline"', 'class="form"', $f('dados')) ?>
        <?php if ($manual): ?>
          <label>Título<input name="titulo" value="<?= h($d['titulo']) ?>"></label>
          <label>Marcador<select name="marcador"><option value="">Nenhum</option><?php foreach (array_keys(cfg('marcadores')) as $m): ?><option <?= $d['marcador'] === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <label>Status<select name="status"><?php foreach (STATUS as $k => $v): ?><option value="<?= $k ?>" <?= $d['status'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
        <label>Minha prioridade<select name="prioridade"><?php foreach (PRIORIDADES as $k => $v): ?><option value="<?= $k ?>" <?= $d['prioridade'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
        <label>Prazo<input type="date" name="prazo" value="<?= h($d['prazo']) ?>"></label>
        <label>Progresso <output><?= (int) $d['progresso'] ?>%</output><input type="range" name="progresso" min="0" max="100" step="5" value="<?= (int) $d['progresso'] ?>" oninput="this.previousElementSibling.textContent=this.value+'%'"></label>
        <button class="btn btn-lime">Salvar</button>
      </form>
    </section>

    <section class="card">
      <h2><?= icone('agenda') ?> Na planilha</h2>
      <?php if (!$ocs): ?><p class="muted small"><?= $manual ? 'Demanda criada aqui, não vem da planilha.' : 'Não aparece mais em nenhuma semana da planilha.' ?></p><?php endif; ?>
      <ul class="ocs">
        <?php foreach ($ocs as $o): ?>
          <li><a href="agenda.php?semana=<?= h($o['semana']) ?>&pessoa=<?= h(urlencode($d['responsavel'])) ?>"><span class="row-dot" style="--c:<?= h(cor_marcador($o['marcador'])) ?>"></span>
            <?= $o['recorrente'] ? 'Recorrente, semana de ' . h(data_br($o['semana'])) : h(data_br($o['data'], true)) ?></a>
            <small class="muted"><?= h($o['marcador'] ?: '') ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card" id="historico">
      <h2><?= icone('hist') ?> Histórico de alterações</h2>
      <ul class="timeline">
        <?php foreach ($hist as $a): ?>
          <li class="tl-<?= h($a['tipo']) ?>"><span><?= h($a['descricao']) ?></span><small class="muted"><?= h($a['autor']) ?> · <?= h(data_hora_br($a['criado_em'])) ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if ($manual): ?>
      <?= $f('excluir') ?><button class="btn btn-ghost txt-red" data-confirma="Excluir esta demanda e tudo o que foi anotado nela?"><?= icone('lixo') ?> Excluir demanda</button></form>
    <?php endif; ?>
  </aside>
</div>
<?php rodape();
