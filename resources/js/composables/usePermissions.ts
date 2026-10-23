import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Show or hide UI by permission. This is only for a clearer interface: the server
 * checks every action again (CLAUDE.md rule 3).
 */
export function usePermissions() {
    const page = usePage();
    const granted = computed(() => new Set(page.props.auth.permissions));

    const can = (permission: string): boolean => granted.value.has(permission);
    const canAny = (permissions: string[]): boolean => permissions.some(can);

    return { can, canAny };
}
