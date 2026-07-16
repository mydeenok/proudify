<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use App\Services\GeoLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, GeoLocationService $geo): View|RedirectResponse
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

        $pricingPlans = Schema::hasTable('subscriptions')
            ? Subscription::active()->orderByDesc('is_default_free_plan')->orderBy('sort_order')->limit(3)->get()
            : collect();

        $currency = $geo->currencyFor($request->ip());

        return view('welcome', compact('featuredTemplates', 'stats', 'pricingPlans', 'currency'));
    }
}
