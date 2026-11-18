<?php
// Recebe todas as alterações feitas na página da demanda.
require __DIR__ . '/lib/bootstrap.php';
exigir_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') voltar('index.php');
csrf_check();

$db = db();
$id = (int) ($_POST['id'] ?? 0);
$st = $db->prepare('SELECT * FROM demandas WHERE id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { flash('Demanda não encontrada.', 'erro'); voltar('demandas.php'); }

$acao = $_POST['acao'] ?? '';
$ancora = '';
$tocar = fn() => $db->prepare('UPDATE demandas SET atualizado_em = ? WHERE id = ?')->execute([agora(), $id]);
$curto = fn(string $s) => mb_strlen($s) > 70 ? mb_substr($s, 0, 70) . '…' : $s;

switch ($acao) {
    case 'dados':
        $mud = [];
        $novo = [
            'status' => isset(STATUS[$_POST['status'] ?? '']) ? $_POST['status'] : $d['status'],
            'prioridade' => isset(PRIORIDADES[$_POST['prioridade'] ?? '']) ? $_POST['prioridade'] : $d['prioridade'],
            'progresso' => max(0, min(100, (int) ($_POST['progresso'] ?? $d['progresso']))),
            'prazo' => preg_match('/^\d{4}-\d\d-\d\d$/', $_POST['prazo'] ?? '') ? $_POST['prazo'] : null,
        ];
        if ($novo['status'] === 'concluida' && $d['status'] !== 'concluida') $novo['progresso'] = 100;
        if ($novo['status'] !== $d['status']) $mud[] = 'Status: ' . STATUS[$d['status']] . ' → ' . STATUS[$novo['status']];
        if ($novo['prioridade'] !== $d['prioridade']) $mud[] = 'Prioridade: ' . PRIORIDADES[$d['prioridade']] . ' → ' . PRIORIDADES[$novo['prioridade']];
        if ($novo['progresso'] !== (int) $d['progresso']) $mud[] = 'Progresso: ' . $d['progresso'] . '% → ' . $novo['progresso'] . '%';
        if ($novo['prazo'] !== $d['prazo']) $mud[] = 'Prazo: ' . ($d['prazo'] ? data_br($d['prazo']) : 'sem prazo') . ' → ' . ($novo['prazo'] ? data_br($novo['prazo']) : 'sem prazo');
        if ($d['origem'] === 'manual') {
            $tit = trim((string) ($_POST['titulo'] ?? '')) ?: $d['titulo'];
            $marc = array_key_exists($_POST['marcador'] ?? '', cfg('marcadores')) ? $_POST['marcador'] : null;
            if ($tit !== $d['titulo']) $mud[] = 'Título: "' . $d['titulo'] . '" → "' . $tit . '"';
            if ($marc !== $d['marcador']) $mud[] = 'Marcador: ' . ($d['marcador'] ?: 'nenhum') . ' → ' . ($marc ?: 'nenhum');
            $db->prepare('UPDATE demandas SET titulo = ?, marcador = ? WHERE id = ?')->execute([$tit, $marc, $id]);
        }
        if ($mud) {
            $db->prepare('UPDATE demandas SET status = ?, prioridade = ?, progresso = ?, prazo = ?, atualizado_em = ? WHERE id = ?')
                ->execute([$novo['status'], $novo['prioridade'], $novo['progresso'], $novo['prazo'], agora(), $id]);
            foreach ($mud as $m) registrar($id, 'alteracao', $m . '.');
            flash('Alterações salvas.');
        }
        break;

    case 'status_rapido':
        $s = $_POST['status'] ?? '';
        if (isset(STATUS[$s]) && $s !== $d['status']) {
            $db->prepare('UPDATE demandas SET status = ?, progresso = CASE WHEN ? = \'concluida\' THEN 100 ELSE progresso END, atualizado_em = ? WHERE id = ?')->execute([$s, $s, agora(), $id]);
            registrar($id, 'alteracao', 'Status: ' . STATUS[$d['status']] . ' → ' . STATUS[$s] . '.');
        }
        break;

    case 'descricao':
        $t = trim((string) ($_POST['descricao'] ?? ''));
        if ($t !== $d['descricao']) {
            $db->prepare('UPDATE demandas SET descricao = ?, atualizado_em = ? WHERE id = ?')->execute([$t, agora(), $id]);
            registrar($id, 'alteracao', $d['descricao'] === '' ? 'Descrição adicionada.' : 'Descrição alterada.');
        }
        break;

    case 'nota_nova':
        $corpo = trim((string) ($_POST['corpo'] ?? ''));
        $tit = trim((string) ($_POST['titulo'] ?? ''));
        if ($corpo !== '' || $tit !== '') {
            $db->prepare('INSERT INTO notas (demanda_id, titulo, corpo, criado_em, atualizado_em) VALUES (?,?,?,?,?)')->execute([$id, $tit, $corpo, agora(), agora()]);
            registrar($id, 'nota', 'Anotação adicionada: ' . $curto($tit ?: $corpo));
            $tocar();
        }
        $ancora = '#notas';
        break;

    case 'nota_editar':
        $nid = (int) ($_POST['nota_id'] ?? 0);
        $corpo = trim((string) ($_POST['corpo'] ?? ''));
        $tit = trim((string) ($_POST['titulo'] ?? ''));
        $db->prepare('UPDATE notas SET titulo = ?, corpo = ?, atualizado_em = ? WHERE id = ? AND demanda_id = ?')->execute([$tit, $corpo, agora(), $nid, $id]);
        registrar($id, 'nota', 'Anotação editada: ' . $curto($tit ?: $corpo));
        $tocar();
        $ancora = '#notas';
        break;

    case 'nota_fixar':
        $db->prepare('UPDATE notas SET fixada = 1 - fixada WHERE id = ? AND demanda_id = ?')->execute([(int) ($_POST['nota_id'] ?? 0), $id]);
        $ancora = '#notas';
        break;

    case 'nota_excluir':
        $s = $db->prepare('SELECT titulo, corpo FROM notas WHERE id = ? AND demanda_id = ?');
        $s->execute([(int) ($_POST['nota_id'] ?? 0), $id]);
        if ($n = $s->fetch()) {
            $db->prepare('DELETE FROM notas WHERE id = ?')->execute([(int) $_POST['nota_id']]);
            registrar($id, 'nota', 'Anotação excluída: ' . $curto($n['titulo'] ?: $n['corpo']));
            $tocar();
        }
        $ancora = '#notas';
        break;

    case 'comentario':
        $t = trim((string) ($_POST['texto'] ?? ''));
        if ($t !== '') {
            $db->prepare('INSERT INTO comentarios (demanda_id, autor, texto, criado_em) VALUES (?,?,?,?)')->execute([$id, eu(), $t, agora()]);
            registrar($id, 'comentario', 'Comentário: ' . $curto($t));
            $tocar();
        }
        $ancora = '#comentarios';
        break;

    case 'comentario_excluir':
        $db->prepare('DELETE FROM comentarios WHERE id = ? AND demanda_id = ?')->execute([(int) ($_POST['comentario_id'] ?? 0), $id]);
        registrar($id, 'comentario', 'Comentário excluído.');
        $ancora = '#comentarios';
        break;

    case 'check_novo':
        $t = trim((string) ($_POST['texto'] ?? ''));
        if ($t !== '') {
            $db->prepare('INSERT INTO checklist (demanda_id, texto, ordem) VALUES (?,?,(SELECT COALESCE(MAX(ordem),0)+1 FROM checklist WHERE demanda_id = ?))')->execute([$id, $t, $id]);
            registrar($id, 'checklist', 'Item de checklist adicionado: ' . $curto($t));
            $tocar();
        }
        $ancora = '#checklist';
        break;

    case 'check_alternar':
        $s = $db->prepare('SELECT * FROM checklist WHERE id = ? AND demanda_id = ?');
        $s->execute([(int) ($_POST['item_id'] ?? 0), $id]);
        if ($k = $s->fetch()) {
            $db->prepare('UPDATE checklist SET feito = 1 - feito WHERE id = ?')->execute([$k['id']]);
            registrar($id, 'checklist', ($k['feito'] ? 'Item reaberto: ' : 'Item concluído: ') . $curto($k['texto']));
            $tocar();
        }
        $ancora = '#checklist';
        break;

    case 'check_excluir':
        $db->prepare('DELETE FROM checklist WHERE id = ? AND demanda_id = ?')->execute([(int) ($_POST['item_id'] ?? 0), $id]);
        $ancora = '#checklist';
        break;

    case 'excluir':
        if ($d['origem'] === 'manual') {
            $db->prepare('DELETE FROM demandas WHERE id = ?')->execute([$id]);
            flash('Demanda excluída.');
            voltar('demandas.php');
        }
        break;
}
voltar('demanda.php?id=' . $id . $ancora);
