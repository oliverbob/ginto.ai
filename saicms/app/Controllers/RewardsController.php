<?php
namespace App\Controllers;

use Core\Controller;
use Medoo\Medoo;

class RewardsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Gathers all necessary dashboard data for the logged-in user's referrals.
     *
     * @return array An array containing referral stats and lists.
     */
    public function getDashboardData(): array
    {
        $loggedInUserId = $_SESSION['user_id'] ?? null;

        if (!$loggedInUserId) {
            return [
                'total_referrals' => 0,
                'reward_points' => 0,
                'recent_referrals' => [],
                'referral_link' => '#'
            ];
        }

        // 1. Get the total count of successful referrals
        $totalReferrals = $this->db->count('referrals', [
            'referrer_id' => $loggedInUserId
        ]);

        // 2. Calculate rewards (simple 1-point-per-referral for now)
        // You can make this more complex later (e.g., check referral status)
        $rewardPoints = $totalReferrals; 

        // 3. Get the 5 most recent users who signed up via the link
        $recentReferrals = $this->db->select('referrals', [
            '[>]users' => ['referred_id' => 'id']
        ], [
            'users.id',
            'users.fullname',
            'users.username',
            'users.avatar',
            'referrals.created_at',
            'referrals.status'
        ], [
            'referrals.referrer_id' => $loggedInUserId,
            'ORDER' => ['referrals.created_at' => 'DESC'],
            'LIMIT' => 5
        ]);

        // Map DB keys to the legacy keys expected by views
        if (is_array($recentReferrals)) {
            foreach ($recentReferrals as &$r) {
                if (isset($r['fullname'])) $r['full_name'] = $r['fullname'];
                if (isset($r['avatar'])) $r['profile_picture'] = $r['avatar'];
            }
            unset($r);
        }

        // 4. Generate the user's personal referral link
        $referralLink = 'https://smartfed.ai/profile/' . $loggedInUserId;

        return [
            'total_referrals' => $totalReferrals,
            'reward_points' => $rewardPoints,
            'recent_referrals' => $recentReferrals,
            'referral_link' => $referralLink
        ];
    }
}