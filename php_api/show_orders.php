<?php
include 'condb.php';
header('Content-Type: application/json; charset=utf-8');

try {
   $sql = "
    SELECT 
        o.id AS order_id,           
        o.table_no,
        o.total_price,
        o.order_date,
        p.product_id,
        p.product_name,
        p.type_id,              -- ✅ เพิ่ม type_id จากตาราง products
        i.quantity,
        i.price,
        i.status,
        (i.quantity * i.price) AS subtotal
    FROM orders o
    JOIN order_items i ON o.id = i.order_id
    JOIN products p ON i.product_id = p.product_id
    ORDER BY o.order_date DESC
";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // แปลง string เป็น float เพื่อให้ Vue ใช้ toFixed ได้
    foreach ($orders as &$order) {
        $order['total_price'] = (float)$order['total_price'];
        $order['price'] = (float)$order['price'];
        $order['subtotal'] = (float)$order['subtotal'];
        $order['type_id'] = (int)$order['type_id']; // ✅ แปลง type_id เป็น integer
    }

    echo json_encode([
        "success" => true,
        "data" => $orders
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>