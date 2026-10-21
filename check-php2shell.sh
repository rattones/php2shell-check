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
set -euo pipefail

# Versões que já contêm a correção, por branch
FIXED_82=35; FIXED_83=36; FIXED_84=27; FIXED_85=12

echo "==========================================================="
echo " Diagnóstico php2shell (CVE-2026-109618 / CVE-2026-109619)"
echo "==========================================================="
echo

if ! command -v php >/dev/null 2>&1; then
  echo "php (CLI) não encontrado no PATH."
  echo "Tente localizar o binário: ls /usr/bin/php* /opt/*/bin/php* 2>/dev/null"
  exit 1
fi

VER="$(php -r 'echo PHP_VERSION;')"
MAJOR="$(php -r 'echo PHP_MAJOR_VERSION;')"
MINOR="$(php -r 'echo PHP_MINOR_VERSION;')"
PATCH="$(php -r 'echo PHP_RELEASE_VERSION;')"
SAPI="$(php -r 'echo PHP_SAPI;')"
BRANCH="${MAJOR}.${MINOR}"

echo "Versão do PHP (CLI) : $VER"
echo "SAPI                : $SAPI"
echo "(Atenção: a versão do CLI pode diferir da usada pelo site — veja PHP-FPM/módulo abaixo.)"
echo

echo "--- A versão está corrigida? ---"
fixed_patch=""
case "$BRANCH" in
  8.2) fixed_patch=$FIXED_82 ;;
  8.3) fixed_patch=$FIXED_83 ;;
  8.4) fixed_patch=$FIXED_84 ;;
  8.5) fixed_patch=$FIXED_85 ;;
esac

if [ "$MAJOR" -lt 8 ] || { [ "$MAJOR" -eq 8 ] && [ "$MINOR" -lt 2 ]; }; then
  echo "RESULTADO: VULNERÁVEL — PHP $BRANCH não recebe mais correção oficial."
  echo "           Migre para 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+."
elif [ -n "$fixed_patch" ]; then
  if [ "$PATCH" -ge "$fixed_patch" ]; then
    echo "RESULTADO: OK — versão igual ou superior à corrigida ($BRANCH.$fixed_patch)."
  else
    echo "RESULTADO: VULNERÁVEL — atualize para $BRANCH.$fixed_patch ou superior."
  fi
else
  echo "RESULTADO: verifique manualmente. Branch $BRANCH não está na tabela;"
  echo "           confirme que é >= 8.6.0 final (RC4 já contém a correção)."
fi

echo
echo "--- Configurações relevantes (php -i) ---"
php -i 2>/dev/null | grep -i -E \
  'session.upload_progress.enabled|memory_limit|post_max_size|upload_max_filesize|fatal_error_backtraces' \
  || echo "(nenhum parâmetro encontrado)"

echo
echo "--- Versões de PHP-FPM / módulos instalados ---"
for b in php-fpm php8.2-fpm php8.3-fpm php8.4-fpm php8.5-fpm; do
  if command -v "$b" >/dev/null 2>&1; then
    echo "-- $b --"; "$b" -v 2>/dev/null | head -1
  fi
done

echo
echo "--- Pacotes PHP instalados ---"
if command -v dpkg >/dev/null 2>&1; then
  dpkg -l 2>/dev/null | grep -i '^ii.*php' | awk '{print $2, $3}' || true
elif command -v rpm >/dev/null 2>&1; then
  rpm -qa 2>/dev/null | grep -i php || true
fi

echo
echo "Lembrete: session.upload_progress.enabled deve estar Off (0) como mitigação da 109619."
echo "==========================================================="
