<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px;">
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 16px; font-size:20px; line-height:1.4;">{{ $greeting ?? 'Hello there!' }}</h1>

                @foreach (preg_split("/\r\n|\r|\n/", trim((string) ($body ?? ''))) as $paragraph)
                    @if ($paragraph !== '')
                        <p style="margin:0 0 12px; font-size:15px; line-height:1.65;">{{ $paragraph }}</p>
                    @endif
                @endforeach

                @if (!empty($actionLabel) && !empty($actionUrl))
                    <p style="margin:20px 0 0;">
                        <a
                            href="{{ $actionUrl }}"
                            style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:600;"
                        >
                            {{ $actionLabel }}
                        </a>
                    </p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
