<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Local-only: lets every email template be opened directly in a browser
 * tab with realistic fake data, instead of needing a real trigger (sign up,
 * pay, let a batch finish) just to see what an email looks like. Routes
 * for this controller are only registered when app()->environment('local')
 * - see routes/web.php - so this never exists outside local dev.
 */
class MailPreviewController extends Controller
{
    /**
     * @return array<string, array{label: string, view: string}>
     */
    private function catalog(): array
    {
        return [
            'otp-code' => ['label' => 'OTP verification code', 'view' => 'emails.otp-code'],
            'account-approved' => ['label' => 'Account approved', 'view' => 'emails.account-approved'],
            'account-rejected' => ['label' => 'Account rejected', 'view' => 'emails.account-rejected'],
            'certificate-issued' => ['label' => 'Certificate issued', 'view' => 'emails.certificate-issued'],
            'bulk-upload-completed' => ['label' => 'Bulk upload completed', 'view' => 'emails.bulk-upload-completed'],
            'payment-successful' => ['label' => 'Payment successful', 'view' => 'emails.payment-successful'],
            'payment-failed' => ['label' => 'Payment failed', 'view' => 'emails.payment-failed'],
            'subscription-expiring' => ['label' => 'Subscription expiring soon', 'view' => 'emails.subscription-expiring'],
            'subscription-expired' => ['label' => 'Subscription expired', 'view' => 'emails.subscription-expired'],
            'quota-almost-full' => ['label' => 'Quota almost full', 'view' => 'emails.quota-almost-full'],
            'subscription-cancelled' => ['label' => 'Subscription cancelled', 'view' => 'emails.subscription-cancelled'],
            'admin-new-registration' => ['label' => '[Admin] New registration awaiting approval', 'view' => 'emails.admin-new-registration'],
            'admin-bulk-upload-requested' => ['label' => '[Admin] Bulk certificate request submitted', 'view' => 'emails.admin-bulk-upload-requested'],
            'admin-new-purchase' => ['label' => '[Admin] New subscription purchase', 'view' => 'emails.admin-new-purchase'],
        ];
    }

    public function index(): View
    {
        return view('dev.mail-preview-index', ['emails' => $this->catalog()]);
    }

    public function show(string $type): Response
    {
        abort_unless(array_key_exists($type, $this->catalog()), 404);

        $user = new User(['first_name' => 'Jordan', 'last_name' => 'Lee', 'email' => 'jordan@example.com']);

        $data = match ($type) {
            'otp-code' => ['user' => $user, 'otpCode' => '482913'],
            'account-approved', 'account-rejected' => ['user' => $user],
            'certificate-issued' => ['certificate' => $this->fakeCertificate($user)],
            'bulk-upload-completed' => ['notifiable' => $user, 'batch' => $this->fakeBatch()],
            'subscription-expiring' => ['userSubscription' => $this->fakeUserSubscription($user), 'daysRemaining' => 3],
            'quota-almost-full' => ['userSubscription' => $this->fakeUserSubscription($user, certificatesUsed: 92), 'percentUsed' => 92],
            'admin-new-registration' => ['registrant' => $user->fill(['organization_name' => 'Acme University'])],
            'admin-bulk-upload-requested' => ['batch' => $this->fakeBatch()],
            'admin-new-purchase' => ['userSubscription' => $this->fakeUserSubscription($user->fill(['organization_name' => 'Acme University']))],
            default => ['userSubscription' => $this->fakeUserSubscription($user)],
        };

        return response(view($this->catalog()[$type]['view'], $data));
    }

    private function fakeCertificate(User $user): Certificate
    {
        $certificate = new Certificate([
            'title' => 'Certificate of Excellence',
            'recipient_name' => 'Jordan Lee',
            'uuid' => 'preview-uuid',
            'verification_code' => 'PREVIEW1',
            'date_of_issue' => now(),
        ]);
        $certificate->setRelation('user', $user->fill(['organization_name' => 'Acme University']));

        return $certificate;
    }

    private function fakeBatch(): CertificateBatch
    {
        $batch = CertificateBatch::with(['user', 'template'])->latest()->first();

        if ($batch && $batch->user && $batch->template) {
            return $batch;
        }

        $batch = new CertificateBatch(['id' => 1, 'total_rows' => 20, 'succeeded_rows' => 18, 'failed_rows' => 2]);
        $batch->setRelation('user', new User(['organization_name' => 'Acme University']));
        $batch->setRelation('template', new Template(['name' => 'Certificate of Achievement']));

        return $batch;
    }

    private function fakeUserSubscription(User $user, int $certificatesUsed = 10): UserSubscription
    {
        $subscription = Subscription::query()->first() ?? new Subscription(['name' => 'Professional']);

        $userSubscription = new UserSubscription([
            'certificates_limit' => 100,
            'certificates_used' => $certificatesUsed,
            'users_limit' => 50,
            'users_used' => 10,
            'billing_period' => 'monthly',
            'amount_paid' => 1999,
            'currency' => 'INR',
            'start_date' => now(),
            'end_date' => now()->addDays(3),
        ]);
        $userSubscription->setRelation('user', $user);
        $userSubscription->setRelation('subscription', $subscription);

        return $userSubscription;
    }
}
