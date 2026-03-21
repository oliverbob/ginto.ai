<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | Ginto</title>
    <meta name="description" content="Ginto Terms of Service — the rules governing use of Ginto AI, Ginto Mall, and Ginto Pay credits.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { gray: { 950: '#0a0a0f' } } } }
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
    <div class="bg-gradient-to-br from-teal-900/30 via-gray-900/50 to-blue-900/30 dark:from-teal-950/50 dark:via-gray-950 dark:to-blue-950/50 py-16 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <div class="inline-flex items-center gap-2 bg-teal-500/10 border border-teal-500/20 text-teal-400 rounded-full px-4 py-1.5 text-sm font-medium mb-6">
                <i class="fas fa-file-contract text-xs"></i>
                Legal
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white mb-4">Terms of Service</h1>
            <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">Please read these terms carefully before using Ginto.</p>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-3">Effective Date: March 21, 2026 &bull; Last Updated: March 21, 2026</p>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex gap-8">

            <!-- TOC -->
            <aside class="hidden lg:block w-64 flex-shrink-0">
                <div class="sticky top-24">
                    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Table of Contents</h3>
                    <nav class="space-y-1 text-sm">
                        <a href="#acceptance" class="toc-link block text-gray-600 dark:text-gray-400 py-1">1. Acceptance of Terms</a>
                        <a href="#services" class="toc-link block text-gray-600 dark:text-gray-400 py-1">2. Description of Services</a>
                        <a href="#ginto-pay" class="toc-link block text-gray-600 dark:text-gray-400 py-1">3. Ginto Mall Credits (Ginto Pay)</a>
                        <a href="#marketplace" class="toc-link block text-gray-600 dark:text-gray-400 py-1">4. Marketplace &amp; Transactions</a>
                        <a href="#accounts" class="toc-link block text-gray-600 dark:text-gray-400 py-1">5. Accounts &amp; Security</a>
                        <a href="#prohibited" class="toc-link block text-gray-600 dark:text-gray-400 py-1">6. Prohibited Conduct</a>
                        <a href="#intellectual-property" class="toc-link block text-gray-600 dark:text-gray-400 py-1">7. Intellectual Property</a>
                        <a href="#disclaimers" class="toc-link block text-gray-600 dark:text-gray-400 py-1">8. Disclaimers</a>
                        <a href="#limitation" class="toc-link block text-gray-600 dark:text-gray-400 py-1">9. Limitation of Liability</a>
                        <a href="#governing-law" class="toc-link block text-gray-600 dark:text-gray-400 py-1">10. Governing Law</a>
                        <a href="#changes" class="toc-link block text-gray-600 dark:text-gray-400 py-1">11. Changes to Terms</a>
                        <a href="#contact" class="toc-link block text-gray-600 dark:text-gray-400 py-1">12. Contact</a>
                    </nav>
                    <div class="mt-6 p-4 bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Also see our <a href="/privacy" class="text-teal-500 hover:underline">Privacy Policy</a>.</p>
                    </div>
                </div>
            </aside>

            <!-- Main -->
            <main class="flex-1 min-w-0">
                <div class="prose prose-gray dark:prose-invert max-w-none prose-headings:font-bold prose-a:text-teal-600 dark:prose-a:text-teal-400 prose-a:no-underline hover:prose-a:underline">

                    <!-- 1 -->
                    <section id="acceptance" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">01</span> Acceptance of Terms
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">By accessing or using any Ginto service — including ginto.ai, Ginto Mall, and the Ginto mobile application — you agree to be bound by these Terms of Service ("<strong>Terms</strong>") and all applicable laws and regulations. If you do not agree with any of these Terms, you are prohibited from using or accessing this site. These Terms apply to all visitors, users, buyers, sellers, and any other persons who access or use the Service.</p>
                    </section>

                    <!-- 2 -->
                    <section id="services" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">02</span> Description of Services
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">Ginto provides the following primary services:</p>
                        <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                            <li class="flex gap-2"><i class="fas fa-robot text-teal-500 mt-1 flex-shrink-0"></i><span><strong>Ginto AI</strong> — An AI-powered assistant and development platform offering chat, code generation, and sandbox tooling.</span></li>
                            <li class="flex gap-2"><i class="fas fa-store text-teal-500 mt-1 flex-shrink-0"></i><span><strong>Ginto Mall</strong> — A peer-to-peer marketplace platform that allows registered sellers to list products and buyers to purchase them.</span></li>
                            <li class="flex gap-2"><i class="fas fa-wallet text-teal-500 mt-1 flex-shrink-0"></i><span><strong>Ginto Pay</strong> — Ginto Mall's internal closed-loop platform credit wallet. It is not an electronic money instrument. Funds loaded into Ginto Pay are processed exclusively by licensed third-party payment providers and may only be used for purchases within the Ginto Mall platform. See Section 3 for full details.</span></li>
                        </ul>
                    </section>

                    <!-- 3 — GINTO PAY KEY SECTION -->
                    <section id="ginto-pay" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-teal-500/30 dark:border-teal-500/20 shadow-sm">
                        <div class="inline-flex items-center gap-1.5 text-xs font-bold text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-900/30 border border-teal-200 dark:border-teal-700 rounded-full px-3 py-1 mb-4">
                            <i class="fas fa-shield-alt"></i> Important — Read Carefully
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">03</span> Ginto Pay — Closed-Loop Platform Credit Wallet
                        </h2>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.1 What Ginto Pay Is</h3>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3"><strong>Ginto Pay</strong> is Ginto Mall's internal closed-loop credit wallet. It is not an electronic money instrument. Funds loaded into Ginto Pay are processed exclusively by licensed third-party payment providers and may only be used for purchases within the Ginto Mall platform.</p>
                        <div class="my-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-xl text-sm text-amber-800 dark:text-amber-300 leading-relaxed">
                            <strong>Disclosure:</strong> All monetary transactions — including QR code payments (GCash, Maya, QR PH) and credit/debit card payments — are processed exclusively by <strong>PayMongo</strong>, a BSP-licensed payment service provider. International transactions may be processed by <strong>PayPal</strong>. <strong>Ginto does not hold, process, transmit, or custody funds at any point.</strong> Ginto records a ledger credit in your Mall account only after the licensed payment processor confirms successful receipt of payment.
                        </div>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.2 Nature of Mall Credits</h3>
                        <ul class="space-y-2 text-gray-600 dark:text-gray-400 text-sm">
                            <li class="flex gap-2"><i class="fas fa-circle text-xs text-gray-400 mt-1.5 flex-shrink-0"></i><span>Ginto Pay credits are <strong>non-cash platform credits</strong>. They are not electronic money, stored value, legal tender, or a financial instrument of any kind.</span></li>
                            <li class="flex gap-2"><i class="fas fa-circle text-xs text-gray-400 mt-1.5 flex-shrink-0"></i><span>Ginto Pay credits are <strong>non-withdrawable</strong>. Once loaded, they can only be applied toward purchases in Ginto Mall and cannot be redeemed for cash, transferred to another user, or refunded except as expressly provided in these Terms or required by applicable law.</span></li>
                            <li class="flex gap-2"><i class="fas fa-circle text-xs text-gray-400 mt-1.5 flex-shrink-0"></i><span>Ginto Pay credits have <strong>no monetary value outside of Ginto Mall</strong> and do not constitute a deposit, investment, or financial product.</span></li>
                            <li class="flex gap-2"><i class="fas fa-circle text-xs text-gray-400 mt-1.5 flex-shrink-0"></i><span>Processing fees charged by the payment provider (PayMongo, PayPal) are non-refundable and are not collected by Ginto.</span></li>
                        </ul>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.3 Regulatory Status</h3>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed text-sm">Ginto is not a bank, a quasi-bank, a money service business, an electronic money issuer (EMI), or any other type of financial institution regulated by the Bangko Sentral ng Pilipinas (BSP) or the Securities and Exchange Commission (SEC). Ginto operates as a marketplace/merchant aggregator. All monetary settlement to sellers is executed by licensed payment service providers on Ginto's instruction. Ginto does not transmit funds.</p>

                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-5 mb-2">3.4 Seller Payouts</h3>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed text-sm">Proceeds from sales are settled to sellers through licensed third-party payment processors. Ginto instructs these processors to release funds based on confirmed, completed orders. Ginto does not hold seller proceeds; settlement timing is subject to the policies of the applicable payment processor.</p>
                    </section>

                    <!-- 4 -->
                    <section id="marketplace" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">04</span> Marketplace &amp; Transactions
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">Ginto Mall is a platform that connects independent sellers and buyers. Ginto is not a party to any transaction between sellers and buyers and does not act as an agent, broker, or intermediary. Sellers are solely responsible for the accuracy of their listings, delivery of goods or services, and compliance with applicable consumer protection laws.</p>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">Buyers are responsible for conducting due diligence before purchasing. Ginto does not guarantee the quality, safety, legality, or accuracy of any listing. Disputes between buyers and sellers must first be attempted to be resolved between the parties directly; Ginto may provide mediation assistance at its discretion but is under no obligation to do so.</p>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">All prices on Ginto Mall are displayed in Philippine Peso (₱ PHP) unless otherwise stated. Currency conversion for international payment methods is handled by the applicable payment processor.</p>
                    </section>

                    <!-- 5 -->
                    <section id="accounts" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">05</span> Accounts &amp; Security
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">You must be at least 18 years of age (or the age of majority in your jurisdiction) to create a Ginto account. You are responsible for maintaining the confidentiality of your login credentials and for all activities that occur under your account. Ginto reserves the right to terminate accounts that violate these Terms.</p>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">Buyer accounts created through the Ginto Mall checkout flow are standard user accounts with a "buyer" designation. They are subject to the same Terms as all other accounts.</p>
                    </section>

                    <!-- 6 -->
                    <section id="prohibited" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">06</span> Prohibited Conduct
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">You agree not to:</p>
                        <ul class="space-y-1.5 text-gray-600 dark:text-gray-400 text-sm">
                            <li class="flex gap-2"><i class="fas fa-times-circle text-red-400 mt-0.5 flex-shrink-0"></i><span>Use the platform for any unlawful purpose or in violation of any applicable regulation</span></li>
                            <li class="flex gap-2"><i class="fas fa-times-circle text-red-400 mt-0.5 flex-shrink-0"></i><span>List or sell prohibited, counterfeit, or illegal items on Ginto Mall</span></li>
                            <li class="flex gap-2"><i class="fas fa-times-circle text-red-400 mt-0.5 flex-shrink-0"></i><span>Attempt to circumvent payment processing, charge-back abuse, or engage in fraudulent transactions</span></li>
                            <li class="flex gap-2"><i class="fas fa-times-circle text-red-400 mt-0.5 flex-shrink-0"></i><span>Reverse-engineer, scrape, or interfere with the platform's infrastructure</span></li>
                            <li class="flex gap-2"><i class="fas fa-times-circle text-red-400 mt-0.5 flex-shrink-0"></i><span>Use AI-generated content to create spam, misinformation, or harmful material via Ginto AI tools</span></li>
                            <li class="flex gap-2"><i class="fas fa-times-circle text-red-400 mt-0.5 flex-shrink-0"></i><span>Launder money or use Ginto Mall credits as a mechanism for money laundering or terrorist financing</span></li>
                        </ul>
                    </section>

                    <!-- 7 -->
                    <section id="intellectual-property" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">07</span> Intellectual Property
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">The Ginto platform, including all software, design, logos, and content produced by Ginto, is owned by Ginto and protected by applicable intellectual property laws. You may not copy, modify, distribute, or create derivative works from Ginto's proprietary materials without express written permission. Seller product listings remain the intellectual property of the respective seller.</p>
                    </section>

                    <!-- 8 -->
                    <section id="disclaimers" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">08</span> Disclaimers
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">The Ginto platform is provided on an "as is" and "as available" basis without warranties of any kind, either express or implied. Ginto does not warrant that the service will be uninterrupted, error-free, or free of harmful components.</p>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">AI-generated content produced through Ginto AI tools is provided for informational and productivity purposes only. It does not constitute professional legal, financial, medical, or other regulated advice. Users are solely responsible for how they apply AI-generated output.</p>
                    </section>

                    <!-- 9 -->
                    <section id="limitation" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">09</span> Limitation of Liability
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">To the fullest extent permitted by law, Ginto and its officers, employees, and agents shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising from your use of the platform, including but not limited to: loss of profits, loss of data, failed transactions, or disputes between marketplace participants. Ginto's total aggregate liability shall not exceed the amount you paid to Ginto in the twelve (12) months preceding the claim.</p>
                    </section>

                    <!-- 10 -->
                    <section id="governing-law" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">10</span> Governing Law
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">These Terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any dispute arising under these Terms shall be subject to the exclusive jurisdiction of the courts of the Philippines, without regard to conflict-of-law provisions. For international users, local mandatory consumer protection laws of your country of residence may also apply.</p>
                    </section>

                    <!-- 11 -->
                    <section id="changes" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">11</span> Changes to These Terms
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed">Ginto reserves the right to update or modify these Terms at any time. We will notify users of material changes by updating the "Last Updated" date at the top of this page and, where reasonably practicable, by sending notice to your registered email. Your continued use of the platform after such notice constitutes acceptance of the updated Terms.</p>
                    </section>

                    <!-- 12 -->
                    <section id="contact" class="mb-10 p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                            <span class="text-teal-500">12</span> Contact
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4">If you have questions about these Terms, please contact us:</p>
                        <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-globe text-teal-500 w-4"></i>
                                <a href="https://ginto.ai" class="text-teal-500 hover:underline">https://ginto.ai</a>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-envelope text-teal-500 w-4"></i>
                                <a href="mailto:support@ginto.ai" class="text-teal-500 hover:underline">support@ginto.ai</a>
                            </div>
                        </div>
                    </section>

                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-gray-200 dark:border-gray-800 mt-12 py-8 px-4">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-2">
                <img src="/assets/images/ginto.png" alt="Ginto" class="h-6 w-6 rounded">
                <span>&copy; <?= date('Y') ?> Ginto. All rights reserved.</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="/privacy" class="hover:text-teal-500 transition-colors">Privacy Policy</a>
                <a href="/terms" class="hover:text-teal-500 transition-colors font-medium text-teal-600 dark:text-teal-400">Terms of Service</a>
                <a href="/chat" class="hover:text-teal-500 transition-colors">Chat</a>
            </div>
        </div>
    </footer>

</body>
</html>
