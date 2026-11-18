<?php
require __DIR__ . '/lib/bootstrap.php';
if (isset($_GET['sair'])) { $_SESSION = []; session_destroy(); voltar('login.php'); }
if (!empty($_SESSION['logado'])) voltar('index.php');
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && senha_configurada()) {
    if (senha_confere((string) ($_POST['senha'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['logado'] = true;
        $v = (string) ($_POST['volta'] ?? 'index.php');
        if (!preg_match('~^[\w\-./?=&%]+$~', $v) || str_contains($v, '//')) $v = 'index.php';
        voltar($v);
    }
    usleep(600000);
    $erro = 'Senha incorreta.';
}
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Demandas</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=1"></head>
<body class="login-body">
<form method="post" class="card login">
  <span class="logo-mark"><?= icone('check') ?></span>
  <h1>Demandas</h1>
  <p class="muted">Olá, <?= h(eu()) ?>. Digite a senha para entrar.</p>
  <?php if ($erro): ?><div class="flash flash-erro"><?= h($erro) ?></div><?php endif; ?>
  <?php if (!senha_configurada()): ?>
  <div class="flash flash-erro">A senha ainda não foi definida. Crie o arquivo <code>config.local.php</code> (copie de <code>config.local.exemplo.php</code>) ou defina a variável de ambiente <code>DEMANDAS_SENHA</code>.</div>
  <?php else: ?>
  <input type="hidden" name="volta" value="<?= h($_GET['volta'] ?? $_POST['volta'] ?? 'index.php') ?>">
  <input type="password" name="senha" placeholder="Senha" autofocus required>
  <button class="btn btn-lime" type="submit">Entrar</button>
  <?php endif; ?>
</form>
</body></html>
