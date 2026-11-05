<?php
namespace Core;

class Controller {
    public function __construct()
    {
        // base controller constructor (kept intentionally minimal)
        // Allows child controllers to call parent::__construct() safely
    }

    public function view($view, $data = []) {
        \Ginto\Core\View::view($view, $data);
    }
}
