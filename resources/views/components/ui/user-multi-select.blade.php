@props([
    'model',
    'users' => collect(),
    'label' => 'Nguoi phu trach',
    'placeholder' => 'Tim ten hoac email...',
    'emptyText' => 'Khong tim thay nguoi dung',
    'addIcon' => 'add',
    'searchIcon' => 'search',
    'emptyIcon' => 'person_search',
    'dropdownPosition' => 'bottom',
    'required' => false,
    'disabled' => false,
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
    selectedIds: @entangle($model).live,
    allUsers: {{ Js::from($userOptions) }},
    get selectedUsers() {
        const selected = this.selectedIds.map(id => Number(id));
        return this.allUsers.filter(user => selected.includes(Number(user.id)));
    },
    get filtered() {
        const selected = this.selectedIds.map(id => Number(id));

        if (!this.search.trim()) {
            return this.allUsers.filter(user => !selected.includes(Number(user.id)));
        }

        const query = this.search.toLowerCase();

        return this.allUsers.filter(user => {
            const id = Number(user.id);
            const name = (user.name ?? '').toLowerCase();
            const email = (user.email ?? '').toLowerCase();

            return !selected.includes(id) && (name.includes(query) || email.includes(query));
        });
    },
    add(user) {
        const id = Number(user.id);
        const selected = this.selectedIds.map(value => Number(value));

        if (!selected.includes(id)) {
            this.selectedIds = [...this.selectedIds, id];
        }

        this.search = '';
        this.showDropdown = false;
    },
    remove(id) {
        const numericId = Number(id);
        this.selectedIds = this.selectedIds.filter(value => Number(value) !== numericId);
    }
}" @click.outside="showDropdown = false">
    <label class="label-text">{{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="mt-1 flex flex-wrap items-center gap-2">
        <template x-for="user in selectedUsers" :key="user.id">
            <div
                class="bg-primary/10 text-primary flex items-center gap-1.5 rounded-full py-1 pl-1 pr-2 text-xs font-medium">
                <template x-if="user.avatar">
                    <img :src="user.avatar" :alt="user.name" class="h-5 w-5 rounded-full object-cover" />
                </template>
                <template x-if="!user.avatar">
                    <div class="bg-primary/30 text-2xs flex h-5 w-5 items-center justify-center rounded-full font-bold"
                        x-text="(user.name?.charAt(0) ?? '?').toUpperCase()"></div>
                </template>

                <span x-text="user.name"></span>

                <button type="button" @if (!$disabled) @click="remove(user.id)" @endif
                    class="{{ $disabled ? 'cursor-not-allowed' : 'hover:text-red-500' }} ml-0.5 transition-colors">
                    <span class="material-symbols-outlined text-xs leading-none">close</span>
                </button>
            </div>
        </template>

        <div class="relative">
            <button type="button"
                @if (!$disabled) @click="showDropdown = !showDropdown; $nextTick(() => { if (showDropdown) $refs.searchInput.focus() })" @endif
                class="{{ $disabled ? 'cursor-not-allowed opacity-50' : 'hover:border-primary hover:text-primary' }} flex h-8 w-8 items-center justify-center rounded-full border-2 border-dashed border-slate-300 text-slate-400 transition-all dark:border-slate-700">
                <span class="material-symbols-outlined text-base">{{ $addIcon }}</span>
            </button>

            <div x-show="showDropdown" x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
                x-transition:leave="transition ease-in duration-100" x-transition:leave-start="scale-100 opacity-100"
                x-transition:leave-end="scale-95 opacity-0"
                class="{{ $dropdownPlacementClasses }} absolute left-0 z-20 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-100 p-2 dark:border-slate-800">
                    <div class="relative">
                        <span
                            class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-base text-slate-400">{{ $searchIcon }}</span>
                        <input x-ref="searchInput" x-model="search" type="text" placeholder="{{ $placeholder }}"
                            class="focus:border-primary focus:ring-primary/30 w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-8 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" />
                    </div>
                </div>

                <div class="custom-scrollbar max-h-52 overflow-y-auto">
                    <template x-if="filtered.length === 0">
                        <div class="px-4 py-6 text-center text-sm text-slate-400">
                            <span class="material-symbols-outlined mb-1 block text-2xl">{{ $emptyIcon }}</span>
                            {{ $emptyText }}
                        </div>
                    </template>

                    <template x-for="user in filtered" :key="user.id">
                        <button type="button" @click="add(user)"
                            class="hover:bg-primary/5 w-full text-left transition-colors">
                            <div class="flex items-center gap-3 px-3 py-2.5">
                                <template x-if="user.avatar">
                                    <img :src="user.avatar" :alt="user.name"
                                        class="h-8 w-8 shrink-0 rounded-full object-cover" />
                                </template>
                                <template x-if="!user.avatar">
                                    <div class="bg-primary/20 text-primary flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                        x-text="(user.name?.charAt(0) ?? '?').toUpperCase()"></div>
                                </template>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-600 dark:text-white"
                                        x-text="user.name"></p>
                                    <p class="truncate text-xs text-slate-400" x-text="user.email"></p>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
