<x-mail::message>
# {{ __('Hi :name,', ['name' => $parentName]) }}

{{ __(':child wants to join :trainer\'s program.', ['child' => $childName, 'trainer' => $trainerName]) }}

{{ __('Child accounts cannot register with a trainer. Review the invitation and register :child yourself.', ['child' => $childName]) }}

<x-mail::button :url="$joinUrl">
{{ __('Review Registration') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
