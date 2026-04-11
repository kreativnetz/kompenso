<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Themeneingabe</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', system-ui, -apple-system, Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.55; color: #0f172a;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f1f5f9; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0;">
                    <tr>
                        <td style="padding: 28px 28px 20px; text-align: center; background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); border-bottom: 1px solid #e2e8f0;">
                            <img src="{{ $logoSrc }}" alt="Mentor Match" width="220" style="max-width: 220px; width: 100%; height: auto; display: inline-block; border: 0; outline: none; text-decoration: none;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px 28px 8px;">
                            <p style="margin: 0 0 12px; font-size: 16px; color: #011c44; font-weight: 600;">Liebe Lernende</p>
                            <p style="margin: 0 0 20px; font-size: 15px; color: #334155;">
                                Ihr habt das Thema eurer Abschlussarbeit erfolgreich eingegeben. Hier die Angaben zur Kontrolle:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 28px 16px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 16px 18px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #03a3aa;">Zuordnung</p>
                                        <p style="margin: 0; font-size: 14px; color: #0f172a;">
                                            <strong style="color: #011c44;">Zyklus:</strong> {{ $sessionName }}
                                            @if ($schoolyearLabel)
                                                <br><strong style="color: #011c44;">Schuljahr:</strong> {{ $schoolyearLabel }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 28px 16px;">
                            <p style="margin: 0 0 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;">Titel der Abschlussarbeit</p>
                            <p style="margin: 0; font-size: 15px; color: #0f172a;">{{ $title }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 28px 16px;">
                            <p style="margin: 0 0 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;">Kurzbeschreibung</p>
                            <p style="margin: 0; font-size: 15px; color: #334155;">{!! nl2br(e($description)) !!}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 28px 20px;">
                            <p style="margin: 0 0 10px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b;">Lernende</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                @foreach ($authors as $a)
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #0f172a;">
                                            {{ $a['first_name'] }} {{ $a['last_name'] }} <span style="color: #64748b;">· Klasse {{ $a['class'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 28px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background: linear-gradient(135deg, #011c44 0%, #0f2847 100%); border-radius: 10px;">
                                <tr>
                                    <td style="padding: 18px 20px; text-align: center;">
                                        <p style="margin: 0 0 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.12em; color: #94a3b8;">Bearbeitungscode</p>
                                        <p style="margin: 0; font-family: ui-monospace, 'SF Mono', Consolas, monospace; font-size: 22px; font-weight: 700; letter-spacing: 0.12em; color: #ffffff;">{{ $editCode }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 14px 0 0; font-size: 13px; color: #64748b; text-align: center; line-height: 1.45;">
                                Mit diesem Code könnt ihr das Thema noch anpassen, solange die Ausschreibung die Bearbeitung erlaubt.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 28px 24px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #94a3b8;">{{ config('app.name') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
