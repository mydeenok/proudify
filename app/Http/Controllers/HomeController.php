<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $request->user()->isAdmin()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('dashboard');
        }

        $hasTables = Schema::hasTable('templates') && Schema::hasTable('certificates');

        $featuredTemplates = $hasTables
            ? Template::active()->latest()->limit(8)->get()
            : collect();

        $stats = [
            'certificates' => $hasTables ? Certificate::count() : 0,
            'organizations' => Schema::hasTable('users')
                ? User::where('role', 'user')->where('status', 'active')->count()
                : 0,
            'verifications' => Schema::hasTable('certificate_verifications')
                ? CertificateVerification::count()
                : 0,
            'templates' => $hasTables ? Template::active()->count() : 0,
        ];

        // Pay-per-certificate is the only billing model now (see
        // CertificatePricingService / CertificateOrder) - the landing page
        // used to show Subscription-tier monthly plans here, which were
        // retired without anyone updating this page, leaving visitors with
        // a pricing section describing a billing model that no longer
        // exists (and no per-certificate rate shown anywhere before
        // checkout).
        $billing = Schema::hasTable('billing_settings') ? BillingSetting::current() : null;

        return view('welcome', compact('featuredTemplates', 'stats', 'billing'));
    }
}
