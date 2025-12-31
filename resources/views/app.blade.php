<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- PWA Meta Tags --}}
        <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
        <meta name="theme-color" content="#fafafa" media="(prefers-color-scheme: light)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Fleet Copilot') }}">
        <meta name="application-name" content="{{ config('app.name', 'Fleet Copilot') }}">
        <meta name="description" content="Sistema inteligente de gestión de flotas con IA">
        <meta name="format-detection" content="telephone=no">
        <meta name="msapplication-TileColor" content="#0a0a0a">
        <meta name="msapplication-tap-highlight" content="no">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Favicon & PWA Icons --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152x152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png">
        <link rel="apple-touch-icon" sizes="167x167" href="/icons/icon-167x167.png">

        {{-- PWA Manifest --}}
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- Apple Splash Screens (generados para diferentes dispositivos) --}}
        <link rel="apple-touch-startup-image" href="/splashscreens/apple-splash-2048-2732.png" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="/splashscreens/apple-splash-1170-2532.png" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
        <link rel="apple-touch-startup-image" href="/splashscreens/apple-splash-1284-2778.png" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Service Worker Registration --}}
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js', { scope: '/' })
                        .then((registration) => {
                            console.log('[PWA] Service Worker registered:', registration.scope);

                            // Detectar actualizaciones
                            registration.addEventListener('updatefound', () => {
                                const newWorker = registration.installing;
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        // Nueva versión disponible
                                        if (window.dispatchEvent) {
                                            window.dispatchEvent(new CustomEvent('pwa-update-available', {
                                                detail: { registration }
                                            }));
                                        }
                                    }
                                });
                            });
                        })
                        .catch((error) => {
                            console.error('[PWA] Service Worker registration failed:', error);
                        });

                    // Detectar cuando el SW toma control
                    navigator.serviceWorker.addEventListener('controllerchange', () => {
                        console.log('[PWA] New Service Worker activated');
                    });
                });
            }

            // Detectar si la app está instalada como PWA
            window.addEventListener('DOMContentLoaded', () => {
                if (window.matchMedia('(display-mode: standalone)').matches) {
                    document.body.classList.add('pwa-installed');
                    console.log('[PWA] Running as installed app');
                }
            });

            // Evento beforeinstallprompt para instalación personalizada
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.deferredPWAPrompt = e;
                if (window.dispatchEvent) {
                    window.dispatchEvent(new CustomEvent('pwa-installable'));
                }
            });

            // Detectar instalación exitosa
            window.addEventListener('appinstalled', () => {
                window.deferredPWAPrompt = null;
                console.log('[PWA] App installed successfully');
            });
        </script>
    </body>
</html>
