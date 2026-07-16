<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ config('app.name') }}</title>
    <style type="text/css">
        a { text-decoration: none; outline: none; }
        @media (max-width: 649px) {
            .o_hide-lg { display: inline-block !important; font-size: inherit !important; max-height: none !important; line-height: inherit !important; overflow: visible !important; width: auto !important; visibility: visible !important; }
            .o_xs-center { text-align: center !important; }
            h2.o_heading { font-size: 26px !important; line-height: 37px !important; }
            .o_xs-py-md { padding-top: 24px !important; padding-bottom: 24px !important; }
        }
        @media screen {
            @font-face {
                font-family: 'Roboto';
                font-style: normal;
                font-weight: 400;
                src: local("Roboto"), local("Roboto-Regular"), url(https://fonts.gstatic.com/s/roboto/v18/KFOmCnqEu92Fr1Mu7GxKOzY.woff2) format("woff2");
            }
            @font-face {
                font-family: 'Roboto';
                font-style: normal;
                font-weight: 700;
                src: local("Roboto Bold"), local("Roboto-Bold"), url(https://fonts.gstatic.com/s/roboto/v18/KFOlCnqEu92Fr1MmWUlfBBc4.woff2) format("woff2");
            }
            .o_sans, .o_heading { font-family: "Roboto", sans-serif !important; }
            .o_heading, strong, b { font-weight: 700 !important; }
            a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
        }
    </style>
    <!--[if mso]>
    <style>
        table { border-collapse: collapse; }
        .o_col { float: left; }
    </style>
    <xml>
        <o:OfficeDocumentSettings>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
</head>
<body class="o_body o_bg-white" style="width: 100%;margin: 0px;padding: 0px;-webkit-text-size-adjust: 100%;-ms-text-size-adjust: 100%;background-color: #ffffff;">
    @isset($preheader)
        <div style="display: none;font-size: 1px;color: #ffffff;line-height: 1px;max-height: 0px;max-width: 0px;opacity: 0;overflow: hidden;mso-hide: all;">
            {{ $preheader }}
            @for ($i = 0; $i < 60; $i++)
                &zwnj;&nbsp;
            @endfor
        </div>
    @endisset

    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
        <tbody>
            <tr>
                <td class="o_re o_bg-white o_px o_pb-md" align="center" style="font-size: 0;vertical-align: top;background-color: #ffffff;padding-left: 16px;padding-right: 16px;padding-bottom: 24px;">
                    <div class="o_col o_col-2" style="display: inline-block;vertical-align: top;width: 100%;max-width: 175px;">
                        <div style="font-size: 24px; line-height: 24px; height: 24px;">&nbsp; </div>
                        <div class="o_px-xs o_sans o_text o_left o_xs-center" style="font-family: Helvetica, Arial, sans-serif;margin-top: 0px;margin-bottom: 0px;font-size: 22px;line-height: 24px;text-align: left;padding-left: 8px;padding-right: 8px;">
                            <p style="margin-top: 0px;margin-bottom: 0px;"><a href="https://proudify.in/" style="text-decoration: none;outline: none;color: #b40012;font-weight: bold;">Proudify</a></p>
                            <p style="margin-top: 0px;margin-bottom: 0px;font-size: 11px;color: #888888;letter-spacing: 0.5px;">CERTIFICATIONS. SIMPLIFIED.</p>
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
        <tbody>
            <tr>
                <td class="o_bg-ultra_light o_px-md o_py-xl o_xs-py-md" align="center" style="background-color: #ffe3e3;padding-left: 24px;padding-right: 24px;padding-top: 64px;padding-bottom: 64px;">
                    <div class="o_col-6s o_sans o_text-md o_text-light o_center" style="font-family: Helvetica, Arial, sans-serif;margin-top: 0px;margin-bottom: 0px;font-size: 19px;line-height: 28px;max-width: 584px;color: #82899a;text-align: center;">
                        <h2 class="o_heading o_text-dark o_mb-xxs" style="font-family: Helvetica, Arial, sans-serif;font-weight: bold;margin-top: 0px;margin-bottom: 4px;color: #242b3d;font-size: 42px;line-height: 39px;">{{ $heroTitle }}</h2>
                        @isset($heroSubtitle)
                            <p style="margin-top: 0px;margin-bottom: 0px;">{{ $heroSubtitle }}</p>
                        @endisset
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
        <tbody>
            <tr>
                <td class="o_bg-white" style="font-size: 24px;line-height: 24px;height: 24px;background-color: #ffffff;">&nbsp; </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
        <tbody>
            <tr>
                <td class="o_bg-white o_px-md o_py" align="center" style="background-color: #ffffff;padding: 16px;">
                    <div class="o_col-6s o_sans o_text o_text-secondary o_center" style="font-family: Helvetica, Arial, sans-serif;font-size: 16px;line-height: 24px;color: #424651;text-align: left;max-width: 584px;">
                        {{ $slot }}
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    @isset($afterContent)
        {{ $afterContent }}
    @endisset

    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
        <tbody>
            <tr>
                <td class="o_bg-white" style="font-size: 24px;line-height: 24px;height: 24px;background-color: #ffffff;">&nbsp; </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
        <tbody>
            <tr>
                <td class="o_re o_bg-white o_px-md o_pb-lg" align="center" style="font-size: 0;vertical-align: top;background-color: #ffffff;padding-left: 24px;padding-right: 24px;padding-bottom: 32px;">
                    <div class="o_col-6s o_sans o_text-xs o_text-light o_center" style="font-family: Helvetica, Arial, sans-serif;margin-top: 0px;margin-bottom: 0px;font-size: 14px;line-height: 21px;max-width: 584px;color: #82899a;text-align: center;">
                        <p class="o_mb-xs" style="margin-top: 0px;margin-bottom: 8px;">This email was generated using <a href="https://proudify.in" style="color: #82899a;">Proudify.in</a><br>
                            A Product by <a href="https://www.obiikriationz.com" style="color: #82899a;">Obii Kriationz Web LLP</a>
                        </p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
