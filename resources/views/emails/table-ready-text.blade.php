{{ $venueName }}

Hi {{ $customerName }},

Your table is ready. Please go to the host desk within {{ $holdMinutes }} {{ $holdMinutes === 1 ? 'minute' : 'minutes' }}.

Confirmation code: {{ $confirmationCode }}
@if ($tableLabel)
Held table: {{ $tableLabel }}
@endif

Show this email or tell the confirmation code to staff when you arrive at the desk.
