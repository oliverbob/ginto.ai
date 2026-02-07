<?php
namespace App\Models;

class Post {
    public static function all() {
        return [
            ['title' => 'First Post'],
            ['title' => 'Second Post']
        ];
    }
}