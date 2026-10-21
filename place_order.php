<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: shopping_list.php");
    exit();
}

$supplier_id      = $_POST['supplier_id'];
$delivery_address = $_POST['delivery_address'];
$delivery_phone   = $_POST['delivery_phone'];
$total_items      = $_POST['total_items'];
$selected_items   = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];

if (empty($selected_items)) {
    header("Location: shopping_list.php");
    exit();
}

// Save order with 0 total for now — supplier will set prices
$stmt = $conn->prepare("INSERT INTO orders (user_id, supplier_id, total_amount, delivery_address, status) VALUES (?, ?, 0, ?, 'pending_quote')");
$stmt->bind_param("iis", $user_id, $supplier_id, $delivery_address);
$stmt->execute();
$order_id = $conn->insert_id;

// Save order items — price is 0 until supplier quotes
foreach ($selected_items as $index) {
    $item_name = $_POST['item_name_' . $index];
    $item_unit = $_POST['item_unit_' . $index];
    $item_qty  = floatval($_POST['item_qty_' . $index]);

    $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, quantity, unit, price) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("isds", $order_id, $item_name, $item_qty, $item_unit);
    $stmt->execute();
}

// Save delivery phone in session temporarily
$_SESSION['delivery_phone'] = $delivery_phone;

header("Location: my_orders.php?placed=1");
exit();