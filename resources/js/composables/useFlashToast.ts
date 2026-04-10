import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import type { PageProps } from '@/types';

export function useFlashToast() {
    const page = usePage<PageProps>();
    const toast = useToast();

    watch(
        () => ({
            success: page.props.flash?.success,
            error: page.props.flash?.error,
            status: page.props.flash?.status,
        }),
        (flash) => {
            if (flash.success) {
                toast.add({ severity: 'success', summary: 'Success', detail: flash.success, life: 4000 });
            }
            if (flash.error) {
                toast.add({ severity: 'error', summary: 'Error', detail: flash.error, life: 6000 });
            }
            if (flash.status) {
                toast.add({ severity: 'info', summary: 'Info', detail: flash.status, life: 4000 });
            }
        },
        { immediate: true },
    );
}
