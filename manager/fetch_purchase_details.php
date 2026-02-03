<?php
include('../config.php');

if(isset($_GET['purchase_id'])) {
    $p_id = $_GET['purchase_id'];

    // 1. Get Items
    $query = "SELECT pi.*, pr.name as product_name 
              FROM purchase_items pi 
              JOIN products pr ON pi.product_id = pr.id 
              WHERE pi.purchase_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $p_id);
    $stmt->execute();
    $items = $stmt->get_result();

    echo '<table class="table table-sm table-bordered">';
    echo '<thead class="bg-light"><tr><th>Product</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th></tr></thead>';
    echo '<tbody>';
    while($row = $items->fetch_assoc()) {
        echo "<tr>
                <td>{$row['product_name']}</td>
                <td>{$row['quantity']}</td>
                <td>RS ".number_format($row['unit_price'], 2)."</td>
                <td class='text-end'>RS ".number_format($row['line_total'], 2)."</td>
              </tr>";
    }
    echo '</tbody></table>';
}
?>