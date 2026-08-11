<x-mail::message>
# Your verification code

Hello {{ $name }},

Use this code to finish signing in to {{ config('app.name') }}:

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

It expires in {{ $expiresIn }} {{ \Illuminate\Support\Str::plural('minute', $expiresIn) }}.

If you did not try to sign in, you can ignore this email — no one can get in
with your email address alone. If it keeps happening, please tell us.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
