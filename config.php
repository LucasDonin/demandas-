<?php
// Configurações do sistema de demandas.
// A senha e o token NÃO ficam aqui: defina em config.local.php (fora do Git)
// ou nas variáveis de ambiente DEMANDAS_SENHA e DEMANDAS_SYNC_TOKEN.
// Veja config.local.exemplo.php.
$config = [
    // Seu nome exatamente como aparece na coluna "Responsável" da planilha.
    'meu_nome' => 'Lucas',

    // Senha de acesso (obrigatória). Pode ser o texto da senha ou um hash gerado com:
    //   php -r 'echo password_hash("sua-senha", PASSWORD_DEFAULT), PHP_EOL;'
    'senha' => (string) (getenv('DEMANDAS_SENHA') ?: ''),

    // Link da planilha do Google. Para a sincronização automática funcionar,
    // a planilha precisa estar como "Qualquer pessoa com o link pode ver".
    'planilha_url' => 'https://docs.google.com/spreadsheets/d/1yHPEz8u1CENM8MvCaIKERYRV4Mf4uRN-U6_JtwBvD1s/edit',

    // Sincroniza sozinho ao abrir o sistema se a última sincronização tiver mais que X horas. 0 desliga.
    'auto_sync_horas' => 2,

    // Token para agendar a sincronização por cron: sync.php?token=...
    'sync_token' => (string) (getenv('DEMANDAS_SYNC_TOKEN') ?: ''),

    // Banco SQLite (a pasta data/ precisa ter permissão de escrita).
    'db' => __DIR__ . '/data/demandas.sqlite',

    // Marcadores da legenda da planilha e uma cor reserva (a cor real vem da planilha ao importar .xlsx).
    'marcadores' => [
        'Padrão'        => '#a3a8a0',
        'Não prevista'  => '#ff9b54',
        'Prioridade I'  => '#ff5c7a',
        'Prioridade II' => '#ffd166',
        'Postergado'    => '#9d8cff',
        'Outros'        => '#5cc8ff',
    ],

    // Textos que não viram demanda (aparecem só como ausência na agenda).
    'ausencias' => ['Feriado', 'Atestado', 'Férias', 'Ferias', 'Folga', 'Banco de horas', 'Recesso'],

    'timezone' => 'America/Sao_Paulo',
];

// Sobrescritas locais (senha, token etc.). Esse arquivo não vai para o Git.
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, (array) require __DIR__ . '/config.local.php');
}
return $config;
