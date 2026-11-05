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

    // SilverQueen collects into the SilverQueen wallet platform at sq.silverqueen.pro —
    // this is the operator's own deposit address there, so a payment made here
    // is credited to that account automatically once the chain confirms it.
    // Separate from registration payments, which still go to the address above.
    // The QR served at 'qr' encodes exactly this address — if one changes, regenerate
    // the other, because buyers scan the image rather than read the text.
    'silverqueen_usdt_bep20' => [
        'network' => 'BNB Smart Chain (BEP20)',
        'token'   => 'USDT',
        'address' => '0x8d1918428ad1D1D3B166715b570E25FE39Bc3814',
        'qr'      => '/assets/images/pay_usdt_silverqueen.png',
    ],
];
