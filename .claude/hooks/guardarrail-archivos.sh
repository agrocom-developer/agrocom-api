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
#      El congelamiento es por zona, no todo o nada: AGROCOM_DESCONGELA
#      lista las que la tarea declaró necesitar (`tests`, `decisiones`,
#      `claude`, `github`). Sin esa granularidad, una tarea cuyo
#      entregable ES un test tenía que apagar el turno noche entero —y
#      quedaba habilitada a editar también los ADRs y los agentes, que no
#      necesitaba para nada. Pasó con las tareas 04 y 07.
#
#      CLAUDE.md no se descongela nunca: las invariantes son del usuario,
#      no del turno.
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

# Una zona queda editable solo si la tarea la declaró. La lista viaja en
# AGROCOM_DESCONGELA separada por comas; las comas de los extremos hacen que
# "tests" no coincida con "tests-visuales" si algún día existiera.
descongelada() {
    printf '%s' ",${AGROCOM_DESCONGELA:-},"  | grep -q ",$1,"
}

case "$relativa" in
    CLAUDE.md)
        decidir deny 'CLAUDE.md son las invariantes del proyecto, escritas por el usuario. No se editan desde dentro del turno, con ninguna bandera.' ;;
    tests/*)
        descongelada tests && exit 0
        decidir deny 'Turno noche: los tests son el criterio de aceptación, no parte de la tarea. Si el test está mal, la tarea se marca bloqueada y la revisa el usuario. Si el entregable de la tarea ES un test, su prompt tiene que declarar descongela=tests.' ;;
    docs/decisiones/*)
        descongelada decisiones && exit 0
        decidir deny 'Turno noche: los ADRs registran decisiones tomadas por una persona. Un agente no las reescribe solo. Para ampliar lo que un ADR dejó explícitamente abierto, el prompt declara descongela=decisiones.' ;;
    .claude/*)
        descongelada claude && exit 0
        decidir deny 'Turno noche: los agentes, hooks y skills definen las reglas del turno. No se editan desde dentro del turno salvo que el prompt declare descongela=claude.' ;;
    .github/*)
        descongelada github && exit 0
        decidir deny 'Turno noche: el CI es la compuerta que valida el turno. No se edita desde dentro salvo que el prompt declare descongela=github.' ;;
esac

exit 0
