<?php
namespace Core;

class View {
    public static function render($view, $data = []) {
        extract($data);
        require_once __DIR__ . '/../app/views/' . $view . '.view.php';

    }
}
