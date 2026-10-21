<x-mail::message>
# {{ __('Welcome to :app, :name!', ['app' => config('app.name'), 'name' => $coach->first_name]) }}

{{ __('You are now a coach for :trainer.', ['trainer' => $trainerName]) }}

<x-mail::button :url="$loginUrl">
{{ __('Log in') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
