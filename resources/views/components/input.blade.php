@props([
    'name',
    'label' => null,
    'type' => 'text',
    'id' => null,
    'value' => null,
    'placeholder' => '',
    'required' => false,
    'autofocus' => false,
    'autocomplete' => '',
    'icon' => null,
    'error' => null,
])

@php $invalid = filled($error); @endphp
<div class="w-full" {{ $attributes->except('name')->class('mb-4') }}>
    @if($label)
        <label for="{{ $id ?? $name }}" class="mb-1.5 block text-sm font-semibold text-ink">
            {{ $label }} @if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <div class="relative">
        @if($icon)
            <x-icon name="{{ $icon }}" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-muted" />
        @endif
        <input
            type="{{ $type }}"
            id="{{ $id ?? $name }}"
            name="{{ $name }}"
            value="{{ $value ?? ($type === 'password' ? '' : old($name)) }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            @if($autofocus) autofocus @endif
            autocomplete="{{ $autocomplete }}"
            {{ $attributes->merge(['class' => 'h-12 w-full rounded-xl border bg-card px-4 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/40 ' . ($invalid ? 'border-danger' : 'border-line focus:border-primary') . ($icon ? ' pl-11' : '')]) }}
        >
    </div>
    @if($invalid)
        <p class="mt-1.5 text-xs font-medium text-danger">{{ __($error) }}</p>
    @endif
</div>
