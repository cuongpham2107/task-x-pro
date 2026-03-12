{{--
    Event-driven toast notification stack (Alpine.js).

    Usage: place once in your page layout, then dispatch events:

      $dispatch('toast', { message: 'Task created!', type: 'success' })
      $dispatch('toast', { message: 'Something went wrong', type: 'error' })

    Types: success | error | warning | info
    Auto-dismisses after 4 seconds. Supports stacking multiple toasts.
--}}

<div
    x-data="{
        toasts: [],
        add(e) {
            const id = Date.now();
            this.toasts.push({
                id,
                message: e.detail?.message ?? e.detail ?? '',
                type: e.detail?.type ?? 'success',
            });
            setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
        icon(type) {
            return { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' } [type] ?? 'info';
        },
        colors(type) {
            const map = {
                success: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300',
                error: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300',
                warning: 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300',
                info: 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300',
            };
            return map[type] ?? map.info;
        },
        iconColor(type) {
            const map = {
                success: 'text-green-500 dark:text-green-400',
                error: 'text-red-500 dark:text-red-400',
                warning: 'text-amber-500 dark:text-amber-400',
                info: 'text-blue-500 dark:text-blue-400',
            };
            return map[type] ?? map.info;
        },
    }"
    @toast.window="add($event)"
    class="z-100 pointer-events-none fixed bottom-6 right-6 flex w-full max-w-sm flex-col-reverse gap-3"
>
    <template
        x-for="toast in toasts"
        :key="toast.id"
    >
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            :class="colors(toast.type)"
            class="pointer-events-auto flex items-center gap-3 rounded-xl border px-5 py-4 shadow-2xl backdrop-blur-sm"
        >
            <span
                class="material-symbols-outlined mt-0.5 shrink-0 text-xl"
                :class="iconColor(toast.type)"
                x-text="icon(toast.type)"
            ></span>
            <p
                class="flex-1 text-sm font-medium"
                x-text="toast.message"
            ></p>
            <button
                @click="remove(toast.id)"
                class="shrink-0 opacity-60 transition-opacity hover:opacity-100"
            >
                <span
                    class="material-symbols-outlined text-base"
                >close</span>
            </button>
        </div>
    </template>
</div>
