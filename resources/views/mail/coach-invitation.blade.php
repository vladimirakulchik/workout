<x-mail::message>
# {{ $coachName ? __('Hi :name,', ['name' => $coachName]) : __('Hello,') }}

{{ __(':trainer invited you to join their team as a coach on :app.', ['trainer' => $trainerName, 'app' => config('app.name')]) }}

@if ($personalMessage)
<x-mail::panel>
{{ $personalMessage }}
</x-mail::panel>
@endif

<x-mail::button :url="$joinUrl">
{{ __('Accept Invitation') }}
</x-mail::button>

{{ __('This link can be used once and expires on :date.', ['date' => $expiresAt]) }}

{{ config('app.name') }}
</x-mail::message>
