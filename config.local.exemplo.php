<?php
// Copie este arquivo para config.local.php e preencha. O config.local.php não vai para o Git.
// Qualquer chave de config.php pode ser sobrescrita aqui.
return [
    // Senha de login. Pode ser o texto da senha ou, melhor, um hash:
    //   php -r 'echo password_hash("sua-senha", PASSWORD_DEFAULT), PHP_EOL;'
    'senha' => '',

    // Token para chamar sync.php?token=... por um agendador web (opcional).
    'sync_token' => '',
];
