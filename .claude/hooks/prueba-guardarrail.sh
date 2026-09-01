#!/usr/bin/env bash
#
# Prueba del guardarraíl de bash. Se corre a mano:
#
#   .claude/hooks/prueba-guardarrail.sh
#
# Existe porque el hook falló en silencio una vez y nadie se enteró: la excepción
# de "esto es solo una búsqueda" se evaluaba sobre el comando entero, así que un
# `echo` en cualquier línea desactivaba TODAS las reglas de abajo — push a
# develop, reset en duro, migrate:fresh. Un guardarraíl que se puede apagar sin
# querer no es un guardarraíl, y este es el único que queda de pie cuando el
# ciclo corre de noche.
#
# Los casos peligrosos se arman por partes para que este archivo no dispare el
# hook al escribirse o al leerse.
#
set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1

HOOK=.claude/hooks/guardarrail-bash.sh
fallos=0

# El hook, cuando deja pasar, no imprime nada y sale 0. "pasa" es esa ausencia.
decision() {
    local salida
    salida=$(printf '%s' "$1" | jq -Rs '{tool_input:{command:.}}' | "$HOOK" 2>/dev/null |
             jq -r '.hookSpecificOutput.permissionDecision // empty' 2>/dev/null)
    printf '%s' "${salida:-pasa}"
}

caso() {
    local esperado="$1" nombre="$2" comando="$3" real
    real=$(decision "$comando")
    if [ "$real" = "$esperado" ]; then
        printf '\033[32m  ✓ %-52s %s\033[0m\n' "$nombre" "$real"
    else
        printf '\033[31m  ✗ %-52s esperaba %s, dio %s\033[0m\n' "$nombre" "$esperado" "$real"
        fallos=$((fallos + 1))
    fi
}

ECHO_ANTES='echo encabezado del script
'
G=git
ENV_REAL='.env'

printf '\n\033[1m── Tiene que denegar ──\033[0m\n'
caso deny "borrado forzado de rama"          "$G branch -D x"
caso deny "borrado forzado, con echo antes"  "${ECHO_ANTES}$G branch -D x"
caso deny "push a develop, con echo antes"   "${ECHO_ANTES}$G push origin develop"
caso deny "reset en duro, con echo antes"    "${ECHO_ANTES}$G reset --hard origin/develop"
caso deny "push forzado, con echo antes"     "${ECHO_ANTES}$G push --force origin x"
caso deny "clean -fdx, con echo antes"       "${ECHO_ANTES}$G clean -fdx"
caso deny "migrate:fresh, con echo antes"    "${ECHO_ANTES}php artisan migrate:fresh"
caso deny "db:wipe, con echo antes"          "${ECHO_ANTES}php artisan db:wipe"
caso deny "volumen del compose, echo antes"  "${ECHO_ANTES}docker compose down -v"
caso deny "DDL destructivo, con echo antes"  "${ECHO_ANTES}psql -c 'DROP TABLE sec_user'"
caso deny "echo encadenado con push"         "echo x && $G push origin develop"
caso deny "echo con sustitución y push"      "echo \$($G push origin develop)"
caso deny "lectura del .env, con echo antes" "${ECHO_ANTES}cat $ENV_REAL"
caso deny "rm -rf fuera del scratchpad"      "${ECHO_ANTES}rm -rf /Users/alguien/cosas"

printf '\n\033[1m── Tiene que dejar pasar ──\033[0m\n'
caso pasa "solo un echo"                     'echo hola'
caso pasa "echo que MENCIONA un destructivo" "echo 'no uses $G reset --hard nunca'"
caso pasa "grep que menciona migrate:fresh"  "grep -rn 'migrate:fresh' docs/"
caso pasa "varias líneas de echo"            'echo uno
echo dos'
caso pasa "push a una rama feature"          "$G push -u origin feature/algo"
caso pasa "echo y después un comando inocuo" "${ECHO_ANTES}$G status --short"
caso pasa "lectura del .env.example"         "cat ${ENV_REAL}.example"
caso pasa "rm -rf en el scratchpad"          'rm -rf /tmp/claude-501/algo'

printf '\n'
if [ "$fallos" -eq 0 ]; then
    printf '\033[32m✓ El guardarraíl responde como debe en los %s casos.\033[0m\n' 22
    exit 0
fi
printf '\033[31m✗ %s caso(s) mal. El guardarraíl NO está protegiendo lo que dice proteger.\033[0m\n' "$fallos"
exit 1
