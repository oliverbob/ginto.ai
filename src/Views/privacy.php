<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Ginto</title>
    <meta name="description" content="Ginto Privacy Policy — how we collect, use, and protect your personal information.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gray: { 950: '#0a0a0f' }
                    }
                }
            }
        }
        if (localStorage.theme === 'light') {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        .toc-link { transition: color 0.15s; }
        .toc-link:hover { color: #14b8a6; }
        .prose h2 { scroll-margin-top: 80px; }
        .prose h3 { scroll-margin-top: 80px; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen">

    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <img src="/assets/images/ginto.png" alt="Ginto" class="h-8 w-8 rounded-lg">
                <span class="font-bold text-xl text-gray-900 dark:text-white">Ginto</span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <a href="/chat" class="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">Chat</a>
                <a href="/upgrade" class="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">Plans</a>
                <a href="/login" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors text-sm">Log In</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <div class="bg-gradient-to-br from-teal-600 to-teal-800 dark:from-teal-800 dark:to-gray-900 py-16">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-white/20 rounded-2xl mb-6">
                <i class="fas fa-shield-halved text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-3">Privacy Policy</h1>
            <p class="text-teal-100 text-lg">Last updated: <strong>March 18, 2026</strong></p>
            <p class="text-teal-200 mt-2 text-sm max-w-xl mx-auto">
                This policy explains how Ginto collects, uses, and protects your personal information in compliance with the Philippine Data Privacy Act of 2012 (Republic Act No. 10173) and applicable international privacy laws.
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex flex-col lg:flex-row gap-10">

            <!-- Table of Contents (sticky sidebar) -->
            <aside class="lg:w-64 shrink-0">
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 lg:sticky lg:top-24">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-4">Contents</p>
                    <nav class="space-y-2 text-sm">
                        <a href="#overview"          class="toc-link block text-gray-700 dark:text-gray-300">1. Overview</a>
                        <a href="#who-we-are"        class="toc-link block text-gray-700 dark:text-gray-300">2. Who We Are</a>
                        <a href="#data-collected"    class="toc-link block text-gray-700 dark:text-gray-300">3. Data We Collect</a>
                        <a href="#how-we-use"        class="toc-link block text-gray-700 dark:text-gray-300">4. How We Use Your Data</a>
                        <a href="#legal-basis"       class="toc-link block text-gray-700 dark:text-gray-300">5. Legal Basis</a>
                        <a href="#sharing"           class="toc-link block text-gray-700 dark:text-gray-300">6. Data Sharing</a>
                        <a href="#retention"         class="toc-link block text-gray-700 dark:text-gray-300">7. Data Retention</a>
                        <a href="#security"          class="toc-link block text-gray-700 dark:text-gray-300">8. Security</a>
                        <a href="#your-rights"       class="toc-link block text-gray-700 dark:text-gray-300">9. Your Rights</a>
                        <a href="#cookies"           class="toc-link block text-gray-700 dark:text-gray-300">10. Cookies</a>
                        <a href="#minors"            class="toc-link block text-gray-700 dark:text-gray-300">11. Minors</a>
                        <a href="#cross-border"      class="toc-link block text-gray-700 dark:text-gray-300">12. Cross-Border Transfers</a>
                        <a href="#third-party"       class="toc-link block text-gray-700 dark:text-gray-300">13. Third-Party Services</a>
                        <a href="#ai-content"        class="toc-link block text-gray-700 dark:text-gray-300">14. AI &amp; Content</a>
                        <a href="#changes"           class="toc-link block text-gray-700 dark:text-gray-300">15. Policy Changes</a>
                        <a href="#contact"           class="toc-link block text-gray-700 dark:text-gray-300">16. Contact Us</a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-8 prose prose-gray dark:prose-invert max-w-none space-y-10 text-gray-700 dark:text-gray-300 leading-relaxed">

                    <!-- 1. Overview -->
                    <section id="overview">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">1</span>
                            Overview
                        </h2>
                        <p>
                            Ginto ("<strong>Ginto</strong>," "<strong>we</strong>," "<strong>our</strong>," or "<strong>us</strong>") is an AI-powered platform providing conversational intelligence, code generation, sandbox environments, multimedia tools, and related services (collectively, the "<strong>Services</strong>"). This Privacy Policy describes how we collect, use, disclose, and safeguard personal information when you access or use the Services through our website (<a href="https://silverqueen.pro" class="text-teal-600 dark:text-teal-400 hover:underline">https://silverqueen.pro</a>), mobile applications, or any other interface we provide.
                        </p>
                        <p class="mt-3">
                            By using the Services, you acknowledge that you have read and understood this Privacy Policy. If you do not agree, please discontinue use of the Services.
                        </p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 2. Who We Are -->
                    <section id="who-we-are">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">2</span>
                            Who We Are
                        </h2>
                        <p>
                            Ginto is operated and maintained as a technology platform accessible globally, with primary operations serving users in the Philippines and beyond. For the purposes of the Philippine Data Privacy Act of 2012 (<strong>RA 10173</strong>) and its Implementing Rules and Regulations, Ginto acts as the <strong>Personal Information Controller (PIC)</strong> for personal data collected through the Services.
                        </p>
                        <p class="mt-3">
                            For inquiries regarding this policy or your personal data, please contact our Data Protection Officer (DPO) at the address provided in <a href="#contact" class="text-teal-600 dark:text-teal-400 hover:underline">Section 16</a>.
                        </p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 3. Data We Collect -->
                    <section id="data-collected">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">3</span>
                            Data We Collect
                        </h2>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.1 Information You Provide</h3>
                        <ul class="list-disc list-inside space-y-1 ml-2">
                            <li><strong>Account registration:</strong> Name, email address, username, and password (stored as a one-way hash).</li>
                            <li><strong>Profile information:</strong> Optional profile photo, bio, country, and contact details you voluntarily add.</li>
                            <li><strong>Payment information:</strong> Billing name, country, and transaction identifiers. Full card numbers are processed by our third-party payment processor and are never stored on our servers.</li>
                            <li><strong>Communications:</strong> Messages, feedback, or support requests you send us.</li>
                            <li><strong>User-generated content:</strong> Prompts, code, files, and other content you create or upload while using the Services.</li>
                            <li><strong>Referral data:</strong> Referral codes used during registration to attribute network relationships.</li>
                            <li><strong>GPS / Location data:</strong> When using the ePower Mall delivery features, we collect your precise GPS coordinates to auto-detect your barangay, verify delivery proof locations, and optimize the delivery experience. Location access is requested with your permission and is used solely for delivery and commerce purposes.</li>
                            <li><strong>Delivery proof photographs:</strong> Buyers and sellers may upload photographs as proof of delivery, including product arrival photos, selfies with the customer, and condition documentation. These images may include embedded GPS metadata (EXIF data) and are stored securely on our servers or third-party storage providers.</li>
                        </ul>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.2 Information Collected Automatically</h3>
                        <ul class="list-disc list-inside space-y-1 ml-2">
                            <li><strong>Device &amp; browser data:</strong> IP address, browser type and version, operating system, device type, and screen resolution.</li>
                            <li><strong>Usage data:</strong> Pages visited, features used, session duration, click paths, and interaction logs.</li>
                            <li><strong>Log data:</strong> Server access logs including timestamps, requested URLs, HTTP status codes, and referring URLs.</li>
                            <li><strong>Session identifiers:</strong> Secure cookies and session tokens used to maintain your authenticated state.</li>
                            <li><strong>Location data (automatic):</strong> When you grant location permissions on the Ginto mobile app, we may collect your device's GPS coordinates in the background to support location-based barangay detection and delivery features. You may revoke this permission at any time through your device settings.</li>
                        </ul>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.3 Information from Third Parties</h3>
                        <ul class="list-disc list-inside space-y-1 ml-2">
                            <li><strong>OAuth providers:</strong> If you log in via a third-party provider (e.g., Google), we receive basic profile information permitted by your authorization.</li>
                            <li><strong>Payment processors:</strong> Confirmation of successful or failed transactions and associated reference numbers.</li>
                        </ul>

                        <div class="mt-4 p-4 bg-teal-50 dark:bg-teal-900/20 rounded-xl border border-teal-200 dark:border-teal-800 text-sm">
                            <i class="fas fa-circle-info text-teal-600 dark:text-teal-400 mr-2"></i>
                            We do <strong>not</strong> collect sensitive personal information such as racial or ethnic origin, political opinions, religious beliefs, health data, or biometrics, except where you explicitly provide such data as part of your generated content.
                        </div>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 4. How We Use Your Data -->
                    <section id="how-we-use">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">4</span>
                            How We Use Your Data
                        </h2>
                        <p>We use your personal information for the following purposes:</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 dark:bg-gray-800">
                                        <th class="text-left px-4 py-3 rounded-tl-lg font-semibold text-gray-700 dark:text-gray-300">Purpose</th>
                                        <th class="text-left px-4 py-3 rounded-tr-lg font-semibold text-gray-700 dark:text-gray-300">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Service delivery</td>
                                        <td class="px-4 py-3">Operate features, process AI queries, manage sandbox sessions, and fulfil paid subscriptions.</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Account management</td>
                                        <td class="px-4 py-3">Create and maintain your account, authenticate your identity, and manage preferences and settings.</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Billing &amp; payments</td>
                                        <td class="px-4 py-3">Process subscription payments, issue receipts, and handle refund or dispute requests.</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Communication</td>
                                        <td class="px-4 py-3">Send transactional emails (account confirmations, receipts, security alerts) and, with your consent, service announcements.</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Security &amp; fraud prevention</td>
                                        <td class="px-4 py-3">Monitor for abuse, detect unauthorized access, enforce rate limits, and investigate security incidents.</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Service improvement</td>
                                        <td class="px-4 py-3">Analyze aggregated and de-identified usage data to improve the platform's performance and features.</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap">Legal compliance</td>
                                        <td class="px-4 py-3">Fulfill obligations under applicable laws, respond to lawful government requests, and enforce our Terms of Service.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 5. Legal Basis -->
                    <section id="legal-basis">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">5</span>
                            Legal Basis for Processing
                        </h2>
                        <p>
                            We process personal information only when a valid legal basis exists under applicable law. Our processing activities rely on the following bases:
                        </p>
                        <ul class="mt-3 space-y-3">
                            <li class="flex gap-3">
                                <span class="mt-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-400 shrink-0"><i class="fas fa-check text-xs"></i></span>
                                <div><strong class="text-gray-800 dark:text-gray-200">Consent (RA 10173 §13(a); GDPR Art. 6(1)(a)):</strong> Where required by law, we obtain your explicit consent prior to processing — for example, for marketing communications or optional features.</div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-400 shrink-0"><i class="fas fa-check text-xs"></i></span>
                                <div><strong class="text-gray-800 dark:text-gray-200">Contractual necessity (RA 10173 §13(b); GDPR Art. 6(1)(b)):</strong> Processing is necessary to provide the Services you have subscribed to, including account creation, AI query execution, and billing.</div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-400 shrink-0"><i class="fas fa-check text-xs"></i></span>
                                <div><strong class="text-gray-800 dark:text-gray-200">Legitimate interests (RA 10173 §13(f); GDPR Art. 6(1)(f)):</strong> We process data to maintain platform security, prevent fraud, and improve service quality, having balanced these interests against your rights.</div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-400 shrink-0"><i class="fas fa-check text-xs"></i></span>
                                <div><strong class="text-gray-800 dark:text-gray-200">Legal obligation (RA 10173 §13(e); GDPR Art. 6(1)(c)):</strong> We retain and disclose data as required by Philippine law, including the Cybercrime Prevention Act of 2012 (RA 10175), the Electronic Commerce Act (RA 8792), and applicable tax regulations.</div>
                            </li>
                        </ul>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 6. Data Sharing -->
                    <section id="sharing">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">6</span>
                            Data Sharing &amp; Disclosure
                        </h2>
                        <p>We do <strong>not</strong> sell, rent, or trade your personal information. We share data only in the limited circumstances described below:</p>
                        <div class="mt-4 space-y-4">
                            <div class="p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 mb-1"><i class="fas fa-building text-teal-500 mr-2"></i>Service Providers</p>
                                <p class="text-sm">We engage vetted third-party processors (e.g., cloud infrastructure, payment gateways, AI model APIs) that process data on our behalf under data processing agreements that impose equivalent privacy obligations. These providers are prohibited from using your data for any purpose other than providing the contracted services.</p>
                            </div>
                            <div class="p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 mb-1"><i class="fas fa-gavel text-teal-500 mr-2"></i>Legal Requirements</p>
                                <p class="text-sm">We may disclose personal data when legally compelled to do so by Philippine government authorities, courts, or law enforcement agencies, or when we have a good-faith belief that disclosure is necessary to prevent imminent harm, fraud, or illegal activity.</p>
                            </div>
                            <div class="p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 mb-1"><i class="fas fa-right-left text-teal-500 mr-2"></i>Business Transfers</p>
                                <p class="text-sm">If Ginto undergoes a merger, acquisition, or sale of substantially all of its assets, your personal information may be transferred to the successor entity. We will notify you via email or a prominent notice on the platform prior to such a transfer and your data will remain subject to this Privacy Policy.</p>
                            </div>
                            <div class="p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 mb-1"><i class="fas fa-user-shield text-teal-500 mr-2"></i>With Your Consent</p>
                                <p class="text-sm">We may share your data with third parties when you have explicitly authorized such disclosure, for example when you connect an integration or share content publicly.</p>
                            </div>
                        </div>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 7. Data Retention -->
                    <section id="retention">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">7</span>
                            Data Retention
                        </h2>
                        <p>
                            We retain personal information for as long as necessary to provide the Services and fulfil the purposes described in this policy, or as required by applicable law. Our general retention schedule is:
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs"></i>
                                <span><strong>Active account data:</strong> Retained for the duration of your account. You may request deletion at any time (see Section 9).</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs"></i>
                                <span><strong>Deleted account data:</strong> Purged within <strong>30 days</strong> of account deletion, except where retention is required by law.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs"></i>
                                <span><strong>Transaction records:</strong> Retained for a minimum of <strong>5 years</strong> to comply with Philippine BIR and anti-money laundering regulations.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs"></i>
                                <span><strong>Server access logs:</strong> Retained for up to <strong>90 days</strong>, then permanently deleted.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs"></i>
                                <span><strong>Backup data:</strong> Purged within <strong>30 days</strong> of the scheduled backup rotation cycle.</span>
                            </li>
                        </ul>
                        <p class="mt-3">After the applicable retention period, data is securely deleted or anonymized so that it can no longer be linked to an identifiable individual.</p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 8. Security -->
                    <section id="security">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">8</span>
                            Security
                        </h2>
                        <p>
                            We implement industry-standard technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction, including:
                        </p>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <i class="fas fa-lock text-teal-500 mt-0.5"></i>
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">Encryption in Transit</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">All data transmitted between your device and our servers is protected by TLS 1.2 or higher.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <i class="fas fa-key text-teal-500 mt-0.5"></i>
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">Password Hashing</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Passwords are stored using strong one-way cryptographic hashing algorithms (bcrypt/Argon2).</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <i class="fas fa-user-lock text-teal-500 mt-0.5"></i>
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">Access Controls</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Access to production systems and personal data is restricted to authorized personnel on a need-to-know basis.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <i class="fas fa-shield-virus text-teal-500 mt-0.5"></i>
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">Sandboxed Environments</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">User-generated code runs in isolated sandbox environments with strict resource and capability restrictions.</p>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm">
                            Despite our efforts, no security measure is infallible. In the event of a personal data breach that is likely to harm your rights, we will notify affected users and the National Privacy Commission (NPC) of the Philippines within <strong>72 hours</strong> of becoming aware of the breach, as required by NPC Circular 16-03.
                        </p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 9. Your Rights -->
                    <section id="your-rights">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">9</span>
                            Your Rights
                        </h2>
                        <p>
                            Under the Philippine Data Privacy Act of 2012 and, where applicable, the EU General Data Protection Regulation (GDPR) and other international frameworks, you have the following rights with respect to your personal information:
                        </p>
                        <div class="mt-4 space-y-3">
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-eye text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Access (RA 10173 §16(c))</p>
                                    <p class="text-sm mt-0.5">Request a copy of the personal information we hold about you, including the purposes for which it is processed.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-pen-to-square text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Rectification (RA 10173 §16(d))</p>
                                    <p class="text-sm mt-0.5">Request correction of inaccurate or incomplete personal information. You may update most profile data directly from your account settings.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-trash-can text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Erasure / Right to be Forgotten (RA 10173 §16(f))</p>
                                    <p class="text-sm mt-0.5">Request deletion of your personal information. We will comply unless retention is required by law (e.g., financial records) or necessary to resolve disputes.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-ban text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Object (RA 10173 §16(e))</p>
                                    <p class="text-sm mt-0.5">Object to the processing of your personal data on grounds relating to your particular situation, including processing for direct marketing purposes.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-file-export text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Data Portability (RA 10173 §18(b); GDPR Art. 20)</p>
                                    <p class="text-sm mt-0.5">Request a copy of your personal data in a structured, commonly used, machine-readable format, where technically feasible.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-circle-xmark text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Withdraw Consent</p>
                                    <p class="text-sm mt-0.5">Withdraw consent at any time where processing is based on consent, without affecting the lawfulness of processing prior to withdrawal.</p>
                                </div>
                            </div>
                            <div class="flex gap-3 p-4 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <i class="fas fa-flag text-teal-500 mt-0.5 w-5 text-center shrink-0"></i>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Right to Lodge a Complaint</p>
                                    <p class="text-sm mt-0.5">File a complaint with the <strong>National Privacy Commission (NPC)</strong> of the Philippines if you believe your rights under RA 10173 have been violated. Visit <a href="https://www.privacy.gov.ph" class="text-teal-600 dark:text-teal-400 hover:underline" target="_blank" rel="noopener noreferrer">privacy.gov.ph</a> for more information.</p>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm">
                            To exercise any of the above rights, please contact us at <a href="mailto:privacy@silverqueen.pro" class="text-teal-600 dark:text-teal-400 hover:underline">privacy@silverqueen.pro</a>. We will respond within <strong>15 business days</strong> of receiving a verified request, consistent with NPC guidelines.
                        </p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 10. Cookies -->
                    <section id="cookies">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">10</span>
                            Cookies &amp; Similar Technologies
                        </h2>
                        <p>We use cookies and similar tracking technologies to operate the Services. Below is a description of the types of cookies we use:</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 dark:bg-gray-800">
                                        <th class="text-left px-4 py-3 rounded-tl-lg font-semibold text-gray-700 dark:text-gray-300">Type</th>
                                        <th class="text-left px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Purpose</th>
                                        <th class="text-left px-4 py-3 rounded-tr-lg font-semibold text-gray-700 dark:text-gray-300">Opt-Out</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">Strictly Necessary</td>
                                        <td class="px-4 py-3">Session management, authentication, CSRF protection. Required for the Services to function.</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Cannot be disabled</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">Functional</td>
                                        <td class="px-4 py-3">Remember your preferences (e.g., dark mode, language) across sessions.</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Browser settings</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">Analytics</td>
                                        <td class="px-4 py-3">Aggregate, anonymized statistics about platform usage to improve the Services.</td>
                                        <td class="px-4 py-3"><a href="mailto:privacy@silverqueen.pro" class="text-teal-600 dark:text-teal-400 hover:underline">Opt-out by request</a></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-sm">You can control cookies through your browser settings. Disabling strictly necessary cookies may prevent certain features from working correctly.</p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 11. Minors -->
                    <section id="minors">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">11</span>
                            Minors
                        </h2>
                        <p>
                            The Services are intended for users who are at least <strong>18 years of age</strong>, or the age of majority in their jurisdiction. We do not knowingly collect personal information from children under 18. If you are a parent or guardian and believe your child has provided us with personal information, please contact us immediately at <a href="mailto:privacy@silverqueen.pro" class="text-teal-600 dark:text-teal-400 hover:underline">privacy@silverqueen.pro</a> and we will delete such information promptly.
                        </p>
                        <p class="mt-3">
                            Where consent of a parent or legal guardian is required for minors under applicable law (e.g., the Philippine Child Safety Act), we will take appropriate steps to verify and obtain such consent before processing.
                        </p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 12. Cross-Border Transfers -->
                    <section id="cross-border">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">12</span>
                            Cross-Border Data Transfers
                        </h2>
                        <p>
                            As a globally accessible platform, your personal information may be transferred to and processed in countries other than the Philippines, including countries with different data protection laws. Where we transfer personal data internationally, we ensure adequate safeguards are in place as required under RA 10173 §21 and applicable international regulations, including:
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Binding contractual clauses with third-party processors that meet NPC-approved standards;</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Standard Contractual Clauses (SCCs) approved by the European Commission for transfers subject to GDPR;</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Transfer of data only to countries or organizations with an adequate level of data protection as recognized by the NPC.</span>
                            </li>
                        </ul>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 13. Third-Party Services -->
                    <section id="third-party">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">13</span>
                            Third-Party Services &amp; Links
                        </h2>
                        <p>
                            The Services may integrate with or contain links to third-party websites, APIs, and services that are not owned or controlled by Ginto. We are not responsible for the privacy practices of those third parties. We encourage you to review the privacy policies of any third-party services you access through the platform.
                        </p>
                        <p class="mt-3">
                            AI model responses are processed through third-party AI providers under data processing agreements. Prompts you submit may be processed on external AI infrastructure; please do not include sensitive personal information, financial data, or credentials in AI prompts.
                        </p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 14. AI & Content -->
                    <section id="ai-content">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">14</span>
                            AI-Generated Content &amp; User Prompts
                        </h2>
                        <p>
                            Ginto's core feature is AI-powered conversation and code generation. When you submit prompts or interact with AI features:
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Your prompts are transmitted to third-party AI model providers for inference. We do not train models on your personal data without your explicit consent.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Conversation history may be stored on our servers to provide continuity of service and allow you to review past interactions.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>You retain ownership of the content you create. AI-generated outputs are provided as-is and Ginto makes no warranty as to their accuracy.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>You are responsible for ensuring that any personal data included in your prompts complies with applicable data protection laws.</span>
                            </li>
                        </ul>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 15. Changes -->
                    <section id="changes">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">15</span>
                            Changes to This Policy
                        </h2>
                        <p>
                            We may update this Privacy Policy from time to time to reflect changes in our practices, applicable laws, or the Services. When we make material changes, we will:
                        </p>
                        <ul class="mt-2 space-y-1">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Update the "Last updated" date at the top of this page;</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Send a notification email to your registered email address; and/or</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-circle-dot text-teal-500 mt-1 text-xs shrink-0"></i>
                                <span>Display a prominent notice within the platform.</span>
                            </li>
                        </ul>
                        <p class="mt-3">Your continued use of the Services after the effective date of the revised policy constitutes your acceptance of the updated terms.</p>
                    </section>

                    <hr class="border-gray-200 dark:border-gray-800">

                    <!-- 16. Contact -->
                    <section id="contact">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-700 dark:text-teal-400 text-xs font-bold">16</span>
                            Contact Us
                        </h2>
                        <p>
                            If you have any questions, concerns, or requests regarding this Privacy Policy or our data processing practices, please contact our Data Protection Officer:
                        </p>
                        <div class="mt-4 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-200 dark:border-gray-800 space-y-3">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-building text-teal-500 w-5 text-center"></i>
                                <span class="text-gray-800 dark:text-gray-200 font-medium">Ginto</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-envelope text-teal-500 w-5 text-center"></i>
                                <a href="mailto:privacy@silverqueen.pro" class="text-teal-600 dark:text-teal-400 hover:underline">privacy@silverqueen.pro</a>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-globe text-teal-500 w-5 text-center"></i>
                                <a href="https://silverqueen.pro" class="text-teal-600 dark:text-teal-400 hover:underline">https://silverqueen.pro</a>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            We are committed to resolving privacy concerns promptly. If you are unsatisfied with our response, you have the right to escalate your complaint to the <strong>National Privacy Commission (NPC)</strong> of the Philippines at <a href="https://www.privacy.gov.ph" class="text-teal-600 dark:text-teal-400 hover:underline" target="_blank" rel="noopener noreferrer">privacy.gov.ph</a>.
                        </p>
                    </section>

                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-16 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 py-10">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <img src="/assets/images/ginto.png" alt="Ginto" class="h-7 w-7 rounded-lg">
                    <span class="font-semibold text-gray-900 dark:text-white">Ginto</span>
                    <span class="text-gray-400 dark:text-gray-600 text-sm ml-2">&copy; <?= date('Y') ?> Ginto. All rights reserved.</span>
                </div>
                <div class="flex items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                    <a href="/privacy" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors font-medium text-teal-600 dark:text-teal-400">Privacy Policy</a>
                    <a href="/chat" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors">Chat</a>
                    <a href="/upgrade" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors">Plans</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
