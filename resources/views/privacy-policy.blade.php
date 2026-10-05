<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - {{ config('app.name', 'Utility Accounts System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased selection:bg-slate-900 selection:text-white">
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur-xs sticky top-0 z-30">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-slate-900 hover:text-slate-700 transition-colors">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-900 text-white font-bold text-sm">
                    U
                </span>
                <span>{{ config('app.name', 'Utility Accounts System') }}</span>
            </a>

            <div class="flex items-center gap-4 text-sm">
                <form method="POST" action="{{ route('locale.switch') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'bn' ? 'en' : 'bn' }}">
                    <button type="submit" class="text-slate-600 hover:text-slate-900 font-medium cursor-pointer">
                        {{ app()->getLocale() === 'bn' ? 'English' : 'বাংলা' }}
                    </button>
                </form>
                <a href="{{ route('login') }}" class="inline-flex items-center rounded-md bg-slate-900 px-3.5 py-1.5 text-xs font-medium text-white shadow-xs hover:bg-slate-800 transition-colors">
                    {{ __('auth.sign_in') }}
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-6 sm:p-10">
            <div class="border-b border-slate-200 pb-6 mb-8">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Google Play Store & Web Compliance
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Privacy Policy</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Last Updated: {{ date('F d, Y') }} &bull; Effective Date: October 01, 2024
                </p>
            </div>

            <div class="prose prose-slate max-w-none space-y-8 text-slate-600 leading-relaxed">
                <!-- Overview -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">1. Overview</h2>
                    <p>
                        This Privacy Policy applies to the <strong>{{ config('app.name', 'Utility Accounts System') }}</strong> web portal and mobile application (collectively, the &ldquo;Service&rdquo;). We are committed to protecting your privacy and ensuring transparency regarding how your personal information is collected, stored, used, and safeguarded.
                    </p>
                    <p class="mt-2">
                        By accessing or using our Service, you agree to the collection and use of information in accordance with this policy.
                    </p>
                </section>

                <!-- Information We Collect -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">2. Information We Collect</h2>
                    <p>To provide apartment/building management, utility meter billing, payment processing, and community communication services, we may collect the following categories of information:</p>
                    <ul class="list-disc pl-5 mt-2 space-y-1.5">
                        <li><strong>Personal Identification Information:</strong> Full name, email address, phone number, and building/flat or unit numbers.</li>
                        <li><strong>Account Credentials:</strong> Securely hashed passwords and authentication tokens used to secure your account.</li>
                        <li><strong>Billing & Financial Records:</strong> Service charge invoices, utility tariffs and meter readings (electricity, gas, water), payment transaction records, bank or mobile wallet payment references, and uploaded payment receipts.</li>
                        <li><strong>Device & Push Notification Data:</strong> Unique device identifiers, operating system version, and Firebase Cloud Messaging (FCM) device registration tokens required to deliver push notifications regarding monthly bills, payment confirmations, and maintenance announcements.</li>
                        <li><strong>Maintenance & Community Inquiries:</strong> Maintenance issue descriptions, photos or attachments submitted with service requests, and notices posted for building residents.</li>
                        <li><strong>System Logs & Analytics:</strong> IP addresses, browser types, access timestamps, and error diagnostics for security auditing and service reliability.</li>
                    </ul>
                </section>

                <!-- How We Use Information -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">3. How We Use Your Information</h2>
                    <p>We use the collected information strictly for legitimate operational purposes, including:</p>
                    <ul class="list-disc pl-5 mt-2 space-y-1.5">
                        <li>Generating and distributing monthly service charge and utility bills.</li>
                        <li>Processing payment submissions, recording payments in the accounting ledger, and issuing downloadable receipts.</li>
                        <li>Sending real-time push notifications, payment reminders, and emergency community notices to registered devices.</li>
                        <li>Managing maintenance requests and communicating resolution progress.</li>
                        <li>Maintaining financial transparency and audit logs for building committee members, accountants, flat owners, and tenants.</li>
                        <li>Protecting against fraud, unauthorized access, and system misuse.</li>
                    </ul>
                </section>

                <!-- Third-Party Services -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">4. Third-Party Service Providers</h2>
                    <p>
                        We do not sell, rent, or trade your personal data to third parties or advertising networks. We only share data with trusted third-party service providers essential for operating the application:
                    </p>
                    <ul class="list-disc pl-5 mt-2 space-y-1.5">
                        <li>
                            <strong>Google Firebase Cloud Messaging (FCM):</strong> Used to deliver push notifications to your mobile devices. 
                            You can review Google&rsquo;s Privacy Policy at 
                            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-slate-900 underline font-medium hover:text-indigo-600">https://policies.google.com/privacy</a>.
                        </li>
                    </ul>
                </section>

                <!-- Data Security -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">5. Data Security & Storage</h2>
                    <p>
                        We employ industry-standard administrative, technical, and physical security measures to safeguard your personal data:
                    </p>
                    <ul class="list-disc pl-5 mt-2 space-y-1.5">
                        <li>All data transmissions are encrypted in transit using Transport Layer Security (TLS/HTTPS).</li>
                        <li>Passwords are hashed using modern cryptographic hashing algorithms (Bcrypt/Argon2).</li>
                        <li>Role-based access control (RBAC) ensures users only access records for which they are authorized.</li>
                        <li>Automated, encrypted database backups are maintained to prevent catastrophic data loss.</li>
                    </ul>
                </section>

                <!-- Data Retention & Deletion -->
                <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-5">
                    <h2 class="text-xl font-bold text-slate-900 mb-3">6. User Rights & Data Deletion (Google Play Compliance)</h2>
                    <p>
                        You have the right to access, update, rectify, or request the deletion of your personal account and associated data at any time.
                    </p>
                    <p class="mt-2 font-medium text-slate-900">
                        How to request account and data deletion:
                    </p>
                    <p class="mt-1">
                        To request the complete deletion of your account, login credentials, and personal information, please email our support team directly at:
                    </p>
                    <div class="mt-3 inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 border border-slate-200 shadow-2xs">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <a href="mailto:frabbi727@gmail.com?subject=Account%20and%20Data%20Deletion%20Request" class="font-mono text-sm font-semibold text-slate-900 hover:text-indigo-600">
                            frabbi727@gmail.com
                        </a>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Please send your request from the registered email address with the subject &ldquo;Account and Data Deletion Request&rdquo;. We will verify and process your request within 7 business days. Note that certain billing and transaction ledger entries may be retained as required by applicable tax, accounting, or legal auditing obligations.
                    </p>
                </section>

                <!-- Children's Privacy -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">7. Children&rsquo;s Privacy</h2>
                    <p>
                        Our Service is intended for property owners, residents, tenants, and building managers. We do not knowingly collect or solicit personal identifiable information from children under the age of 13. If we discover that a child under 13 has provided us with personal information, we will immediately delete such data.
                    </p>
                </section>

                <!-- Changes to This Policy -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">8. Changes to This Privacy Policy</h2>
                    <p>
                        We may update this Privacy Policy from time to time to reflect changes in legal requirements or application enhancements. Any modifications will become effective immediately upon posting the updated policy on this page with a revised &ldquo;Last Updated&rdquo; date.
                    </p>
                </section>

                <!-- Contact Information -->
                <section>
                    <h2 class="text-xl font-bold text-slate-900 mb-3">9. Contact Us</h2>
                    <p>
                        If you have any questions, concerns, or inquiries regarding this Privacy Policy or your personal information, please reach out to us at:
                    </p>
                    <div class="mt-3 rounded-lg border border-slate-200 bg-white p-4">
                        <p class="font-semibold text-slate-900">{{ config('app.name', 'Utility Accounts System') }}</p>
                        <p class="text-sm mt-1">Contact Email: <a href="mailto:frabbi727@gmail.com" class="text-slate-900 underline font-medium hover:text-indigo-600">frabbi727@gmail.com</a></p>
                    </div>
                </section>
            </div>
        </div>

        <footer class="mt-8 text-center text-xs text-slate-500">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Utility Accounts System') }}. All rights reserved.</p>
        </footer>
    </main>
</body>
</html>
