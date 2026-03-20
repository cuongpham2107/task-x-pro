@props([
    'model',
    'users' => collect(),
    'label' => 'Người phụ trách',
    'placeholder' => 'Tìm tên hoặc email...',
    'emptyText' => 'Không tìm thấy người dùng',
    'searchIcon' => 'search',
    'emptyIcon' => 'person_search',
    'dropdownPosition' => 'bottom',
    'required' => false,
])

@php
    $userOptions = collect($users)
        ->map(function ($user): array {
            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
                'avatar' => $user->avatar_url ?? $user->avatar,
            ];
        })
        ->values()
        ->all();

    $dropdownPosition = in_array($dropdownPosition, ['top', 'bottom'], true) ? $dropdownPosition : 'bottom';
    $dropdownPlacementClasses = $dropdownPosition === 'top' ? 'bottom-full mb-2' : 'top-full mt-2';
@endphp

<div class="space-y-2" x-data="{
    search: '',
    showDropdown: false,
    selectedId: @entangle($model).live,
    allUsers: {{ Js::from($userOptions) }},
    get selectedUser() {
        if (!this.selectedId) return null;
        return this.allUsers.find(user => Number(user.id) === Number(this.selectedId));
    },
    get filtered() {
        if (!this.search.trim()) {
            return this.allUsers;
        }

        const query = this.search.toLowerCase();

        return this.allUsers.filter(user => {
            const name = (user.name ?? '').toLowerCase();
            const email = (user.email ?? '').toLowerCase();
            return name.includes(query) || email.includes(query);
        });
    },
    select(user) {
        this.selectedId = Number(user.id);
        this.search = '';
        this.showDropdown = false;
    },
    clear() {
        this.selectedId = null;
        this.search = '';
    }
}" @click.outside="showDropdown = false">
    <label class="label-text">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    <div class="relative mt-1">
        {{-- Trigger / Display --}}
        <div @click="showDropdown = !showDropdown; $nextTick(() => { if (showDropdown) $refs.searchInput.focus() })"
            class="input-field flex cursor-pointer items-center justify-between gap-2 overflow-hidden bg-white py-2 pl-3 pr-2 transition-all hover:border-slate-400 dark:bg-slate-900"
            :class="showDropdown ? 'border-primary ring-2 ring-primary/20' : 'border-slate-300 dark:border-slate-700'">
            <div class="flex min-w-0 items-center gap-2.5">
                <template x-if="selectedUser">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <template x-if="selectedUser.avatar">
                            <img :src="selectedUser.avatar"
                                class="h-6 w-6 shrink-0 rounded-full object-cover shadow-sm" />
                        </template>
                        <template x-if="!selectedUser.avatar">
                            <div
                                class="bg-primary/20 text-primary flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold">
                                <span x-text="(selectedUser.name?.charAt(0) ?? '?').toUpperCase()"></span>
                            </div>
                        </template>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900 dark:text-white"
                                x-text="selectedUser.name"></p>
                        </div>
                    </div>
                </template>

                <template x-if="!selectedUser">
                    <span class="text-sm text-slate-400">{{ $placeholder }}</span>
                </template>
            </div>

            <div class="flex shrink-0 items-center gap-1 text-slate-400">
                <template x-if="selectedUser">
                    <button type="button" @click.stop="clear()" class="transition-colors hover:text-red-500">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </template>
                <span class="material-symbols-outlined text-xl transition-transform duration-200"
                    :class="showDropdown ? 'rotate-180' : ''">expand_more</span>
            </div>
        </div>

        {{-- Dropdown --}}
        <div x-show="showDropdown" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-100" x-transition:leave-start="scale-100 opacity-100"
            x-transition:leave-end="scale-95 opacity-0"
            class="{{ $dropdownPlacementClasses }} absolute left-0 z-30 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
            style="display: none;">
            <div class="border-b border-slate-100 p-2 dark:border-slate-800">
                <div class="relative">
                    <span
                        class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-base text-slate-400">{{ $searchIcon }}</span>
                    <input x-ref="searchInput" x-model="search" type="text" placeholder="Tìm kiếm..."
                        class="focus:border-primary focus:ring-primary/30 w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-8 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        @keydown.escape="showDropdown = false" />
                </div>
            </div>

            <div class="custom-scrollbar max-h-60 overflow-y-auto">
                <template x-if="filtered.length === 0">
                    <div class="px-4 py-8 text-center text-sm text-slate-400">
                        <span
                            class="material-symbols-outlined mb-2 block text-3xl font-light">{{ $emptyIcon }}</span>
                        {{ $emptyText }}
                    </div>
                </template>

                <template x-for="user in filtered" :key="user.id">
                    <button type="button" @click="select(user)"
                        class="group w-full border-b border-slate-50 transition-colors last:border-none dark:border-slate-800/50"
                        :class="Number(selectedId) === Number(user.id) ? 'bg-primary/5' :
                            'hover:bg-slate-50 dark:hover:bg-slate-800/50'">
                        <div class="flex items-center gap-3 px-4 py-3">
                            <template x-if="user.avatar">
                                <img :src="user.avatar" :alt="user.name"
                                    class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-slate-800" />
                            </template>
                            <template x-if="!user.avatar">
                                <div
                                    class="bg-primary/20 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold shadow-sm">
                                    <span x-text="(user.name?.charAt(0) ?? '?').toUpperCase()"></span>
                                </div>
                            </template>

                            <div class="min-w-0 flex-1 text-left">
                                <p class="truncate text-sm font-bold transition-colors"
                                    :class="Number(selectedId) === Number(user.id) ? 'text-primary' :
                                        'text-slate-900 dark:text-white'"
                                    x-text="user.name"></p>
                                <p class="truncate text-xs text-slate-400" x-text="user.email"></p>
                            </div>

                            <template x-if="Number(selectedId) === Number(user.id)">
                                <span class="material-symbols-outlined text-primary font-bold">check_circle</span>
                            </template>
                        </div>
                    </button>
                </template>
            </div>

        </div>


    </div>
    @if ($model)
        <x-ui.field-error field="{{ $model }}" />
    @endif
</div>
