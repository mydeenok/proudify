<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Ten ready-to-use, visually distinct certificate templates. Deliberately
 * uses system font stacks only (Georgia/Times/Helvetica/Arial) instead of
 * Google Fonts `<link>` imports — an earlier hand-written template that did
 * import Google Fonts hung the headless-Chrome render whenever the sandbox
 * had no outbound network access, and these should render identically
 * online or offline. All ten use the same placeholder-token contract
 * CertificateRenderService already fills at issuance time (see that class
 * for the full list); `{date_of_expiry}` is only included on templates
 * where an expiry genuinely makes sense, since it renders literal text
 * "No Expiry" otherwise.
 */
class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

        foreach ($this->definitions() as $definition) {
            Template::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'category' => $definition['category'],
                    'html_content' => $definition['html'],
                    'page_format' => $definition['page_format'] ?? 'a4',
                    'orientation' => $definition['orientation'] ?? 'landscape',
                    'is_active' => true,
                    'is_exclusive' => false,
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    /**
     * @return array<int, array{name: string, category: string, html: string, orientation?: string, page_format?: string}>
     */
    private function definitions(): array
    {
        return [
            $this->classicElegant(),
            $this->modernMinimalist(),
            $this->corporateNavy(),
            $this->vibrantGradient(),
            $this->academicFormal(),
            $this->techDarkMode(),
            $this->achievementMedal(),
            $this->ecoNature(),
            $this->luxuryBlackGold(),
            $this->playfulCreative(),
        ];
    }

    private function classicElegant(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 50px; background: #faf6ee; font-family: Georgia, 'Times New Roman', serif; }
                .frame { border: 3px double #a4762c; padding: 40px; height: calc(100vh - 180px); position: relative; text-align: center; background: #fffdf8; }
                .corner { position: absolute; width: 46px; height: 46px; border: 2px solid #a4762c; }
                .tl { top: 14px; left: 14px; border-right: none; border-bottom: none; }
                .tr { top: 14px; right: 14px; border-left: none; border-bottom: none; }
                .bl { bottom: 14px; left: 14px; border-right: none; border-top: none; }
                .br { bottom: 14px; right: 14px; border-left: none; border-top: none; }
                .eyebrow { letter-spacing: 6px; font-size: 13px; color: #a4762c; text-transform: uppercase; margin-top: 20px; }
                h1 { font-size: 40px; color: #2c2417; margin: 10px 0 4px; }
                .lead { font-size: 15px; color: #5c5342; margin: 24px 0 6px; }
                .recipient { font-size: 34px; color: #a4762c; font-weight: bold; margin: 0 0 22px; border-bottom: 1px solid #d8c69a; display: inline-block; padding-bottom: 8px; }
                .desc { max-width: 640px; margin: 0 auto 30px; font-size: 15px; color: #4a4030; line-height: 1.6; font-style: italic; }
                .footer { position: absolute; bottom: 40px; left: 60px; right: 60px; display: flex; justify-content: space-between; align-items: flex-end; }
                .stamp { width: 92px; height: 92px; object-fit: contain; }
                .sig { width: 150px; height: 60px; object-fit: contain; margin-bottom: 6px; }
                .col { font-size: 12px; color: #5c5342; text-align: center; }
                .col .label { border-top: 1px solid #a4762c; padding-top: 6px; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
                .code { position: absolute; bottom: 10px; right: 20px; font-size: 10px; color: #b3a988; }
            </style>
            </head>
            <body>
                <div class="frame">
                    <div class="corner tl"></div><div class="corner tr"></div><div class="corner bl"></div><div class="corner br"></div>
                    <p class="eyebrow">{organization_name}</p>
                    <p class="eyebrow" style="margin-top:0;">Certificate</p>
                    <h1>{title}</h1>
                    <p class="lead">This certificate is proudly presented to</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="footer">
                        <div class="col"><img class="sig" src="{signature}" alt="Signature"><div class="label">Signature</div></div>
                        <img class="stamp" src="{qrcode}" alt="QR Code">
                        <div class="col"><div style="height:60px;display:flex;align-items:flex-end;justify-content:center;">{date_of_issue}</div><div class="label">Date Issued</div></div>
                    </div>
                    <span class="code">ID: {verification_code}</span>
                </div>
            </body>
            </html>
            HTML;

        return ['name' => 'Classic Elegant', 'category' => 'Achievement', 'html' => $html];
    }

    private function modernMinimalist(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 70px 90px; background: #ffffff; font-family: Helvetica, Arial, sans-serif; color: #111; }
                .top { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #111; padding-bottom: 18px; }
                .logo { height: 40px; width: auto; object-fit: contain; }
                .eyebrow { font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #777; }
                h1 { font-size: 46px; font-weight: 300; margin: 46px 0 4px; letter-spacing: -1px; }
                .lead { font-size: 14px; color: #777; margin: 0 0 30px; }
                .recipient { font-size: 30px; font-weight: 700; margin: 0 0 26px; }
                .desc { max-width: 620px; font-size: 14px; color: #444; line-height: 1.7; }
                .footer { position: absolute; bottom: 70px; left: 90px; right: 90px; display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #111; padding-top: 18px; }
                .col { font-size: 12px; color: #777; }
                .col strong { display: block; color: #111; font-size: 14px; margin-bottom: 2px; }
                .qr { width: 68px; height: 68px; object-fit: contain; }
                .sig { width: 130px; height: 44px; object-fit: contain; }
                .code { position: absolute; bottom: 24px; right: 90px; font-size: 10px; color: #aaa; letter-spacing: 1px; }
            </style>
            </head>
            <body>
                <div class="top">
                    <span class="eyebrow">{organization_name}</span>
                    <img class="logo" src="{company_logo}" alt="Logo">
                </div>
                <p class="eyebrow" style="margin-top:40px;">{title}</p>
                <h1>{recipient_name}</h1>
                <p class="lead">has successfully completed the requirements below</p>
                <p class="desc">{description}</p>
                <div class="footer">
                    <div class="col"><strong>{date_of_issue}</strong>Date Issued</div>
                    <img class="sig" src="{signature}" alt="Signature">
                    <img class="qr" src="{qrcode}" alt="QR Code">
                </div>
                <span class="code">{verification_code}</span>
            </body>
            </html>
            HTML;

        return ['name' => 'Modern Minimalist', 'category' => 'Completion', 'html' => $html];
    }

    private function corporateNavy(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #1b2432; }
                .band { background: #10233f; color: #fff; padding: 26px 60px; display: flex; justify-content: space-between; align-items: center; }
                .band .name { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
                .logo { height: 34px; object-fit: contain; }
                .content { padding: 50px 70px; }
                .eyebrow { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #10233f; font-weight: bold; }
                h1 { font-size: 36px; margin: 8px 0 26px; color: #10233f; }
                .lead { font-size: 13px; color: #556; margin-bottom: 4px; }
                .recipient { font-size: 30px; font-weight: bold; color: #1b2432; margin: 0 0 20px; }
                .desc { max-width: 700px; font-size: 14px; color: #445; line-height: 1.7; margin-bottom: 40px; }
                .grid { display: flex; gap: 40px; border-top: 3px solid #10233f; padding-top: 20px; }
                .cell { flex: 1; }
                .cell .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #889; margin-bottom: 4px; }
                .cell .value { font-size: 14px; font-weight: bold; color: #10233f; }
                .qr { width: 74px; height: 74px; object-fit: contain; }
                .sig { width: 130px; height: 50px; object-fit: contain; }
                .code { text-align: right; font-size: 10px; color: #9aa; margin-top: 14px; }
            </style>
            </head>
            <body>
                <div class="band">
                    <span class="name">{organization_name}</span>
                    <img class="logo" src="{company_logo}" alt="Logo">
                </div>
                <div class="content">
                    <p class="eyebrow">Certificate of {title}</p>
                    <h1>Official Recognition</h1>
                    <p class="lead">Awarded to</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="grid">
                        <div class="cell"><div class="label">Date Issued</div><div class="value">{date_of_issue}</div></div>
                        <div class="cell"><div class="label">Signature</div><img class="sig" src="{signature}" alt="Signature"></div>
                        <div class="cell" style="flex: 0 0 auto;"><div class="label">Verify</div><img class="qr" src="{qrcode}" alt="QR Code"></div>
                    </div>
                    <p class="code">Credential ID: {verification_code}</p>
                </div>
            </body>
            </html>
            HTML;

        return ['name' => 'Corporate Navy', 'category' => 'Recognition', 'html' => $html];
    }

    private function vibrantGradient(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; font-family: Helvetica, Arial, sans-serif; background: #fff; color: #26163f; }
                .banner { background: linear-gradient(120deg, #7c3aed, #ec4899, #f97316); padding: 70px 70px 90px; color: #fff; position: relative; }
                .eyebrow { font-size: 12px; letter-spacing: 4px; text-transform: uppercase; opacity: .9; }
                h1 { font-size: 44px; margin: 10px 0 0; }
                .badge { position: absolute; right: 70px; bottom: -34px; width: 88px; height: 88px; border-radius: 999px; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 24px rgba(0,0,0,.15); }
                .badge span { font-size: 30px; }
                .content { padding: 60px 70px 40px; }
                .lead { font-size: 14px; color: #6b5a86; }
                .recipient { font-size: 32px; font-weight: bold; margin: 6px 0 22px; background: linear-gradient(90deg,#7c3aed,#ec4899); -webkit-background-clip: text; background-clip: text; color: transparent; }
                .desc { max-width: 680px; font-size: 14px; color: #4b3d63; line-height: 1.7; margin-bottom: 36px; }
                .footer { display: flex; justify-content: space-between; align-items: flex-end; border-top: 2px dashed #e4d9f5; padding-top: 20px; }
                .col { font-size: 11px; color: #8a7aa3; text-align: center; }
                .sig { width: 130px; height: 46px; object-fit: contain; }
                .qr { width: 72px; height: 72px; object-fit: contain; }
                .code { position: absolute; bottom: 14px; right: 70px; font-size: 10px; color: #b7a9cb; }
            </style>
            </head>
            <body>
                <div class="banner">
                    <span class="eyebrow">{organization_name} presents</span>
                    <h1>{title}</h1>
                    <div class="badge"><span>&#127942;</span></div>
                </div>
                <div class="content">
                    <p class="lead">This certificate is awarded to</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="footer">
                        <div class="col">{date_of_issue}<br>Date</div>
                        <div class="col"><img class="sig" src="{signature}" alt="Signature"><br>Signature</div>
                        <div class="col"><img class="qr" src="{qrcode}" alt="QR Code"><br>Scan to verify</div>
                    </div>
                </div>
                <span class="code">{verification_code}</span>
            </body>
            </html>
            HTML;

        return ['name' => 'Vibrant Gradient', 'category' => 'Achievement', 'html' => $html];
    }

    private function academicFormal(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 60px 55px; background: #f7f3e9; font-family: 'Times New Roman', Georgia, serif; color: #2a2418; text-align: center; }
                .ring { width: 96px; height: 96px; border-radius: 999px; border: 3px solid #7a1f1f; margin: 0 auto 18px; display: flex; align-items: center; justify-content: center; }
                .ring span { font-size: 36px; }
                .university { font-size: 20px; letter-spacing: 2px; text-transform: uppercase; color: #7a1f1f; font-weight: bold; }
                .sub { font-size: 13px; color: #6b5f47; margin: 6px 0 30px; }
                h1 { font-size: 30px; margin: 0 0 6px; text-transform: uppercase; letter-spacing: 2px; }
                .lead { font-size: 14px; color: #5c5342; margin-bottom: 22px; }
                .recipient { font-size: 32px; font-family: Georgia, serif; font-style: italic; color: #2a2418; border-bottom: 2px solid #7a1f1f; display: inline-block; padding-bottom: 8px; margin-bottom: 24px; }
                .desc { max-width: 560px; margin: 0 auto 40px; font-size: 14px; line-height: 1.7; color: #4a4030; }
                .footer { display: flex; justify-content: space-around; align-items: flex-end; margin-top: 20px; }
                .col { font-size: 12px; }
                .sig { width: 140px; height: 50px; object-fit: contain; }
                .line { border-top: 1px solid #2a2418; width: 160px; margin: 6px auto 4px; padding-top: 4px; }
                .qr { width: 76px; height: 76px; object-fit: contain; }
                .code { margin-top: 30px; font-size: 10px; color: #a89a76; }
            </style>
            </head>
            <body>
                <div class="ring"><span>&#127891;</span></div>
                <p class="university">{organization_name}</p>
                <p class="sub">hereby confers upon</p>
                <p class="recipient">{recipient_name}</p>
                <p class="lead">the certificate of</p>
                <h1>{title}</h1>
                <p class="desc">{description}</p>
                <div class="footer">
                    <div class="col"><img class="sig" src="{signature}" alt="Signature"><div class="line">Registrar</div></div>
                    <div class="col"><img class="qr" src="{qrcode}" alt="QR Code"><div class="line">Verify Online</div></div>
                    <div class="col"><div style="height:50px;display:flex;align-items:flex-end;justify-content:center;">{date_of_issue}</div><div class="line">Date Conferred</div></div>
                </div>
                <p class="code">Valid through {date_of_expiry} &middot; Credential {verification_code}</p>
            </body>
            </html>
            HTML;

        return ['name' => 'Academic Formal', 'category' => 'Completion', 'orientation' => 'portrait', 'html' => $html];
    }

    private function techDarkMode(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 60px 70px; background: #0b0f19; font-family: Helvetica, Arial, sans-serif; color: #e6f1ff; }
                .frame { border: 1px solid #1f2b45; border-radius: 12px; padding: 46px; position: relative; height: calc(100vh - 232px); background: #0e1425; }
                .frame::before { content: ""; position: absolute; top: 0; left: 40px; right: 40px; height: 3px; background: linear-gradient(90deg,#22d3ee,#818cf8,#22d3ee); }
                .eyebrow { font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #22d3ee; }
                h1 { font-size: 38px; margin: 12px 0 4px; color: #fff; }
                .lead { font-size: 13px; color: #7c8aad; margin-bottom: 22px; }
                .recipient { font-size: 30px; font-weight: bold; color: #22d3ee; margin: 0 0 20px; }
                .desc { max-width: 640px; font-size: 14px; color: #b7c3de; line-height: 1.7; margin-bottom: 36px; }
                .footer { position: absolute; bottom: 46px; left: 46px; right: 46px; display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #1f2b45; padding-top: 18px; }
                .col { font-size: 11px; color: #7c8aad; }
                .col strong { display: block; color: #e6f1ff; font-family: 'Courier New', monospace; font-size: 13px; }
                .qr { width: 70px; height: 70px; object-fit: contain; border-radius: 6px; background: #fff; padding: 4px; }
                .sig { width: 120px; height: 44px; object-fit: contain; filter: brightness(0) invert(1); opacity: .85; }
                .code { position: absolute; bottom: 20px; right: 70px; font-family: 'Courier New', monospace; font-size: 10px; color: #4c5c82; }
            </style>
            </head>
            <body>
                <div class="frame">
                    <p class="eyebrow">// {organization_name}</p>
                    <h1>{title}</h1>
                    <p class="lead">This credential certifies that</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="footer">
                        <div class="col"><strong>{date_of_issue}</strong>issued_at</div>
                        <img class="sig" src="{signature}" alt="Signature">
                        <img class="qr" src="{qrcode}" alt="QR Code">
                    </div>
                </div>
                <span class="code">hash: {verification_code}</span>
            </body>
            </html>
            HTML;

        return ['name' => 'Tech Dark Mode', 'category' => 'Completion', 'html' => $html];
    }

    private function achievementMedal(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 50px 70px; background: #fff8ec; font-family: Georgia, serif; color: #3a2a12; }
                .layout { display: flex; align-items: center; gap: 50px; height: calc(100vh - 100px); }
                .medal { flex: 0 0 210px; width: 210px; height: 210px; border-radius: 999px; background: radial-gradient(circle at 35% 30%, #ffe08a, #c8860a 70%); display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 30px rgba(150,100,10,.3); position: relative; }
                .medal::after { content: ""; position: absolute; bottom: -34px; left: 50%; transform: translateX(-50%); border-left: 24px solid transparent; border-right: 24px solid transparent; border-top: 40px solid #7a1f1f; }
                .medal span { font-size: 64px; }
                .main { flex: 1; }
                .eyebrow { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #c8860a; font-weight: bold; }
                h1 { font-size: 38px; margin: 6px 0 20px; }
                .recipient { font-size: 30px; font-weight: bold; color: #7a1f1f; margin: 0 0 16px; }
                .desc { font-size: 14px; line-height: 1.7; color: #5a4a2c; margin-bottom: 30px; max-width: 620px; }
                .footer { display: flex; gap: 46px; align-items: flex-end; }
                .col { font-size: 12px; text-align: center; }
                .sig { width: 130px; height: 46px; object-fit: contain; }
                .qr { width: 74px; height: 74px; object-fit: contain; }
                .rule { border-top: 1px solid #c8860a; margin-top: 4px; padding-top: 4px; }
                .code { margin-top: 24px; font-size: 10px; color: #b39a6c; }
            </style>
            </head>
            <body>
                <div class="layout">
                    <div class="medal"><span>&#127942;</span></div>
                    <div class="main">
                        <p class="eyebrow">{organization_name}</p>
                        <h1>{title}</h1>
                        <p class="recipient">{recipient_name}</p>
                        <p class="desc">{description}</p>
                        <div class="footer">
                            <div class="col">{date_of_issue}<div class="rule">Date</div></div>
                            <div class="col"><img class="sig" src="{signature}" alt="Signature"><div class="rule">Signature</div></div>
                            <div class="col"><img class="qr" src="{qrcode}" alt="QR Code"><div class="rule">Verify</div></div>
                        </div>
                        <p class="code">{verification_code}</p>
                    </div>
                </div>
            </body>
            </html>
            HTML;

        return ['name' => 'Achievement Medal', 'category' => 'Achievement', 'html' => $html];
    }

    private function ecoNature(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 55px 70px; background: #f3f7ee; font-family: Helvetica, Arial, sans-serif; color: #23331f; position: relative; overflow: hidden; }
                .leaf { position: absolute; width: 220px; height: 220px; background: #cfe3b8; border-radius: 0 100% 0 100%; }
                .leaf.tl { top: -80px; left: -80px; }
                .leaf.br { bottom: -80px; right: -80px; background: #a9cf8a; }
                .content { position: relative; z-index: 1; }
                .eyebrow { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #4c7a34; font-weight: bold; }
                h1 { font-size: 38px; margin: 8px 0 24px; color: #23331f; }
                .lead { font-size: 13px; color: #5c6b52; }
                .recipient { font-size: 30px; font-weight: bold; margin: 6px 0 20px; color: #386427; }
                .desc { max-width: 640px; font-size: 14px; line-height: 1.7; color: #45543d; margin-bottom: 40px; }
                .footer { display: flex; justify-content: space-between; align-items: flex-end; border-top: 2px solid #a9cf8a; padding-top: 18px; max-width: 720px; }
                .col { font-size: 11px; color: #5c6b52; text-align: center; }
                .sig { width: 130px; height: 46px; object-fit: contain; }
                .qr { width: 72px; height: 72px; object-fit: contain; background: #fff; padding: 4px; border-radius: 6px; }
                .code { margin-top: 20px; font-size: 10px; color: #8ba57b; }
            </style>
            </head>
            <body>
                <div class="leaf tl"></div>
                <div class="leaf br"></div>
                <div class="content">
                    <p class="eyebrow">{organization_name}</p>
                    <h1>{title}</h1>
                    <p class="lead">Awarded with appreciation to</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="footer">
                        <div class="col">{date_of_issue}<br>Date</div>
                        <div class="col"><img class="sig" src="{signature}" alt="Signature"><br>Signature</div>
                        <div class="col"><img class="qr" src="{qrcode}" alt="QR Code"><br>Verify</div>
                    </div>
                    <p class="code">{verification_code}</p>
                </div>
            </body>
            </html>
            HTML;

        return ['name' => 'Eco Nature', 'category' => 'Recognition', 'html' => $html];
    }

    private function luxuryBlackGold(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 55px; background: #0d0d0d; font-family: Georgia, serif; color: #e9d8a6; }
                .frame { border: 1px solid #b08d3f; padding: 44px; height: calc(100vh - 174px); text-align: center; position: relative; }
                .frame::before, .frame::after { content: ""; position: absolute; left: 20px; right: 20px; height: 1px; background: #b08d3f; }
                .frame::before { top: 12px; }
                .frame::after { bottom: 12px; }
                .eyebrow { font-size: 12px; letter-spacing: 5px; text-transform: uppercase; color: #b08d3f; margin-top: 20px; }
                h1 { font-size: 40px; color: #f5ecd0; margin: 10px 0 24px; }
                .lead { font-size: 14px; color: #cdb679; margin-bottom: 6px; }
                .recipient { font-size: 32px; color: #f5ecd0; font-weight: bold; border-bottom: 1px solid #b08d3f; display: inline-block; padding-bottom: 8px; margin-bottom: 24px; }
                .desc { max-width: 620px; margin: 0 auto 34px; font-size: 14px; line-height: 1.7; color: #cdb679; font-style: italic; }
                .footer { position: absolute; bottom: 44px; left: 60px; right: 60px; display: flex; justify-content: space-between; align-items: flex-end; }
                .col { font-size: 11px; color: #b08d3f; text-align: center; }
                .sig { width: 130px; height: 46px; object-fit: contain; filter: brightness(0) saturate(100%) invert(78%) sepia(28%) saturate(555%) hue-rotate(357deg); }
                .qr { width: 70px; height: 70px; object-fit: contain; background: #f5ecd0; padding: 4px; border-radius: 4px; }
                .code { position: absolute; bottom: 16px; right: 20px; font-size: 9px; color: #6b5a2c; }
            </style>
            </head>
            <body>
                <div class="frame">
                    <p class="eyebrow">{organization_name}</p>
                    <h1>{title}</h1>
                    <p class="lead">This certificate is presented to</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="footer">
                        <div class="col"><img class="sig" src="{signature}" alt="Signature"><div>Signature</div></div>
                        <img class="qr" src="{qrcode}" alt="QR Code">
                        <div class="col"><div style="height:46px;display:flex;align-items:flex-end;justify-content:center;color:#f5ecd0;">{date_of_issue}</div><div>Date</div></div>
                    </div>
                    <span class="code">{verification_code}</span>
                </div>
            </body>
            </html>
            HTML;

        return ['name' => 'Luxury Black & Gold', 'category' => 'Achievement', 'html' => $html];
    }

    private function playfulCreative(): array
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                body { margin: 0; padding: 20px; background: linear-gradient(135deg,#fde68a,#fca5a5,#93c5fd); font-family: Helvetica, Arial, sans-serif; }
                .card { background: #fff; border-radius: 28px; padding: 50px 44px; height: calc(100vh - 100px); text-align: center; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,.15); }
                .stars { font-size: 22px; letter-spacing: 10px; color: #f59e0b; }
                .eyebrow { font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: #ec4899; font-weight: bold; margin-top: 10px; }
                h1 { font-size: 36px; color: #7c3aed; margin: 8px 0 20px; }
                .lead { font-size: 14px; color: #6b7280; }
                .recipient { font-size: 30px; font-weight: bold; color: #ef4444; margin: 8px 0 18px; }
                .desc { max-width: 480px; margin: 0 auto 30px; font-size: 14px; line-height: 1.6; color: #4b5563; }
                .footer { display: flex; justify-content: center; gap: 50px; align-items: flex-end; margin-top: 20px; }
                .col { font-size: 12px; color: #6b7280; text-align: center; }
                .sig { width: 120px; height: 44px; object-fit: contain; }
                .qr { width: 70px; height: 70px; object-fit: contain; border-radius: 10px; }
                .badge { position: absolute; top: 24px; right: 24px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: bold; padding: 6px 12px; border-radius: 999px; }
                .code { margin-top: 20px; font-size: 10px; color: #9ca3af; }
            </style>
            </head>
            <body>
                <div class="card">
                    <span class="badge">{organization_name}</span>
                    <div class="stars">&#9733; &#9733; &#9733;</div>
                    <p class="eyebrow">You did it!</p>
                    <h1>{title}</h1>
                    <p class="lead">This award goes to</p>
                    <p class="recipient">{recipient_name}</p>
                    <p class="desc">{description}</p>
                    <div class="footer">
                        <div class="col">{date_of_issue}<br>Date</div>
                        <div class="col"><img class="sig" src="{signature}" alt="Signature"><br>Signature</div>
                        <div class="col"><img class="qr" src="{qrcode}" alt="QR Code"><br>Scan me</div>
                    </div>
                    <p class="code">{verification_code}</p>
                </div>
            </body>
            </html>
            HTML;

        return ['name' => 'Playful Creative', 'category' => 'Achievement', 'orientation' => 'portrait', 'html' => $html];
    }
}
