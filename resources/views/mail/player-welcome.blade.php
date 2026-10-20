<x-mail::message>
# {{ __('Welcome to :app, :name!', ['app' => config('app.name'), 'name' => $player->first_name]) }}

{{ __('Your account is connected to :trainer.', ['trainer' => $trainerName]) }}

{{ __('Please verify your email address using the link we sent in a separate message, then sign in to see upcoming events.') }}

<x-mail::button :url="$loginUrl">
{{ __('Log in') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
