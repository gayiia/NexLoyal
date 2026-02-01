{{-- This email template notifies customers about birthday reward points. --}}
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        {{-- The title is used by email clients as a preview label. --}}
        <title>Happy Birthday!</title>
    </head>
    {{-- Inline styles are used for maximum email client compatibility. --}}
    <body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f4f5f7;color:#1f2933;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:12px;padding:24px;box-shadow:0 12px 30px rgba(17,24,39,0.08);">
                        <tr>
                            <td>
                                {{-- The customer name falls back to a generic greeting. --}}
                                <h1 style="margin:0 0 12px;font-size:22px;">Happy Birthday, {{ $customer->full_name ?: 'there' }}!</h1>
                                <p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#5b6777;">
                                    {{-- Points value is injected from the reward issuance. --}}
                                    We just added {{ $points }} reward points to your account. Enjoy your special month!
                                </p>
                                <p style="margin:0;font-size:13px;color:#5b6777;">
                                    Thank you for being part of NexLoyal.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
