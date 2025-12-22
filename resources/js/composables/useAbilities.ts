import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type AbilitiesMap = Record<string, boolean>;

export function useAbilities() {
    const page = usePage();

    const abilities = computed<AbilitiesMap>(() => {
        const auth = (page.props as any)?.auth;
        const raw = auth?.abilities;

        // Handle either a plain object or a lazy Inertia prop.
        if (typeof raw === 'function') {
            try {
                return (raw() ?? {}) as AbilitiesMap;
            } catch {
                return {} as AbilitiesMap;
            }
        }

        return (raw ?? {}) as AbilitiesMap;
    });

    function can(key: string) {
        return Boolean(abilities.value[key]);
    }

    return { abilities, can };
}
