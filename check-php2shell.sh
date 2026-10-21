#!/usr/bin/env bash
#
# check-php2shell.sh — diagnóstico das CVE-2026-109618 / CVE-2026-109619 ("php2shell")
#
# Apenas LEITURA: mostra a versão do PHP e as configurações relevantes.
# NÃO explora, NÃO altera nada, NÃO ataca o servidor. Use só em servidores que você administra.
#
# USO (com acesso ao shell/SSH):
#   chmod +x check-php2shell.sh
#   ./check-php2shell.sh
#
# Saída colorida por padrão. Desative com:  NO_COLOR=1 ./check-php2shell.sh
#
set -euo pipefail

# ---- cores ANSI (desativadas se não for terminal ou se NO_COLOR estiver definido) ----
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
  C_RESET=$'\033[0m'; C_DIM=$'\033[90m'; C_FG=$'\033[0m'
  C_HEAD=$'\033[1;34m'; C_OK=$'\033[1;32m'; C_BAD=$'\033[1;31m'; C_WARN=$'\033[33m'
  C_PROMPT=$'\033[32m'
else
  C_RESET=''; C_DIM=''; C_FG=''; C_HEAD=''; C_OK=''; C_BAD=''; C_WARN=''; C_PROMPT=''
fi

say() { printf '%s%s%s\n' "$2" "$1" "$C_RESET"; }   # say "texto" "$COR"

# Versões que já contêm a correção, por branch
FIXED_82=35; FIXED_83=36; FIXED_84=27; FIXED_85=12
SEP="==========================================================="

say "$SEP" "$C_DIM"
say " Diagnostico php2shell (CVE-2026-109618 / CVE-2026-109619)" "$C_HEAD"
say "$SEP" "$C_DIM"
echo

if ! command -v php >/dev/null 2>&1; then
  say "php (CLI) nao encontrado no PATH." "$C_BAD"
  echo "Tente localizar o binario: ls /usr/bin/php* /opt/*/bin/php* 2>/dev/null"
  exit 1
fi

VER="$(php -r 'echo PHP_VERSION;')"
MAJOR="$(php -r 'echo PHP_MAJOR_VERSION;')"
MINOR="$(php -r 'echo PHP_MINOR_VERSION;')"
PATCH="$(php -r 'echo PHP_RELEASE_VERSION;')"
SAPI="$(php -r 'echo PHP_SAPI;')"
BRANCH="${MAJOR}.${MINOR}"

say "Versao do PHP (CLI) : $VER" "$C_FG"
say "SAPI                : $SAPI" "$C_FG"
say "(Atencao: a versao do CLI pode diferir da usada pelo site — veja PHP-FPM abaixo.)" "$C_DIM"
echo

say "--- A versao esta corrigida? ---" "$C_HEAD"
fixed_patch=""
case "$BRANCH" in
  8.2) fixed_patch=$FIXED_82 ;;
  8.3) fixed_patch=$FIXED_83 ;;
  8.4) fixed_patch=$FIXED_84 ;;
  8.5) fixed_patch=$FIXED_85 ;;
esac

if [ "$MAJOR" -lt 8 ] || { [ "$MAJOR" -eq 8 ] && [ "$MINOR" -lt 2 ]; }; then
  say "RESULTADO: VULNERAVEL — PHP $BRANCH nao recebe mais correcao oficial." "$C_BAD"
  say "           Migre para 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+." "$C_BAD"
elif [ -n "$fixed_patch" ]; then
  if [ "$PATCH" -ge "$fixed_patch" ]; then
    say "RESULTADO: OK — versao igual ou superior a corrigida ($BRANCH.$fixed_patch)." "$C_OK"
  else
    say "RESULTADO: VULNERAVEL — atualize para $BRANCH.$fixed_patch ou superior." "$C_BAD"
  fi
else
  say "RESULTADO: verifique manualmente. Branch $BRANCH nao esta na tabela;" "$C_WARN"
  say "           confirme que e >= 8.6.0 final (RC4 ja contem a correcao)." "$C_WARN"
fi
echo

say "--- Configuracoes relevantes (php -i) ---" "$C_HEAD"
php -i 2>/dev/null | grep -i -E \
  'session.upload_progress.enabled|memory_limit|post_max_size|upload_max_filesize|fatal_error_backtraces' \
  | while IFS= read -r line; do
      low="$(printf '%s' "$line" | tr '[:upper:]' '[:lower:]')"
      if printf '%s' "$low" | grep -q 'upload_progress.enabled'; then
        if printf '%s' "$low" | grep -qE '=> *on'; then
          say "$line   << ATIVO (mitigar: defina 0)" "$C_WARN"
        else
          say "$line" "$C_OK"
        fi
      else
        say "$line" "$C_FG"
      fi
    done || say "(nenhum parametro encontrado)" "$C_DIM"
echo

say "--- Versoes de PHP-FPM / modulos instalados ---" "$C_HEAD"
for b in php-fpm php8.2-fpm php8.3-fpm php8.4-fpm php8.5-fpm; do
  if command -v "$b" >/dev/null 2>&1; then
    say "-- $b --" "$C_DIM"
    "$b" -v 2>/dev/null | head -1
  fi
done
echo

say "--- Pacotes PHP instalados ---" "$C_HEAD"
if command -v dpkg >/dev/null 2>&1; then
  dpkg -l 2>/dev/null | grep -i '^ii.*php' | awk '{print $2, $3}' || true
elif command -v rpm >/dev/null 2>&1; then
  rpm -qa 2>/dev/null | grep -i php || true
fi
echo

say "Lembrete: session.upload_progress.enabled deve estar Off (0) (mitigacao da 109619)." "$C_DIM"
say "$SEP" "$C_DIM"
