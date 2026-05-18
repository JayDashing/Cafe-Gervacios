<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your table is ready</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:28px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 28px 8px 28px;">
                            <p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#64748b;">{{ $venueName }}</p>
                            <h1 style="margin:10px 0 0 0;font-size:22px;font-weight:700;color:#0f172a;line-height:1.3;">Your table is ready</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 24px 28px;">
                            <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Hi {{ $customerName }},
                            </p>
                            <p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Please go to the host desk within {{ $holdMinutes }} {{ $holdMinutes === 1 ? 'minute' : 'minutes' }}.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:6px;background-color:#f8fafc;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0 0 6px 0;font-size:12px;text-transform:uppercase;letter-spacing:0.06em;color:#64748b;">Confirmation code</p>
                                        <p style="margin:0;font-size:20px;font-weight:700;letter-spacing:0.05em;color:#0f172a;font-family:ui-monospace,Menlo,Monaco,Consolas,monospace;">{{ $confirmationCode }}</p>
                                    </td>
                                </tr>
                                @if ($tableLabel)
                                    <tr>
                                        <td style="padding:0 18px 16px 18px;border-top:1px solid #e2e8f0;">
                                            <p style="margin:14px 0 6px 0;font-size:12px;text-transform:uppercase;letter-spacing:0.06em;color:#64748b;">Held table</p>
                                            <p style="margin:0;font-size:15px;color:#0f172a;font-weight:700;">{{ $tableLabel }}</p>
                                        </td>
                                    </tr>
                                @endif
                            </table>
                            <p style="margin:18px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                                Show this email or tell the confirmation code to staff when you arrive at the desk.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 26px 28px;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;">This message was sent because your waitlist entry is ready for seating.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
