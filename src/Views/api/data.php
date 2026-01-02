<?php
// src/Views/api/data.php
// This view expects $data to be set by the controller
header('Content-Type: application/json');
echo json_encode($data);
