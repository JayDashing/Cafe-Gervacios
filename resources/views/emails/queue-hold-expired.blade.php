<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Waitlist hold expired</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:28px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 28px 8px 28px;">
                            <p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#64748b;">{{ $venueName }}</p>
                            <h1 style="margin:10px 0 0 0;font-size:22px;font-weight:700;color:#0f172a;line-height:1.3;">Your waitlist hold expired</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 26px 28px;">
                            <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#334155;">Hi {{ $customerName }},</p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#334155;">
                                We could not seat you within the hold time. Your reserved table was released. Please rejoin the queue if you are still waiting.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
