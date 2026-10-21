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
 *
 * Saída colorida em HTML (tema escuro). Para texto puro, acrescente &plain=1 na URL.
 */

$TOKEN = 'TROQUE_ESTA_SENHA';

if (!isset($_GET['token']) || !hash_equals($TOKEN, (string)$_GET['token'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

$PLAIN = isset($_GET['plain']);

// Versões que já contêm a correção, por branch (major.minor => patch mínimo)
$FIXED = [
    '8.2' => 35,
    '8.3' => 36,
    '8.4' => 27,
    '8.5' => 12,
];

$ver    = PHP_VERSION;                 // ex.: "8.3.20"
$major  = PHP_MAJOR_VERSION;
$minor  = PHP_MINOR_VERSION;
$patch  = PHP_RELEASE_VERSION;
$branch = "$major.$minor";

/* ---- coleta das linhas: cada item é [texto, tipo] ---- */
$L = [];
$sep = str_repeat('=', 59);

$L[] = [$sep, 'dim'];
$L[] = [' Diagnóstico php2shell (CVE-2026-109618 / CVE-2026-109619)', 'head'];
$L[] = [$sep, 'dim'];
$L[] = ['', 'fg'];
$L[] = ["Versão do PHP : $ver", 'fg'];
$L[] = ['SAPI          : ' . PHP_SAPI, 'fg'];
$L[] = ['', 'fg'];

$L[] = ['--- CVE-2026-109618 e 109619: a versão está corrigida? ---', 'head'];
if ($major < 8 || ($major == 8 && $minor < 2)) {
    $L[] = ["RESULTADO: VULNERÁVEL — PHP $branch não recebe mais correção oficial.", 'bad'];
    $L[] = ['           Migre para 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+.', 'bad'];
} elseif (isset($FIXED[$branch])) {
    if ($patch >= $FIXED[$branch]) {
        $L[] = ["RESULTADO: OK — versão igual ou superior à corrigida ($branch.{$FIXED[$branch]}).", 'ok'];
    } else {
        $L[] = ["RESULTADO: VULNERÁVEL — atualize para $branch.{$FIXED[$branch]} ou superior.", 'bad'];
    }
} else {
    $L[] = ["RESULTADO: verifique manualmente. Branch $branch não está na tabela;", 'warn'];
    $L[] = ['           confirme que é >= 8.6.0 final (RC4 já contém a correção).', 'warn'];
}
$L[] = ['', 'fg'];

$L[] = ['--- CVE-2026-109619: mitigação de session.upload_progress ---', 'head'];
$up = ini_get('session.upload_progress.enabled');
$upShown = ($up === false) ? '(indefinido)' : ($up ? 'On (1)' : 'Off (0)');
$L[] = ["session.upload_progress.enabled = $upShown", $up ? 'warn' : 'fg'];
if ($up) {
    $L[] = ['AÇÃO: defina session.upload_progress.enabled = 0 e reinicie o PHP.', 'warn'];
} else {
    $L[] = ['OK: upload_progress desativado (mitigação já aplicada).', 'ok'];
}
$L[] = ['', 'fg'];

$L[] = ['--- CVE-2026-109618: memória e tamanho de requisição ---', 'head'];
$L[] = ['memory_limit        = ' . ini_get('memory_limit'), 'fg'];
$L[] = ['post_max_size       = ' . ini_get('post_max_size'), 'fg'];
$L[] = ['upload_max_filesize = ' . ini_get('upload_max_filesize'), 'fg'];

if ($major == 8 && $minor >= 5) {
    $L[] = ['', 'fg'];
    $L[] = ['--- PHP 8.5: mitigação extra ---', 'head'];
    $feb = ini_get('fatal_error_backtraces');
    $L[] = ['fatal_error_backtraces = ' . ($feb === false ? '(indefinido)' : $feb), 'fg'];
    $L[] = ['(Recomendado 0 como mitigação temporária enquanto não atualiza.)', 'dim'];
}

$L[] = ['', 'fg'];
$L[] = [$sep, 'dim'];
$L[] = [' Lembre-se de APAGAR este arquivo do servidor ao terminar.', 'dim'];
$L[] = [$sep, 'dim'];

/* ---- saída em texto puro ---- */
if ($PLAIN) {
    header('Content-Type: text/plain; charset=utf-8');
    foreach ($L as $line) {
        echo $line[0] . "\n";
    }
    exit;
}

/* ---- saída colorida em HTML ---- */
$COLORS = [
    'dim'  => '#8b98a5',
    'fg'   => '#d7dde3',
    'head' => '#6ea8fe',
    'ok'   => '#3ddc84',
    'bad'  => '#ff6b6b',
    'warn' => '#ffd43b',
];
$BOLD = ['ok' => 1, 'bad' => 1, 'head' => 1];

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico php2shell</title>
<style>
  body { margin: 0; background: #0d1117; color: #d7dde3;
         font-family: ui-monospace, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", monospace; }
  .term { max-width: 920px; margin: 24px auto; background: #1b1f24; border-radius: 10px;
          overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,.4); }
  .bar { background: #2b2f36; height: 46px; display: flex; align-items: center; padding: 0 16px; gap: 8px; }
  .dot { width: 13px; height: 13px; border-radius: 50%; }
  .title { color: #9aa4ae; font-size: 12.5px; margin-left: auto; margin-right: auto; }
  pre { margin: 0; padding: 18px 22px 24px; font-size: 13.5px; line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
  pre span { display: block; }
</style>
</head>
<body>
  <div class="term">
    <div class="bar">
      <span class="dot" style="background:#ff5f56"></span>
      <span class="dot" style="background:#ffbd2e"></span>
      <span class="dot" style="background:#27c93f"></span>
      <span class="title">check-php2shell.php</span>
    </div>
    <pre><?php
    foreach ($L as $line) {
        $txt  = htmlspecialchars($line[0], ENT_QUOTES, 'UTF-8');
        $type = $line[1];
        $col  = $COLORS[$type] ?? $COLORS['fg'];
        $w    = isset($BOLD[$type]) ? 'font-weight:700;' : '';
        echo '<span style="color:' . $col . ';' . $w . '">' . ($txt === '' ? '&nbsp;' : $txt) . "</span>";
    }
    ?></pre>
  </div>
</body>
</html>
