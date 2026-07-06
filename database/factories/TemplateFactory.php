<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Certificate',
            'category' => fake()->randomElement(['Achievement', 'Recognition', 'Completion']),
            'html_content' => $this->defaultHtml(),
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'is_active' => true,
            'is_exclusive' => false,
            'created_by' => User::factory()->admin(),
            // Pre-cached so ordinary tests don't trigger real corner
            // detection (a full Browsershot screenshot render) on every
            // single render call - tests that actually exercise detection
            // override this back to null explicitly.
            'watermark_corner' => 'bottom-left',
        ];
    }

    private function defaultHtml(): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <style>
                    body { font-family: 'Helvetica', Arial, sans-serif; margin: 0; padding: 60px; text-align: center; border: 12px solid #b40012; }
                    h1 { font-size: 42px; color: #151c27; margin-bottom: 8px; }
                    .subtitle { font-size: 16px; color: #5c403c; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 32px; }
                    .recipient { font-size: 32px; font-weight: bold; color: #b40012; margin-bottom: 24px; }
                    .description { font-size: 16px; color: #151c27; max-width: 600px; margin: 0 auto 32px; }
                    .footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 60px; gap: 24px; }
                    .qr { width: 100px; }
                    .logo img { max-width: 120px; max-height: 60px; object-fit: contain; }
                    .signature img { max-width: 180px; max-height: 80px; object-fit: contain; }
                </style>
            </head>
            <body>
                <h1>{title}</h1>
                <p class="subtitle">This is to certify that</p>
                <p class="recipient">{recipient_name}</p>
                <p class="description">{description}</p>
                <div class="footer">
                    <div class="qr">{qrcode}</div>
                    <div class="signature">
                        <img src="{signature}" alt="Signature" />
                    </div>
                    <div class="logo">
                        <img src="{company_logo_0}" alt="Company Logo" />
                    </div>
                    <div>
                        <p>Issued by {organization_name}</p>
                        <p>{date_of_issue}</p>
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }
}
