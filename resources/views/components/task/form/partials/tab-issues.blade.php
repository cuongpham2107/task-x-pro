@php
    $isCeo = auth()->user()?->hasRole('ceo');
@endphp

<div
    class="animate-in fade-in slide-in-from-bottom-2 {{ $this->isCompletedLocked || $isCeo ? 'pointer-events-none select-none opacity-70' : '' }} duration-300">
    <div class="grid grid-cols-1 gap-6">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <x-ui.textarea label="Vấn đề phát sinh" name="issue_note"
                placeholder="Mô tả các vấn đề, vướng mắc, rủi ro đang gặp..." rows="6" wire:model="issue_note"
                icon="error_outline" icon-color="text-amber-500" class="min-h-36" :disabled="$isCeo" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <x-ui.textarea label="Đề xuất cải tiến" name="recommendation"
                placeholder="Đề xuất giải pháp, hướng xử lý hoặc cải tiến tiếp theo..." rows="6"
                wire:model="recommendation" icon="tips_and_updates" icon-color="text-primary" class="min-h-36"
                :disabled="$isCeo" />
        </div>
    </div>
</div>
