import { ref } from 'vue';

interface ToastOptions {
    title: string;
    description?: string;
    variant?: 'default' | 'destructive';
}

const toasts = ref<Array<ToastOptions & { id: number }>>([]);
let idCounter = 0;

export function useToast() {
    const toast = (options: ToastOptions) => {
        const id = idCounter++;
        toasts.value.push({ ...options, id });

        // Auto remove after 5 seconds
        setTimeout(() => {
            const index = toasts.value.findIndex((t) => t.id === id);
            if (index !== -1) {
                toasts.value.splice(index, 1);
            }
        }, 5000);

        // Show browser notification or alert for now
        if (options.variant === 'destructive') {
            console.error(`${options.title}: ${options.description}`);
        } else {
            console.log(`${options.title}: ${options.description}`);
        }
    };

    return { toast, toasts };
}
