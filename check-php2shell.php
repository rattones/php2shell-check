<?php
/**
 * check-php2shell.php — diagnóstico das CVE-2026-109618 / CVE-2026-109619 ("php2shell")
 *
 * Apenas LEITURA: mostra a versão do PHP e as configurações relevantes.
 * NÃO explora, NÃO altera nada, NÃO ataca o servidor.
 *
 * COMO USAR
 *   1. Troque o valor de TOKEN abaixo por uma senha sua.
 *   2. Suba este arquivo pela raiz do site (via FTP ou gerenciador de arquivos do painel).
 *   3. Acesse:  https://SEU-DOMINIO/check-php2shell.php?token=SUA_SENHA
 *   4. APAGUE o arquivo do servidor assim que terminar.
 */

$TOKEN = 'TROQUE_ESTA_SENHA';

if (!isset($_GET['token']) || !hash_equals($TOKEN, (string)$_GET['token'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

header('Content-Type: text/plain; charset=utf-8');

// Versões que já contêm a correção, por branch (major.minor => patch mínimo)
$FIXED = [
    '8.2' => 35,
    '8.3' => 36,
    '8.4' => 27,
    '8.5' => 12,
];

$ver   = PHP_VERSION;                 // ex.: "8.3.20"
$major = PHP_MAJOR_VERSION;
$minor = PHP_MINOR_VERSION;
$patch = PHP_RELEASE_VERSION;
$branch = "$major.$minor";

echo "===========================================================\n";
echo " Diagnóstico php2shell (CVE-2026-109618 / CVE-2026-109619)\n";
echo "===========================================================\n\n";
echo "Versão do PHP : $ver\n";
echo "SAPI          : " . PHP_SAPI . "\n\n";

echo "--- CVE-2026-109618 e 109619: a versão está corrigida? ---\n";

if ($major < 8 || ($major == 8 && $minor < 2)) {
    echo "RESULTADO: VULNERÁVEL — PHP $branch não recebe mais correção oficial.\n";
    echo "           Migre para PHP 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+.\n";
} elseif (isset($FIXED[$branch])) {
    if ($patch >= $FIXED[$branch]) {
        echo "RESULTADO: OK — versão igual ou superior à corrigida ($branch.{$FIXED[$branch]}).\n";
    } else {
        echo "RESULTADO: VULNERÁVEL — atualize para $branch.{$FIXED[$branch]} ou superior.\n";
    }
} else {
    // branch 8.6+ ou desconhecido
    echo "RESULTADO: verifique manualmente. Branch $branch não está na tabela;\n";
    echo "           confirme que é >= 8.6.0 final (RC4 já contém a correção).\n";
}

echo "\n--- CVE-2026-109619: mitigação de session.upload_progress ---\n";
$up = ini_get('session.upload_progress.enabled');
$upShown = ($up === false) ? '(indefinido)' : ($up ? 'On (1)' : 'Off (0)');
echo "session.upload_progress.enabled = $upShown\n";
if ($up) {
    echo "AÇÃO: defina session.upload_progress.enabled = 0 (php.ini / painel) e reinicie o PHP.\n";
} else {
    echo "OK: upload_progress desativado (mitigação já aplicada).\n";
}

echo "\n--- CVE-2026-109618: parâmetros de memória e tamanho de requisição ---\n";
printf("memory_limit        = %s\n", ini_get('memory_limit'));
printf("post_max_size       = %s\n", ini_get('post_max_size'));
printf("upload_max_filesize = %s\n", ini_get('upload_max_filesize'));

if ($minor >= 5 && $major == 8) {
    echo "\n--- PHP 8.5: mitigação extra ---\n";
    $feb = ini_get('fatal_error_backtraces');
    echo "fatal_error_backtraces = " . ($feb === false ? '(indefinido)' : $feb) . "\n";
    echo "(Recomendado 0 como mitigação temporária enquanto não atualiza.)\n";
}

echo "\n===========================================================\n";
echo " Lembre-se de APAGAR este arquivo do servidor ao terminar.\n";
echo "===========================================================\n";
