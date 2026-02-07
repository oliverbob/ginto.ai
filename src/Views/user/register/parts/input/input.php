<?php
/**
 * File Input Components for Registration
 * 
 * Usage: Include with the $inputType variable set:
 *   - 'bank_receipt' : Bank transfer receipt upload
 *   - 'crypto_receipt' : Crypto transaction screenshot upload
 */

if (!isset($inputType)) {
    return;
}

switch ($inputType) {
    case 'bank_receipt':
        ?>
        <input type="file" name="bank_receipt" id="bank_receipt" accept="image/*,.pdf" class="hidden">
        <?php
        break;
        
    case 'crypto_receipt':
        ?>
        <input type="file" name="crypto_receipt" id="crypto_receipt" accept="image/*,.pdf" class="hidden">
        <?php
        break;
        
    case 'gcash_receipt':
        ?>
        <input type="file" name="gcash_receipt" id="gcash_receipt" accept="image/*,.pdf" class="hidden">
        <?php
        break;
}
