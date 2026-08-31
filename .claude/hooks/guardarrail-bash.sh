#!/usr/bin/env bash
#
# Guardarraíl de comandos destructivos (hook PreToolUse sobre Bash).
#
# Un deny de hook gana incluso sobre los modos permisivos, así que esta
# es la única capa que sigue de pie cuando no hay nadie mirando la
# pantalla. Lista negra explícita: lo que no está acá, pasa.
#
# Cada regla protege algo concreto de este repo:
#   - la historia publicada en GitHub (ADR 0006: todo entra por PR)
#   - la base del compose, que tiene el seed demo del panel
#   - el .env real, que vive en la raíz junto al .env.example
#
set -uo pipefail

entrada=$(cat)
comando=$(printf '%s' "$entrada" | jq -r '.tool_input.command // ""' 2>/dev/null)
[ -z "$comando" ] && exit 0

# El cuerpo de un heredoc son datos, no comandos: un mensaje de commit que
# MENCIONA un comando destructivo no lo ejecuta. Se descarta ese cuerpo (no la
# línea que lo abre, que sí es un comando) antes de evaluar las reglas.
comando=$(printf '%s' "$comando" | awk '
{
    if (dentro) { if ($0 == delim) dentro = 0; next }
    print
    if (match($0, /<<-?[ \t]*(\042[^\042]+\042|\047[^\047]+\047|[A-Za-z_][A-Za-z0-9_]*)/)) {
        d = substr($0, RSTART, RLENGTH)
        sub(/^<<-?[ \t]*/, "", d)
        gsub(/[\042\047]/, "", d)
        delim = d
        dentro = 1
    }
}')

# Decisión al harness. "deny" corta; "ask" exige confirmación humana
# (que de noche, sin nadie para confirmar, equivale a cortar).
decidir() {
    jq -cn --arg d "$1" --arg r "$2" \
        '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:$d,permissionDecisionReason:$r}}'
    exit 0
}

# `--` es obligatorio: sin él, grep lee un patrón como --force-with-lease
# como si fuera una opción suya y aborta.
coincide() { printf '%s' "$comando" | grep -qE -- "$1"; }

# Un comando de búsqueda que solo MENCIONA un patrón peligroso no es
# peligroso. Se exceptúa .env: ahí leer es justamente el riesgo.
solo_busqueda=0
coincide '^[[:space:]]*(grep|rg|ag|echo|printf)[[:space:]]' && solo_busqueda=1

rama=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")

# --- Secretos --------------------------------------------------------
if coincide '(^|[[:space:]/"'"'"'])\.env([[:space:]"'"'"';|&]|$)' && ! coincide '\.env\.example'; then
    coincide '\b(cat|less|more|head|tail|bat|nl|od|xxd|strings|source|\.)\b' &&
        decidir deny 'Lectura del .env real. Los valores que necesites pedilos al usuario o leelos de .env.example.'
    coincide '(>>?[[:space:]]*|[[:space:]](cp|mv|rm|truncate)[[:space:]][^;&|]*)' &&
        decidir deny 'Escritura o borrado del .env real: es el único archivo del repo que no está versionado y no se puede recuperar.'
fi

[ "$solo_busqueda" = "1" ] && exit 0

# --- Historia de git -------------------------------------------------
if coincide '\bgit[[:space:]]+push\b'; then
    coincide '(--force([[:space:]]|$)|[[:space:]]-f([[:space:]]|$))' && ! coincide '--force-with-lease' &&
        decidir deny 'git push --force reescribe historia ya publicada en GitHub. Si de verdad hace falta, lo hace el usuario a mano.'
    coincide '\bpush\b[^;&|]*\b(master|develop)\b' &&
        decidir deny 'Push directo a master/develop. GitFlow simplificado (ADR 0006): todo entra por PR desde una rama feature/*.'
    { [ "$rama" = "master" ] || [ "$rama" = "develop" ]; } &&
        decidir deny "Estás parado en $rama y este push iría directo a la rama base. Creá una rama feature/* primero (ADR 0006)."
fi

coincide '\bgit[[:space:]]+reset\b[^;&|]*--hard' &&
    decidir deny 'git reset --hard descarta cambios sin papelera. Si querés descartar, decilo y lo hacemos archivo por archivo.'
coincide '\bgit[[:space:]]+clean\b[^;&|]*-[[:alnum:]]*[fdx]' &&
    decidir deny 'git clean borra archivos no versionados — entre ellos el .env real.'
coincide '\bgit[[:space:]]+branch\b[^;&|]*[[:space:]]-D([[:space:]]|$)' &&
    decidir deny 'Borrado forzado de rama: puede perder commits que nunca llegaron al remoto.'
coincide '\bgit[[:space:]]+filter-branch\b|\bgit[[:space:]]+reflog[[:space:]]+expire\b' &&
    decidir deny 'Reescritura de historia local. Decisión del usuario, nunca automática.'

if coincide '\bgit[[:space:]]+commit\b'; then
    [ "$rama" = "master" ] &&
        decidir deny 'Commit directo sobre master. master solo recibe merges de develop por PR (ADR 0006).'
    [ "$rama" = "develop" ] &&
        decidir ask 'Estás en develop. La convención del repo (ADR 0006) es commitear en una rama feature/* y entrar por PR. ¿Confirmás el commit directo?'
fi

# --- Base de datos ---------------------------------------------------
coincide '\bartisan\b[^;&|]*\b(migrate:fresh|migrate:refresh|migrate:reset|db:wipe)\b' &&
    decidir deny 'Ese comando vacía la base del compose, que tiene el seed demo del panel (usuario camila.rojas y catálogo sec_*). Para probar migraciones: los tests corren contra SQLite en memoria.'
coincide '\bdocker([[:space:]]+|-)compose\b[^;&|]*\bdown\b[^;&|]*(-v|--volumes)' &&
    decidir deny 'docker compose down -v elimina el volumen agrocom-db-data: se pierde la base de desarrollo entera.'
coincide '\bdocker[[:space:]]+volume[[:space:]]+rm\b' &&
    decidir deny 'Borrado de volumen Docker: se pierde la base de desarrollo.'
coincide '\b(DROP[[:space:]]+(DATABASE|TABLE|SCHEMA)|TRUNCATE[[:space:]]+TABLE)\b' &&
    decidir deny 'DDL destructivo suelto. Todo cambio de esquema va en una migración versionada (ADR 0007).'

# --- Sistema de archivos ---------------------------------------------
if coincide '(^|[;&|]|[[:space:]])rm[[:space:]]+(-[[:alnum:]]*[rR][[:alnum:]]*f|-[[:alnum:]]*f[[:alnum:]]*[rR])'; then
    coincide 'rm[[:space:]]+-[[:alnum:]]+[[:space:]]+"?(/private)?/tmp/' ||
        decidir deny 'rm -rf fuera del scratchpad. Para archivos temporales usá el directorio de scratchpad de la sesión.'
fi

exit 0
