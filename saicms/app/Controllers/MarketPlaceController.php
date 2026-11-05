<?php
namespace App\Controllers;

use Core\Controller;
use IconCaptcha\IconCaptcha;
use Medoo\Medoo;
use DBConnect;

class MarketPlaceController extends Controller {

    public function __construct()
    {
        parent::__construct();
                            
    }
    
    public function marketFeed() {

        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        // --- NEW LOGIC ---
        // Instantiate the RewardsController
        $rewardsController = new RewardsController();
        
        // Get the referral data
        $rewardsData = $rewardsController->getDashboardData();
        
        // Pass the data to the view
        $this->view('marketplace/marketfeed', [
            'rewards' => $rewardsData
        ]);
        // --- END NEW LOGIC ---
    }

}
?>