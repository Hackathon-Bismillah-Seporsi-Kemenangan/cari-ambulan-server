@props(['status' => 'pending', 'note' => null])
@php
    $steps = [
        'searching_driver' => 'Mencari ambulans',
        'driver_assigned' => 'Pesan diterima',
        'en_route_to_pickup' => 'Dalam perjalanan',
        'arrive_at_pickup' => 'Tiba di lokasi',
        'en_route_to_hospital' => 'Menuju rumah sakit',
        'completed' => 'Selesai',
    ];
    $current = $status;
    $doneClass = 'bg-success text-white border-success';
    $activeClass = 'bg-danger text-white border-danger';
    $todoClass = 'bg-card text-muted border-line';
    $i = 0;
@endphp
<ol {{ $attributes->merge(['class' => 'flex flex-col']) }}>
    @foreach($steps as $key => $label)
        @php
            $currentOrder = match($current) {
                'searching_driver', 'pending' => 0,
                'driver_assigned', 'accepted' => 1,
                'en_route_to_pickup', 'picked_up' => 2,
                'arrive_at_pickup' => 3,
                'en_route_to_hospital' => 4,
                'completed' => 5,
                default => 0,
            };
            $isDone = $i < $currentOrder;
            $isActive = $i === $currentOrder;
        @endphp
        <li class="relative flex gap-3 pb-5 last:pb-0">
            @if(! $loop->last)
                <span class="absolute left-3 top-7 bottom-0 w-px {{ $isDone ? 'bg-success' : 'bg-line' }}"></span>
            @endif
            <span class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border-2 {{ $isDone ? $doneClass : ($isActive ? $activeClass : $todoClass) }}">
                @if($isDone)
                    <x-icon name="check" class="size-3.5" />
                @elseif($isActive)
                    <span class="size-2 rounded-full bg-white"></span>
                @endif
            </span>
            <div class="pt-0.5">
                <p class="text-sm font-semibold {{ $isDone ? 'text-ink/70' : ($isActive ? 'text-danger' : 'text-muted') }}">{{ $label }}</p>
                @if($isActive && isset($note))
                    <p class="text-xs text-ink/50">{{ $note }}</p>
                @endif
            </div>
        </li>
        @php $i++; @endphp
    @endforeach
</ol>
