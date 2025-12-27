<?php
namespace Core;

class Controller {
    public function view($view, $data = []) {
        \Ginto\Core\View::view($view, $data);
    }
}
