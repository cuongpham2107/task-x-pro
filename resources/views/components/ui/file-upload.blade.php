@props([
    'name', // wire:model name for new files, e.g. "files"
    'existingAttachments' => [], // Collection of existing media/attachment models
    'multiple' => true,
    'label' => 'Đính kèm tệp',
    'accept' => null, // e.g. "image/*"
])

@php
    // We assume the parent component has a property $files (or whatever name is passed)
    // accessible via $this->$name if it was a simple property, but in blade components
    // we access the wire:model data via the attributes bag or just assume the parent context.
    
    // For counting new files, we rely on the parent passing the array or checking the wire:model property.
    // However, Blade components don't easily access parent public properties by string name dynamically without passing them.
    // So we'll assume the user passes the new files array as a prop if they want the counter to work immediately,
    // OR we just rely on Livewire's entangle if we wanted to get complex.
    // Simpler approach: Accept 'newFiles' prop.
@endphp

@props(['newFiles' => []])

<div
    class="col-span-full space-y-3"
    x-data="{
        isDragging: false,
        formatSize(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
            return bytes + ' B';
        }
    }"
    @dragover.prevent="isDragging = true"
    @dragleave.prevent="isDragging = false"
    @drop.prevent="isDragging = false; @this.uploadMultiple('{{ $name }}', $event.dataTransfer.files)"
>
    <span class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
        <span class="material-symbols-outlined text-base text-slate-400">attach_file</span>
        {{ $label }}
        @php
            $count = count($newFiles) + (is_countable($existingAttachments) ? count($existingAttachments) : 0);
        @endphp
        @if ($count > 0)
            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-500 dark:bg-slate-800">
                {{ $count }}
            </span>
        @endif
    </span>

    {{-- Danh sách file cũ --}}
    @if (is_countable($existingAttachments) && count($existingAttachments) > 0)
        <div class="space-y-1.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tệp đã tải lên</p>
            @foreach ($existingAttachments as $attachment)
                @php
                    // Handle different attachment structures if needed, defaulting to standard media library
                    $attachmentUrl = method_exists($attachment, 'getFirstMediaUrl') 
                        ? $attachment->getFirstMediaUrl('attachment') 
                        : ($attachment->url ?? '#');
                    $originalName = $attachment->original_name ?? $attachment->file_name ?? 'Unknown';
                    $size = $attachment->size_bytes ?? $attachment->size ?? 0;
                    $uploaderName = $attachment->uploader->name ?? 'Unknown';
                @endphp
                <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                    <span class="material-symbols-outlined text-primary shrink-0 text-lg">description</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $originalName }}</p>
                        <p class="text-xs text-slate-400">{{ number_format($size / 1024, 1) }} KB • {{ $uploaderName }}</p>
                    </div>
                    @if ($attachmentUrl !== '#' && $attachmentUrl !== '')
                        <a href="{{ $attachmentUrl }}" target="_blank" class="shrink-0 text-slate-400 transition-colors hover:text-primary">
                            <span class="material-symbols-outlined text-base">open_in_new</span>
                        </a>
                    @endif
                    <button
                        class="shrink-0 text-slate-400 transition-colors hover:text-red-500"
                        type="button"
                        wire:click="deleteAttachment({{ $attachment->id }})"
                        wire:confirm="Bạn có chắc chắn muốn xóa tệp này không?"
                    >
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Danh sách file mới đang chờ --}}
    @if (count($newFiles) > 0)
        <div class="space-y-1.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tệp mới chuẩn bị tải lên</p>
            @foreach ($newFiles as $index => $file)
                <div class="border-primary/20 bg-primary/5 flex items-center gap-3 rounded-lg border px-3 py-2">
                    <span class="material-symbols-outlined text-primary shrink-0 text-lg">upload_file</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $file->getClientOriginalName() }}</p>
                        <p class="text-xs text-slate-400">{{ number_format($file->getSize() / 1024, 1) }} KB</p>
                    </div>
                    <button
                        class="shrink-0 text-slate-400 transition-colors hover:text-red-500"
                        type="button"
                        wire:click="$set('{{ $name }}.{{ $index }}', null)"
                    >
                        <span class="material-symbols-outlined text-base">close</span>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Drop zone --}}
    <div wire:loading.class="opacity-50 pointer-events-none" wire:target="{{ $name }}">
        <label
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-6 transition-colors"
            :class="isDragging ? 'border-primary bg-primary/5' : 'border-slate-300 bg-slate-50 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:bg-slate-800'"
        >
            <span class="material-symbols-outlined text-3xl" :class="isDragging ? 'text-primary' : 'text-slate-400'">upload_file</span>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                Kéo thả tệp vào đây hoặc <span class="text-primary">nhấp để chọn</span>
            </p>
            <p class="text-xs text-slate-400">JPG, PNG, PDF, DOCX, ZIP... (tối đa 25MB / tệp)</p>
            <input
                class="hidden"
                type="file"
                wire:model="{{ $name }}"
                @if($multiple) multiple @endif
                @if($accept) accept="{{ $accept }}" @endif
            />
        </label>
    </div>
    <div class="text-primary animate-pulse text-xs" wire:loading wire:target="{{ $name }}">
        Đang tải tệp lên máy chủ...
    </div>
</div>
