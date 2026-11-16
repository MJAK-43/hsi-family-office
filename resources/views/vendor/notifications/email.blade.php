<x-mail::message>
{{-- Titre --}}
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error')
# {{ __('Une erreur est survenue') }}
@else
# {{ __('Bonjour,') }}
@endif

{{-- Introduction --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Bouton d'action --}}
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

{{-- Conclusion --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Signature --}}
@if (! empty($salutation))
{{ $salutation }}
@else
{{ __('Bien cordialement,') }}<br>
**{{ __("L'équipe HSI · Fonds familial JOKUNG") }}**
@endif

{{-- Lien de secours --}}
@isset($actionText)
<x-slot:subcopy>
{{ __('Le bouton « :action » ne fonctionne pas ? Copiez ce lien dans votre navigateur :', ['action' => $actionText]) }}
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
