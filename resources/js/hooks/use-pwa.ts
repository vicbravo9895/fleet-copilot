import { useCallback, useEffect, useState } from 'react';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

interface PWAState {
    isInstallable: boolean;
    isInstalled: boolean;
    isOnline: boolean;
    hasUpdate: boolean;
    isIOS: boolean;
    isStandalone: boolean;
}

interface UsePWAReturn extends PWAState {
    installApp: () => Promise<boolean>;
    updateApp: () => void;
    dismissInstall: () => void;
}

declare global {
    interface Window {
        deferredPWAPrompt?: BeforeInstallPromptEvent;
    }
}

export function usePWA(): UsePWAReturn {
    const [state, setState] = useState<PWAState>({
        isInstallable: false,
        isInstalled: false,
        isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
        hasUpdate: false,
        isIOS: false,
        isStandalone: false,
    });

    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [registration, setRegistration] = useState<ServiceWorkerRegistration | null>(null);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        // Detectar iOS
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !(window as { MSStream?: unknown }).MSStream;

        // Detectar si está instalada
        const isStandalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as { standalone?: boolean }).standalone === true;

        setState((prev) => ({
            ...prev,
            isIOS,
            isStandalone,
            isInstalled: isStandalone,
        }));

        // Verificar si ya hay un prompt guardado
        if (window.deferredPWAPrompt) {
            setDeferredPrompt(window.deferredPWAPrompt);
            setState((prev) => ({ ...prev, isInstallable: true }));
        }

        // Escuchar evento de instalación disponible
        const handleInstallable = () => {
            if (window.deferredPWAPrompt) {
                setDeferredPrompt(window.deferredPWAPrompt);
                setState((prev) => ({ ...prev, isInstallable: true }));
            }
        };

        // Escuchar evento de actualización disponible
        const handleUpdateAvailable = (event: CustomEvent<{ registration: ServiceWorkerRegistration }>) => {
            setRegistration(event.detail.registration);
            setState((prev) => ({ ...prev, hasUpdate: true }));
        };

        // Detectar cambios de conexión
        const handleOnline = () => {
            setState((prev) => ({ ...prev, isOnline: true }));
        };

        const handleOffline = () => {
            setState((prev) => ({ ...prev, isOnline: false }));
        };

        window.addEventListener('pwa-installable', handleInstallable);
        window.addEventListener('pwa-update-available', handleUpdateAvailable as EventListener);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('pwa-installable', handleInstallable);
            window.removeEventListener('pwa-update-available', handleUpdateAvailable as EventListener);
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    const installApp = useCallback(async (): Promise<boolean> => {
        if (!deferredPrompt) {
            console.log('[PWA] No install prompt available');
            return false;
        }

        try {
            await deferredPrompt.prompt();
            const choiceResult = await deferredPrompt.userChoice;

            if (choiceResult.outcome === 'accepted') {
                console.log('[PWA] User accepted the install prompt');
                setState((prev) => ({ ...prev, isInstallable: false, isInstalled: true }));
                setDeferredPrompt(null);
                window.deferredPWAPrompt = undefined;
                return true;
            } else {
                console.log('[PWA] User dismissed the install prompt');
                return false;
            }
        } catch (error) {
            console.error('[PWA] Error during installation:', error);
            return false;
        }
    }, [deferredPrompt]);

    const updateApp = useCallback(() => {
        if (!registration?.waiting) {
            console.log('[PWA] No waiting service worker');
            return;
        }

        // Indicar al nuevo SW que tome control
        registration.waiting.postMessage({ type: 'SKIP_WAITING' });

        // Recargar la página cuando el nuevo SW tome control
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        });
    }, [registration]);

    const dismissInstall = useCallback(() => {
        setState((prev) => ({ ...prev, isInstallable: false }));
        setDeferredPrompt(null);
        window.deferredPWAPrompt = undefined;
    }, []);

    return {
        ...state,
        installApp,
        updateApp,
        dismissInstall,
    };
}

