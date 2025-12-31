#!/bin/bash

# =============================================================================
# Fleet Copilot - Generador de Iconos PWA
# =============================================================================
# Este script genera todos los iconos necesarios para la PWA a partir de
# un archivo SVG o PNG de alta resolución (mínimo 1024x1024).
#
# Requisitos:
#   - ImageMagick: brew install imagemagick
#   - (Opcional) sharp-cli: npm install -g sharp-cli
#
# Uso:
#   ./scripts/generate-pwa-icons.sh [ruta-al-icono-fuente]
#
# Si no se proporciona ruta, usará public/logo.svg por defecto.
# =============================================================================

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directorio del proyecto
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ICONS_DIR="$PROJECT_DIR/public/icons"
SPLASH_DIR="$PROJECT_DIR/public/splashscreens"

# Archivo fuente
SOURCE_ICON="${1:-$PROJECT_DIR/public/logo.svg}"

echo -e "${BLUE}╔═══════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Fleet Copilot - Generador de Iconos PWA     ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar que ImageMagick está instalado
if ! command -v convert &> /dev/null; then
    echo -e "${RED}Error: ImageMagick no está instalado.${NC}"
    echo "Instálalo con: brew install imagemagick"
    exit 1
fi

# Verificar que el archivo fuente existe
if [ ! -f "$SOURCE_ICON" ]; then
    echo -e "${RED}Error: No se encontró el archivo fuente: $SOURCE_ICON${NC}"
    echo "Proporciona la ruta al icono como argumento o coloca tu logo en public/logo.svg"
    exit 1
fi

echo -e "${GREEN}✓${NC} Archivo fuente: $SOURCE_ICON"
echo ""

# Crear directorios si no existen
mkdir -p "$ICONS_DIR"
mkdir -p "$SPLASH_DIR"

# =============================================================================
# Generar iconos estándar
# =============================================================================
echo -e "${YELLOW}Generando iconos estándar...${NC}"

ICON_SIZES=(72 96 128 144 152 167 180 192 384 512)

for size in "${ICON_SIZES[@]}"; do
    output="$ICONS_DIR/icon-${size}x${size}.png"
    echo -n "  → ${size}x${size}..."
    convert "$SOURCE_ICON" -resize "${size}x${size}" -background transparent -gravity center -extent "${size}x${size}" "$output"
    echo -e " ${GREEN}✓${NC}"
done

# =============================================================================
# Generar iconos maskable (con padding para zona segura)
# =============================================================================
echo ""
echo -e "${YELLOW}Generando iconos maskable...${NC}"

MASKABLE_SIZES=(192 512)

for size in "${MASKABLE_SIZES[@]}"; do
    output="$ICONS_DIR/icon-maskable-${size}x${size}.png"
    # El icono maskable necesita ~10% de padding en cada lado
    # por lo que el icono real es ~80% del tamaño total
    inner_size=$((size * 80 / 100))
    
    echo -n "  → ${size}x${size} (maskable)..."
    
    # Crear un fondo con el color de la marca y el icono centrado
    convert -size "${size}x${size}" "xc:#6366f1" \
        \( "$SOURCE_ICON" -resize "${inner_size}x${inner_size}" -background none -gravity center \) \
        -gravity center -composite \
        "$output"
    
    echo -e " ${GREEN}✓${NC}"
done

# =============================================================================
# Generar favicon.ico multi-resolución
# =============================================================================
echo ""
echo -e "${YELLOW}Generando favicon.ico...${NC}"

convert "$SOURCE_ICON" \
    -define icon:auto-resize=256,128,96,64,48,32,16 \
    "$PROJECT_DIR/public/favicon.ico"

echo -e "  → favicon.ico ${GREEN}✓${NC}"

# =============================================================================
# Generar apple-touch-icon
# =============================================================================
echo ""
echo -e "${YELLOW}Generando apple-touch-icon...${NC}"

convert "$SOURCE_ICON" \
    -resize "180x180" \
    -background white \
    -gravity center \
    -extent "180x180" \
    "$PROJECT_DIR/public/apple-touch-icon.png"

echo -e "  → apple-touch-icon.png (180x180) ${GREEN}✓${NC}"

# =============================================================================
# Generar iconos para shortcuts
# =============================================================================
echo ""
echo -e "${YELLOW}Generando iconos de shortcuts...${NC}"

# Icono de chat/nueva conversación
convert -size 96x96 xc:transparent \
    -fill "#6366f1" -draw "roundrectangle 0,0 95,95 16,16" \
    -fill white -draw "circle 48,36 48,24" \
    -fill white -draw "polygon 32,48 64,48 48,72" \
    "$ICONS_DIR/shortcut-chat.png"
echo -e "  → shortcut-chat.png ${GREEN}✓${NC}"

# Icono de vehículos
convert -size 96x96 xc:transparent \
    -fill "#8b5cf6" -draw "roundrectangle 0,0 95,95 16,16" \
    -fill white -draw "roundrectangle 16,36 80,68 8,8" \
    -fill white -draw "circle 32,68 32,60" \
    -fill white -draw "circle 64,68 64,60" \
    "$ICONS_DIR/shortcut-vehicles.png"
echo -e "  → shortcut-vehicles.png ${GREEN}✓${NC}"

# =============================================================================
# Generar badge para notificaciones
# =============================================================================
echo ""
echo -e "${YELLOW}Generando badge para notificaciones...${NC}"

convert -size 72x72 xc:transparent \
    -fill "#6366f1" -draw "circle 36,36 36,6" \
    "$ICONS_DIR/badge-72x72.png"

echo -e "  → badge-72x72.png ${GREEN}✓${NC}"

# =============================================================================
# Generar splash screens para iOS
# =============================================================================
echo ""
echo -e "${YELLOW}Generando splash screens para iOS...${NC}"

# Define splash screen sizes
declare -a SPLASH_SCREENS=(
    "2048:2732" # iPad Pro 12.9"
    "1668:2388" # iPad Pro 11"
    "1536:2048" # iPad Mini, iPad Air
    "1284:2778" # iPhone 14 Pro Max
    "1179:2556" # iPhone 14 Pro
    "1170:2532" # iPhone 14, iPhone 13 Pro
    "1125:2436" # iPhone X, XS, 11 Pro
    "1242:2688" # iPhone 11 Pro Max
    "828:1792"  # iPhone XR, 11
    "750:1334"  # iPhone 8, SE
    "640:1136"  # iPhone SE (1st gen)
)

for screen in "${SPLASH_SCREENS[@]}"; do
    IFS=':' read -r width height <<< "$screen"
    output="$SPLASH_DIR/apple-splash-${width}-${height}.png"
    
    # Tamaño del logo (30% del ancho)
    logo_size=$((width * 30 / 100))
    
    echo -n "  → ${width}x${height}..."
    
    # Crear splash con gradiente y logo centrado
    convert -size "${width}x${height}" \
        -define gradient:angle=135 \
        gradient:"#0a0a0a-#1a1a2e" \
        \( "$SOURCE_ICON" -resize "${logo_size}x${logo_size}" -background none \) \
        -gravity center -composite \
        "$output"
    
    echo -e " ${GREEN}✓${NC}"
done

# =============================================================================
# Resumen
# =============================================================================
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ ¡Generación completa!${NC}"
echo ""
echo "Archivos generados:"
echo "  • $(ls -1 "$ICONS_DIR" | wc -l | tr -d ' ') iconos en public/icons/"
echo "  • $(ls -1 "$SPLASH_DIR" | wc -l | tr -d ' ') splash screens en public/splashscreens/"
echo "  • favicon.ico"
echo "  • apple-touch-icon.png"
echo ""
echo -e "${YELLOW}Nota:${NC} Para mejores resultados, proporciona un SVG o PNG"
echo "de alta resolución (mínimo 1024x1024) como archivo fuente."
echo ""

