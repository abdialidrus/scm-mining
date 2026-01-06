/**
 * Route helper that wraps the global route() function
 * This provides proper TypeScript typing for the global route function
 * provided by Laravel Ziggy
 */

// Access the global route function through window
const routeHelper =
    (window as any).route ||
    ((name: string, params?: any) => {
        console.error(
            'Route helper not available. Make sure Ziggy is properly configured.',
        );
        return name;
    });

/**
 * Generate a URL for a named route
 * @param name - The route name
 * @param params - Optional route parameters
 * @param absolute - Whether to generate an absolute URL
 * @returns The generated URL
 */
export function useRoute(
    name: string,
    params?: any,
    absolute?: boolean,
): string {
    return routeHelper(name, params, absolute);
}

/**
 * Check if a route exists
 * @param name - The route name
 * @returns Whether the route exists
 */
export function hasRoute(name: string): boolean {
    return routeHelper().has(name);
}

/**
 * Get the current route name
 * @param name - Optional route name to check against
 * @returns Current route name or boolean if checking
 */
export function currentRoute(name?: string): boolean | string {
    return routeHelper().current(name);
}

/**
 * Get route parameters
 * @returns Route parameters object
 */
export function routeParams(): Record<string, any> {
    return routeHelper().params || {};
}

// For backward compatibility, export route as default
export default useRoute;
