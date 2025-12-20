<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;

class MallController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        // Accept DB as an optional injection, fallback to Database::getInstance if not provided
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                $this->db = null;
            }
        }
    }

    public function marketplace()
    {
        // Sample categories and products for the demo marketplace
        $categories = [
            ['id' => 1, 'name' => 'Electronics'],
            ['id' => 2, 'name' => 'Home & Garden'],
            ['id' => 3, 'name' => 'Health & Beauty'],
            ['id' => 4, 'name' => 'Fashion'],
            ['id' => 5, 'name' => 'Sports'],
        ];

        $products = [
            ['id' => 101, 'title' => 'Wireless Headphones', 'price' => 59.99, 'currency' => '$', 'category_id' => 1, 'rating' => 4.5, 'badge' => 'Bestseller', 'image' => 'https://picsum.photos/seed/headphones/600/400', 'excerpt' => 'Comfortable bluetooth headphones with long battery life.'],
            ['id' => 102, 'title' => 'Smart Watch Pro', 'price' => 129.00, 'currency' => '$', 'category_id' => 1, 'rating' => 4.7, 'badge' => 'New', 'image' => 'https://picsum.photos/seed/watch/600/400', 'excerpt' => 'Track your health and notifications with style.'],
            ['id' => 201, 'title' => 'Ceramic Vase Set', 'price' => 34.50, 'currency' => '$', 'category_id' => 2, 'rating' => 4.2, 'badge' => '', 'image' => 'https://picsum.photos/seed/vase/600/400', 'excerpt' => 'Handcrafted ceramic vases for modern interiors.'],
            ['id' => 301, 'title' => 'Organic Skincare Kit', 'price' => 49.99, 'currency' => '$', 'category_id' => 3, 'rating' => 4.6, 'badge' => 'Eco', 'image' => 'https://picsum.photos/seed/skincare/600/400', 'excerpt' => 'Gentle, organic skincare starter set.'],
            ['id' => 401, 'title' => 'Classic Denim Jacket', 'price' => 79.00, 'currency' => '$', 'category_id' => 4, 'rating' => 4.4, 'badge' => '', 'image' => 'https://picsum.photos/seed/jacket/600/400', 'excerpt' => 'Timeless denim jacket for everyday wear.'],
            ['id' => 501, 'title' => 'Adjustable Dumbbells', 'price' => 199.99, 'currency' => '$', 'category_id' => 5, 'rating' => 4.8, 'badge' => 'Hot', 'image' => 'https://picsum.photos/seed/dumbbells/600/400', 'excerpt' => 'Space-saving adjustable dumbbells for home gym.'],
        ];

        // Pass data to the view
        $this->view('mall/marketplace', [
            'categories' => $categories,
            'products' => $products,
            'title' => 'ePower Mall'
        ]);
    }
}
