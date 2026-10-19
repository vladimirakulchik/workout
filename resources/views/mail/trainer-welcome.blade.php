<x-mail::message>
# {{ __('Welcome to :app, :name!', ['app' => config('app.name'), 'name' => $trainer->first_name]) }}

{{ __('A trainer account has been created for you. Use the details below to sign in:') }}

**{{ __('Email') }}:** {{ $trainer->email }}
**{{ __('Temporary password') }}:** {{ $temporaryPassword }}

<x-mail::button :url="$verificationUrl">
{{ __('Verify email address') }}
</x-mail::button>

{{ __('1. Verify your email address using the button above.') }}
{{ __('2. Sign in at :url with the temporary password.', ['url' => $loginUrl]) }}
{{ __('3. You will be asked to choose a new password on your first login.') }}

{{ __('If you did not expect this email, you can ignore it.') }}

{{ config('app.name') }}
</x-mail::message>
