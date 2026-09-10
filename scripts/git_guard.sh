#!/bin/bash
# ============================================================
#  APEX GLASS — Guardián de secretos para el auto-commit
#  Uso normal (desde el hook Stop):   bash scripts/git_guard.sh
#  Uso de prueba:                     bash scripts/git_guard.sh --test <archivo>
#
#  Salida 0 = limpio, se puede commitear
#  Salida 1 = SE DETECTÓ ALGO, NO COMMITEAR
#
#  Regla: este script NUNCA imprime el valor de un secreto,
#  solo el nombre de la clave y el archivo donde apareció.
# ============================================================
set -uo pipefail

REPO="/home/apexglass2025/apex.glass/public_html/produccion"
ENVFILE="/home/apexglass2025/apex.glass/.env"
LOG="${APEX_GUARD_LOG:-/tmp/apex_git_guard.log}"

MODO="git"; TESTFILE=""
if [ "${1:-}" = "--test" ]; then MODO="test"; TESTFILE="${2:-}"; fi

hallazgos=0
reportar() {
    echo "  [BLOQUEO] $1"
    echo "$(date '+%F %T') BLOQUEO $1" >> "$LOG" 2>/dev/null
    hallazgos=$((hallazgos+1))
}

# ---------- obtener el contenido a revisar ----------
TMP=$(mktemp) || exit 1
NOMBRES=$(mktemp) || exit 1
trap 'rm -f "$TMP" "$NOMBRES"' EXIT

if [ "$MODO" = "test" ]; then
    [ -f "$TESTFILE" ] || { echo "archivo de prueba no existe"; exit 1; }
    cat "$TESTFILE" > "$TMP"
    echo "$TESTFILE" > "$NOMBRES"
else
    command -v git >/dev/null || { echo "[BLOQUEO] git no disponible"; exit 1; }
    git -C "$REPO" diff --cached --name-only > "$NOMBRES" 2>/dev/null || exit 1
    # Solo lo que se AGREGA. Las lineas eliminadas (-) no se revisan a proposito:
    # quitar un secreto de un archivo es justo lo que queremos permitir, no bloquear.
    git -C "$REPO" diff --cached -U0 2>/dev/null \
        | grep -E '^\+' | grep -vE '^\+\+\+' > "$TMP" || true
    if [ ! -s "$NOMBRES" ]; then exit 0; fi   # nada preparado, nada que revisar
fi

# ---------- CAPA 1: rutas peligrosas por su forma ----------
# No depende de conocer el incidente de antemano: bloquea la FORMA del archivo.
PATRONES_RUTA='^dl_|(^|/)\.env|(^|/)[^/]*_cache/|(^|/)[^/]*token[^/]*\.(json|txt|ya?ml)$|\.(pem|key|p12|pfx|jks)$|(^|/)id_(rsa|ed25519|ecdsa)|(^|/)[^/]*secret[^/]*\.(json|txt|ya?ml|env)$|(^|/)[^/]*credential|\.playwright-mcp/|(^|/)\.ssh/|\.sql(\.gz)?$|(^|/)sess_'
while IFS= read -r f; do
    [ -z "$f" ] && continue
    if echo "$f" | grep -qE "$PATRONES_RUTA"; then
        reportar "ruta de riesgo: $f"
    fi
done < "$NOMBRES"

# ---------- CAPA 2: valores literales del .env ----------
# La comprobación más fuerte: si una clave viva aparece en el contenido, se bloquea.
if [ -r "$ENVFILE" ]; then
    while IFS= read -r linea; do
        case "$linea" in \#*|"") continue ;; esac
        clave="${linea%%=*}"
        valor="${linea#*=}"
        valor="${valor%\"}"; valor="${valor#\"}"
        valor="${valor%\'}"; valor="${valor#\'}"
        # solo valores con longitud suficiente para ser secreto y no un dato trivial
        [ ${#valor} -ge 12 ] || continue
        case "$clave" in
            DB_NAME|DB_USER|DB_HOST|DB_PORT|SMTP_HOST|SMTP_FROM|SMTP_FROM_NAME| \
            MAIL_PAGOS|MAIL_PAGOS_CC|MAIL_REPLY_TO|FACTURAPI_MODE|APP_URL|APP_NAME) continue ;;
        esac
        if grep -qF -- "$valor" "$TMP"; then
            archivo=$(grep -lF -- "$valor" "$TMP" >/dev/null 2>&1; echo "contenido preparado")
            reportar "valor de \$$clave presente en el $archivo"
        fi
    done < "$ENVFILE"
else
    echo "  [aviso] no se pudo leer el .env — capa 2 omitida, capas 1 y 3 siguen activas"
    echo "$(date '+%F %T') AVISO .env ilegible" >> "$LOG" 2>/dev/null
fi

# ---------- CAPA 3: formas genéricas de credencial ----------
# Cubre claves que aún no están en el .env (de un tercero nuevo, de una prueba, etc.)
declare -A GENERICOS=(
    ["token de Meta/Facebook"]='EAA[A-Za-z0-9]{60,}'
    ["llave de Google API"]='AIza[0-9A-Za-z_-]{35}'
    ["llave Stripe/FacturAPI"]='sk_(live|test)_[A-Za-z0-9]{16,}'
    ["token de GitHub"]='gh[pousr]_[A-Za-z0-9]{30,}'
    ["token de Slack"]='xox[baprs]-[A-Za-z0-9-]{10,}'
    ["llave privada"]='-----BEGIN [A-Z ]*PRIVATE KEY-----'
    ["token de sesión web"]='"web_token"[[:space:]]*:'
    ["credencial en URL"]='://[A-Za-z0-9_.-]+:[^@/[:space:]]{8,}@'
    ["contraseña en asignación"]='(PASSWORD|PASSWD|SECRET|API_KEY|TOKEN)[[:space:]]*=[[:space:]]*["'"'"'][A-Za-z0-9/+_-]{16,}["'"'"']'
)
for etiqueta in "${!GENERICOS[@]}"; do
    if grep -qE -- "${GENERICOS[$etiqueta]}" "$TMP"; then
        reportar "posible $etiqueta en el contenido preparado"
    fi
done

# ---------- veredicto ----------
if [ "$hallazgos" -gt 0 ]; then
    echo ""
    echo "  ===================================================="
    echo "   AUTO-COMMIT BLOQUEADO — $hallazgos señal(es) de secreto"
    echo "   Los cambios NO se perdieron: siguen en el disco,"
    echo "   solo no se subieron a GitHub."
    echo "   Revisa lo señalado, sácalo del directorio o agrégalo"
    echo "   al .gitignore, y vuelve a intentar."
    echo "  ===================================================="
    exit 1
fi
exit 0
