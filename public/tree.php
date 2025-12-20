<?php

// MySQL connection settings
$host = 'localhost';
$database = 'tree_example';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get child nodes
function getChildren($parent_id) {
    global $conn;
    $sql = "SELECT * FROM tree WHERE parent_id = '$parent_id'";
    $result = $conn->query($sql);
    $children = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $children[] = $row;
        }
    }
    return $children;
}

// Function to print tree recursively
function printTree($node, $level = 0) {
    echo str_repeat('  ', $level) . $node['name'] . "\n";
    $children = getChildren($node['id']);
    foreach ($children as $child) {
        printTree($child, $level + 1);
    }
}

// Get root node
$sql = "SELECT * FROM tree WHERE parent_id IS NULL";
$result = $conn->query($sql);
$root = $result->fetch_assoc();

// Print tree
printTree($root);

$conn->close();

?>
