<x-mail::message>
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error')
# Maaf, terjadi kesalahan!
@else
# Halo!
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@else
Salam,<br>
{{ config('app.name') }}
@endif

@isset($actionText)
<x-slot:subcopy>
Jika tombol "{{ $actionText }}" tidak dapat diklik, salin dan tempel tautan berikut ke browser Anda:
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
