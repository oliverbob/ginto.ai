<?php
namespace Ginto\Models;

use Ginto\Core\Database;
use Medoo\Medoo;

/**
 * A member's vaulted card reference for Academy assisted auto-renew. We never store card numbers —
 * PayMongo keeps the card; we keep the customer + payment-method ids and the display digits.
 */
class AcademyCard
{
    private Medoo $db;
    private string $table = 'academy_card_tokens';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Upsert the saved card for a user (one card per user). $card = ['id','brand','last4','exp_month','exp_year']. */
    public function save(int $userId, string $customerId, array $card): void
    {
        $data = [
            'customer_id'       => $customerId,
            'payment_method_id' => (string) ($card['id'] ?? ''),
            'brand'             => $card['brand'] ?? null,
            'last4'             => $card['last4'] ?? null,
            'exp_month'         => $card['exp_month'] !== null ? (string) $card['exp_month'] : null,
            'exp_year'          => $card['exp_year'] !== null ? (string) $card['exp_year'] : null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
        try {
            if (is_array($this->db->get($this->table, ['id'], ['user_id' => $userId]))) {
                $this->db->update($this->table, $data, ['user_id' => $userId]);
            } else {
                $this->db->insert($this->table, array_merge($data, ['user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]));
            }
        } catch (\Throwable $e) {
            error_log('AcademyCard save error: ' . $e->getMessage());
        }
    }

    /** The user's saved card, or null. */
    public function forUser(int $userId): ?array
    {
        try {
            $r = $this->db->get($this->table, '*', ['user_id' => $userId]);
            return is_array($r) ? $r : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
