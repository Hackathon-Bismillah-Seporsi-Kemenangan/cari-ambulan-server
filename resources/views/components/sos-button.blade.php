<div class="relative flex flex-col items-center gap-4">
    <span class="absolute size-52 animate-ping rounded-full bg-danger/5 [animation-duration:2s]"></span>
    <span class="absolute size-44 rounded-full bg-danger/10"></span>
    <span class="absolute size-40 rounded-full bg-danger/15"></span>
    <a href="{{ $href ?? '#panggil' }}" {{ $attributes->merge(['class' => 'relative flex size-36 flex-col items-center justify-center gap-1.5 rounded-full bg-danger text-white border-2 border-danger/60 shadow-md transition active:scale-95']) }}>
        <x-icon name="siren" class="size-7" />
        <span class="text-sm font-bold tracking-wide">CARI</span>
        <span class="text-sm font-bold tracking-wide">AMBULANS</span>
        <span class="text-[10px] font-medium opacity-80">{{ $subtitle ?? 'Tekan 2 detik darurat' }}</span>
    </a>
</div>
