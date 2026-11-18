<?php
// Sincronização agendada. Exemplos:
//   cron:  0 7-19 * * 1-5  php /caminho/demandas/sync.php
//   web:   https://seu-site/demandas/sync.php?token=SEU_TOKEN
require __DIR__ . '/lib/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    $t = (string) cfg('sync_token', '');
    if ($t === '' || !hash_equals($t, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit("Token inválido. Defina sync_token em config.local.php ou DEMANDAS_SYNC_TOKEN.\n");
    }
}
try {
    $r = Importer::sincronizarLink();
    echo "OK: {$r['novas']} novas, {$r['atualizadas']} atualizadas, {$r['ocorrencias']} itens em {$r['semanas']} semanas.\n";
} catch (Throwable $e) {
    if (PHP_SAPI !== 'cli') http_response_code(500);
    echo 'ERRO: ' . $e->getMessage() . "\n";
    exit(1);
}
