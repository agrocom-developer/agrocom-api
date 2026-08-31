#!/usr/bin/env bash
#
# Guardarraíl de escritura de archivos (hook PreToolUse sobre Write|Edit).
#
# Dos capas:
#   1. Siempre: el .env real es intocable.
#   2. Solo con AGROCOM_TURNO_NOCHE=1: se congelan los archivos que
#      definen QUÉ es correcto en este repo. Un agente que puede editar
#      el test que lo evalúa no está siendo evaluado; uno que puede
#      editar un ADR no está respetando una decisión, la está borrando.
#      De día esos archivos se editan normalmente.
#
set -uo pipefail

entrada=$(cat)
ruta=$(printf '%s' "$entrada" | jq -r '.tool_input.file_path // ""' 2>/dev/null)
[ -z "$ruta" ] && exit 0

decidir() {
    jq -cn --arg d "$1" --arg r "$2" \
        '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:$d,permissionDecisionReason:$r}}'
    exit 0
}

relativa=${ruta#"$PWD"/}
base=$(basename "$ruta")

case "$base" in
    .env|.env.local|.env.production|.env.staging)
        decidir deny 'El .env real no se edita por herramienta: no está versionado y no hay forma de recuperarlo.' ;;
esac

[ "${AGROCOM_TURNO_NOCHE:-0}" != "1" ] && exit 0

case "$relativa" in
    tests/*)
        decidir deny 'Turno noche: los tests son el criterio de aceptación, no parte de la tarea. Si el test está mal, la tarea se marca bloqueada y la revisa el usuario.' ;;
    docs/decisiones/*)
        decidir deny 'Turno noche: los ADRs registran decisiones tomadas por una persona. Un agente no las reescribe solo.' ;;
    CLAUDE.md|.claude/*|.github/*)
        decidir deny 'Turno noche: las invariantes, los agentes y el CI definen las reglas del turno. No se editan desde dentro del turno.' ;;
esac

exit 0
