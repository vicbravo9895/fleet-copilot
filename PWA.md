# Fleet Copilot - Configuración PWA

Esta guía te ayudará a personalizar la Progressive Web App (PWA) con tu branding.

## 🎨 Personalización del Branding

### 1. Manifest (`public/manifest.webmanifest`)

Edita los siguientes campos para tu marca:

```json
{
  "name": "Tu Nombre de App Completo",
  "short_name": "AppCorto",
  "description": "Descripción de tu aplicación",
  "theme_color": "#tu-color-primario",
  "background_color": "#tu-color-de-fondo"
}
```

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| `name` | Nombre completo de la app (máx 45 caracteres) | "Fleet Copilot" |
| `short_name` | Nombre corto para íconos (máx 12 caracteres) | "Fleet" |
| `description` | Descripción para stores | "Sistema de gestión..." |
| `theme_color` | Color de la barra del navegador | "#6366f1" |
| `background_color` | Color de fondo del splash | "#0a0a0a" |

### 2. Iconos PWA

#### Tamaños Requeridos

| Tamaño | Uso |
|--------|-----|
| 72x72 | Android Chrome (ldpi) |
| 96x96 | Android Chrome (mdpi) |
| 128x128 | Chrome Web Store |
| 144x144 | Android Chrome (xhdpi) |
| 152x152 | iPad |
| 167x167 | iPad Pro |
| 180x180 | iPhone |
| 192x192 | Android Chrome (xxxhdpi) |
| 384x384 | Android Chrome |
| 512x512 | Android Chrome (alta res) |

#### Iconos Maskable

Los iconos maskable tienen una "zona segura" circular. Tu logo debe ocupar ~80% del espacio con padding alrededor.

```
┌─────────────────┐
│                 │
│    ┌───────┐    │  ← 10% padding
│    │ LOGO  │    │
│    └───────┘    │
│                 │
└─────────────────┘
```

#### Generar Iconos Automáticamente

```bash
# Dar permisos de ejecución
chmod +x scripts/generate-pwa-icons.sh

# Generar iconos desde tu logo
./scripts/generate-pwa-icons.sh public/tu-logo.svg
```

### 3. Colores del Tema

Edita `resources/views/app.blade.php` para cambiar los colores del tema:

```html
<meta name="theme-color" content="#tu-color-oscuro" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#tu-color-claro" media="(prefers-color-scheme: light)">
```

### 4. Splash Screens (iOS)

Los splash screens se muestran mientras carga la PWA en iOS. Necesitas diferentes tamaños:

| Dispositivo | Tamaño |
|-------------|--------|
| iPhone 14 Pro Max | 1284x2778 |
| iPhone 14 Pro | 1179x2556 |
| iPhone 14, 13 | 1170x2532 |
| iPad Pro 12.9" | 2048x2732 |
| iPad Pro 11" | 1668x2388 |

El script `generate-pwa-icons.sh` genera todos estos automáticamente.

### 5. Shortcuts (Accesos Directos)

Los shortcuts aparecen al mantener presionado el ícono de la app:

```json
{
  "shortcuts": [
    {
      "name": "Nombre del acceso",
      "short_name": "Corto",
      "description": "Descripción",
      "url": "/ruta",
      "icons": [{ "src": "/icons/shortcut-icon.png", "sizes": "96x96" }]
    }
  ]
}
```

## 🔧 Configuración del Service Worker

### Estrategias de Caching

El Service Worker (`public/sw.js`) usa diferentes estrategias:

| Estrategia | Uso | Descripción |
|------------|-----|-------------|
| Cache First | Assets estáticos (JS, CSS, imágenes) | Carga desde cache, actualiza en background |
| Network First | Páginas HTML | Intenta red primero, fallback a cache |
| Stale While Revalidate | API cacheables | Devuelve cache inmediato, actualiza en background |
| Network Only | Auth, API sensible | Siempre va a la red |

### Personalizar Rutas

Edita las constantes en `public/sw.js`:

```javascript
// Rutas que siempre van a la red
const NETWORK_ONLY_PATTERNS = [
  /\/api\//,
  /\/login/,
  // Agrega tus rutas...
];

// Rutas de API que pueden cachearse
const API_CACHE_PATTERNS = [
  /\/api\/vehicles/,
  // Agrega tus rutas...
];
```

### Actualizar Versión del Cache

Cuando hagas cambios significativos, incrementa la versión:

```javascript
const CACHE_VERSION = 'v1.0.1';  // Cambiar para invalidar caches
```

## 📱 Componentes React

### Hook `usePWA`

```tsx
import { usePWA } from '@/hooks/use-pwa';

function MyComponent() {
  const { 
    isInstallable,   // true si se puede instalar
    isInstalled,     // true si ya está instalada
    isOnline,        // true si hay conexión
    hasUpdate,       // true si hay actualización
    installApp,      // función para instalar
    updateApp,       // función para actualizar
  } = usePWA();

  return (
    <button onClick={installApp} disabled={!isInstallable}>
      Instalar App
    </button>
  );
}
```

### Componentes Disponibles

```tsx
import { 
  PWAInstallPrompt,   // Prompt de instalación
  PWAUpdatePrompt,    // Notificación de actualización
  OfflineIndicator    // Banner de sin conexión
} from '@/components/pwa';
```

## ✅ Checklist de Lanzamiento

Antes de lanzar tu PWA, verifica:

- [ ] **Manifest válido** - Usa [Web App Manifest Validator](https://manifest-validator.appspot.com/)
- [ ] **Iconos en todos los tamaños** - Especialmente 192x192 y 512x512
- [ ] **Iconos maskable** - Para Android adaptive icons
- [ ] **HTTPS habilitado** - Requerido para Service Workers
- [ ] **Splash screens** - Para experiencia de carga en iOS
- [ ] **Offline funcional** - Prueba desconectando la red
- [ ] **Lighthouse PWA audit** - Score > 90

### Probar con Lighthouse

1. Abre Chrome DevTools (F12)
2. Ve a la pestaña "Lighthouse"
3. Selecciona "Progressive Web App"
4. Ejecuta el audit

### Probar Instalación

1. **Chrome Desktop**: Menú → "Instalar Fleet Copilot"
2. **Chrome Android**: Banner automático o menú → "Añadir a pantalla de inicio"
3. **Safari iOS**: Compartir → "Añadir a pantalla de inicio"

## 🎨 Colores Recomendados

Para mantener consistencia visual:

| Elemento | Light Mode | Dark Mode |
|----------|------------|-----------|
| Background | `#fafafa` | `#0a0a0a` |
| Theme color | `#fafafa` | `#0a0a0a` |
| Primary | `#6366f1` | `#6366f1` |
| Accent | `#8b5cf6` | `#8b5cf6` |

## 📚 Recursos Adicionales

- [Web.dev PWA Guide](https://web.dev/progressive-web-apps/)
- [PWA Builder](https://www.pwabuilder.com/) - Genera assets automáticamente
- [Maskable.app](https://maskable.app/) - Editor de iconos maskable
- [Real Favicon Generator](https://realfavicongenerator.net/) - Generador completo de favicons

---

¿Necesitas ayuda? Revisa la documentación o abre un issue en el repositorio.

