// Global type declarations for Laravel + Inertia.js + Ziggy

/**
 * Laravel Ziggy route() function
 * Available globally in browser context
 */
declare global {
    function route(name: string, params?: any, absolute?: boolean): string;
    function route(): {
        current(name?: string): boolean | string;
        has(name: string): boolean;
        params: Record<string, any>;
    };

    interface Window {
        route: typeof route;
    }
}

export {};
