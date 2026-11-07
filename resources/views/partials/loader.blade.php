{{-- Animasi loading: karton dimuat ke bak truk. Variabel opsional: $text, $id (id elemen teks) --}}
<span class="loader" aria-hidden="true">
    <span class="loader__truck">
        <span class="loader__box"></span><span class="loader__box"></span><span class="loader__box"></span>
    </span>
    <span class="loader__road"></span>
</span>
@isset($text)<span class="loader__text" @isset($id) id="{{ $id }}" @endisset>{{ $text }}</span>@endisset
