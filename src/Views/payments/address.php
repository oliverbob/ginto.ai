<?php
/**
 * Crypto Payment Wallet Addresses
 * This file stores wallet addresses for crypto payments.
 * Keep this file secure and do not expose directly to web.
 */

return [
    'usdt_bep20' => [
        'network' => 'BNB Smart Chain (BEP20)',
        'token' => 'USDT',
        'address' => '0x4ff1c66c5da2e687b3e1a156a0c8e4cf30eb2f06',
    ],

    // SilverQueen collects to its own wallet, separate from registration payments.
    // The QR served at 'qr' encodes exactly this address — if one changes, regenerate
    // the other, because buyers scan the image rather than read the text.
    'silverqueen_usdt_bep20' => [
        'network' => 'BNB Smart Chain (BEP20)',
        'token'   => 'USDT',
        'address' => '0xa1937b43a867f0a85b70b07797b00d747fc4d1b6',
        'qr'      => '/assets/images/pay_usdt.jpeg',
    ],
];
