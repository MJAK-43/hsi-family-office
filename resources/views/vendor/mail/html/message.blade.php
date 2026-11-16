<x-mail::layout>
{{-- En-tête --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Corps --}}
{!! $slot !!}

{{-- Sous-texte --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Pied de page --}}
<x-slot:footer>
<x-mail::footer>
<span class="motto">{{ __('Une famille. Une vision. Un héritage.') }}</span>

**{{ __('Fonds familial JOKUNG') }}** · HSI — Héritage & Sagesse International<br>
{{ __('Vous recevez cet email car vous êtes membre ou invité du fonds familial.') }}<br>
{{ __('Une question ? Écrivez à') }} [contact@inovixora.com](mailto:contact@inovixora.com)

© {{ date('Y') }} HSI. {{ __('Tous droits réservés.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
