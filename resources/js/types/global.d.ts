import type { AxiosInstance } from 'axios';
import type { route as ziggyRoute } from 'ziggy-js';
import type { SharedProps } from './index';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    const route: typeof ziggyRoute;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
}

export {};
