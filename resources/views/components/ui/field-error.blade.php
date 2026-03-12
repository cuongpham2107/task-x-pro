{{--
    Field-level validation error.

    Usage:
      <x-ui.field-error field="name" />
      <x-ui.field-error field="email" bag="login" />
--}}

@props([
    'field',
    'bag' => 'default',
])

@error($field, $bag)
    <p {{ $attributes->class(['text-red-500 text-xs mt-1 flex items-center gap-1']) }}>
        <span class="material-symbols-outlined text-sm">error</span>
        {{ $message }}
    </p>
@enderror
