<?php

// Mall App

class Mall {
    private $name;
    private $stores;

    public function __construct($name) {
        $this->name = $name;
        $this->stores = [];
    }

    public function addStore($store) {
        array_push($this->stores, $store);
    }

    public function getStores() {
        return $this->stores;
    }
}

class Store {
    private $name;
    private $products;

    public function __construct($name) {
        $this->name = $name;
        $this->products = [];
    }

    public function addProduct($product) {
        array_push($this->products, $product);
    }

    public function getProducts() {
        return $this->products;
    }
}

class Product {
    private $name;
    private $price;

    public function __construct($name, $price) {
        $this->name = $name;
        $this->price = $price;
    }

    public function getName() {
        return $this->name;
    }

    public function getPrice() {
        return $this->price;
    }
}

// Create a new mall
$mall = new Mall("My Mall");

// Create stores
$store1 = new Store("Store 1");
$store2 = new Store("Store 2");

// Add stores to the mall
$mall->addStore($store1);
$mall->addStore($store2);

// Create products
$product1 = new Product("Product 1", 10.99);
$product2 = new Product("Product 2", 9.99);
$product3 = new Product("Product 3", 12.99);

// Add products to stores
$store1->addProduct($product1);
$store1->addProduct($product2);
$store2->addProduct($product3);

// Print the mall's stores and their products
foreach ($mall->getStores() as $store) {
    echo "Store: " . $store->getName() . "\n";
    foreach ($store->getProducts() as $product) {
        echo "Product: " . $product->getName() . ", Price: " . $product->getPrice() . "\n";
    }
}

?>