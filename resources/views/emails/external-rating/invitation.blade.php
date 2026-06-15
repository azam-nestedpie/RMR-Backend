<x-mail::message>
# External rating invitation

You have been invited to submit an external rating.

<x-mail::button :url="$url">
Open rating form
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
