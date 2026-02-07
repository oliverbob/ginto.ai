<?php
// src/Views/data.php
// This view expects $data to be set by the controller
header('Content-Type: application/json');
echo isset($data) ? json_encode($data) : json_encode(['error' => 'No data provided']);
