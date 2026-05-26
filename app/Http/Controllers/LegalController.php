<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LegalController extends Controller
{
    private function render(string $title, string $content): \Illuminate\View\View
    {
        return view('legal', [
            'title'   => $title,
            'updated' => 'May 2026',
            'content' => $content,
        ]);
    }

    // ── Privacy Policy ─────────────────────────────────────────────────────────

    public function privacy()
    {
        $content = '
        <h2>1. Information We Collect</h2>
        <p>When you use KingLive, we collect the following information:</p>
        <ul>
            <li>Account information: name, email address, phone number, username</li>
            <li>Profile information: profile photo, bio, and other content you provide</li>
            <li>Usage data: streams you watch, gifts you send, interactions within the app</li>
            <li>Device information: device type, operating system, unique device identifiers</li>
            <li>Location data: general location based on IP address</li>
        </ul>

        <h2>2. How We Use Your Information</h2>
        <ul>
            <li>To provide and improve our live streaming services</li>
            <li>To process transactions including coin purchases and diamond withdrawals</li>
            <li>To personalize your experience and show relevant content</li>
            <li>To communicate with you about updates, promotions, and support</li>
            <li>To ensure safety and prevent fraud or abuse</li>
        </ul>

        <h2>3. Information Sharing</h2>
        <p>We do not sell your personal information. We may share information with:</p>
        <ul>
            <li>Service providers who help us operate the platform (payment processors, cloud hosting)</li>
            <li>Law enforcement when required by law or to protect user safety</li>
            <li>Other users — your username, profile photo, and public content are visible to other users</li>
        </ul>

        <h2>4. Data Security</h2>
        <p>We use industry-standard encryption and security measures to protect your data. However, no method of transmission over the internet is 100% secure.</p>

        <h2>5. Children\'s Privacy</h2>
        <p>KingLive is not intended for users under 13 years of age. We do not knowingly collect personal information from children under 13.</p>

        <h2>6. Your Rights</h2>
        <ul>
            <li>Access, update, or delete your account information at any time</li>
            <li>Request deletion of your account and associated data</li>
            <li>Opt out of promotional communications</li>
        </ul>

        <h2>7. Cookies</h2>
        <p>We use cookies and similar tracking technologies to improve your experience. You can control cookies through your browser settings.</p>

        <h2>8. Changes to This Policy</h2>
        <p>We may update this privacy policy from time to time. We will notify you of significant changes via the app or email.</p>

        <h2>9. Contact Us</h2>
        <p>If you have questions about this privacy policy, please contact us at: <a href="mailto:support@kinglive.app">support@kinglive.app</a></p>
        ';

        return $this->render('Privacy Policy', $content);
    }

    // ── Terms & Conditions ─────────────────────────────────────────────────────

    public function terms()
    {
        $content = '
        <h2>1. Acceptance of Terms</h2>
        <p>By downloading, installing, or using KingLive, you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the app.</p>

        <h2>2. Eligibility</h2>
        <p>You must be at least 13 years old to use KingLive. By using the app, you confirm that you meet this requirement.</p>

        <h2>3. User Accounts</h2>
        <ul>
            <li>You are responsible for maintaining the confidentiality of your account credentials</li>
            <li>You are responsible for all activity that occurs under your account</li>
            <li>You must provide accurate and complete information when creating your account</li>
            <li>One account per person — creating multiple accounts is not permitted</li>
        </ul>

        <h2>4. Virtual Currency</h2>
        <ul>
            <li>Coins are purchased with real money and used to send gifts within the app</li>
            <li>Diamonds are earned by hosts through gifts received and can be withdrawn</li>
            <li>Virtual currency has no cash value outside of the KingLive platform</li>
            <li>All purchases are final and non-refundable unless required by law</li>
            <li>We reserve the right to modify exchange rates and withdrawal policies</li>
        </ul>

        <h2>5. Prohibited Conduct</h2>
        <p>You agree not to:</p>
        <ul>
            <li>Stream or share illegal, harmful, or offensive content</li>
            <li>Harass, bully, or threaten other users</li>
            <li>Use the platform for commercial solicitation without authorization</li>
            <li>Attempt to hack, reverse engineer, or disrupt the platform</li>
            <li>Create fake accounts or impersonate others</li>
            <li>Stream nudity, sexual content, or graphic violence</li>
        </ul>

        <h2>6. Content Ownership</h2>
        <p>You retain ownership of content you create. By posting content, you grant KingLive a worldwide, non-exclusive license to use, display, and distribute your content on the platform.</p>

        <h2>7. Termination</h2>
        <p>We reserve the right to suspend or terminate your account at any time for violations of these terms, without prior notice.</p>

        <h2>8. Limitation of Liability</h2>
        <p>KingLive is provided "as is" without warranties. We are not liable for any indirect, incidental, or consequential damages arising from your use of the platform.</p>

        <h2>9. Changes to Terms</h2>
        <p>We may update these terms at any time. Continued use of KingLive after changes constitutes acceptance of the new terms.</p>

        <h2>10. Contact</h2>
        <p>For questions about these terms: <a href="mailto:support@kinglive.app">support@kinglive.app</a></p>
        ';

        return $this->render('Terms & Conditions', $content);
    }

    // ── Delete Account Page ────────────────────────────────────────────────────

    public function deleteAccount()
    {
        return view('delete_account');
    }

    public function deleteAccountSubmit(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'reason'   => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->withInput();
        }

        // Soft delete — mark for deletion, actual deletion after 30 days
        // This gives users a chance to recover their account
        $user->update([
            'deletion_requested_at' => now(),
            'deletion_reason'       => $request->reason,
        ]);

        // Hard delete immediately
        // Uncomment below if you want instant deletion instead:
        // $user->delete();

        return redirect()->route('delete.account.success');
    }

    public function deleteAccountSuccess()
    {
        return view('delete_account_success');
    }
}
