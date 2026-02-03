<?php
include('../config.php');

if (isset($_GET['purchase_id'])) {
    $p_id = intval($_GET['purchase_id']);

    // 1. Fetch the main Purchase record
    // We join with branches to show which branch this purchase belonged to
    $sql_main = "SELECT p.*, b.name as branch_name 
                 FROM purchases p 
                 JOIN branches b ON p.branch_id = b.id 
                 WHERE p.id = ?";
    
    $stmt_p = $conn->prepare($sql_main);
    $stmt_p->bind_param("i", $p_id);
    $stmt_p->execute();
    $purchase = $stmt_p->get_result()->fetch_assoc();

    if ($purchase) {
        // 2. Fetch the specific items in this purchase
        $sql_items = "SELECT pi.*, pr.name as product_name, pr.unit 
                      FROM purchase_items pi 
                      JOIN products pr ON pi.product_id = pr.id 
                      WHERE pi.purchase_id = ?";
        
        $stmt_i = $conn->prepare($sql_items);
        $stmt_i->bind_param("i", $p_id);
        $stmt_i->execute();
        $items_result = $stmt_i->get_result();
        ?>

        <div class="row mb-4 p-3 bg-light rounded border">
            <div class="col-md-6">
                <p class="mb-1 text-muted small text-uppercase fw-bold">Supplier Info</p>
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($purchase['supplier_name']) ?></h6>
                <p class="text-muted small">Inv # <?= htmlspecialchars($purchase['invoice_number'] ?: 'N/A') ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1 text-muted small text-uppercase fw-bold">Branch</p>
                <h6 class="fw-bold"><?= htmlspecialchars($purchase['branch_name']) ?></h6>
                <span class="badge <?= $purchase['payment_status'] == 'paid' ? 'bg-success' : 'bg-danger' ?>">
                    Payment: <?= ucfirst($purchase['payment_status']) ?>
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Product Name</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $calc_total = 0;
                    while ($item = $items_result->fetch_assoc()): 
                        $calc_total += $item['line_total'];
                    ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="text-muted extra-small" style="font-size: 0.75rem;">ID: #<?= $item['product_id'] ?></div>
                            </td>
                            <td class="text-center"><?= $item['quantity'] ?> <span class="text-muted small"><?= $item['unit'] ?></span></td>
                            <td class="text-end">RS <?= number_format($item['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold">RS <?= number_format($item['line_total'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Subtotal:</th>
                        <td class="text-end">RS <?= number_format($purchase['total_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end text-danger">Discount:</th>
                        <td class="text-end text-danger">- RS <?= number_format($purchase['discount'], 2) ?></td>
                    </tr>
                    <tr class="table-active">
                        <th colspan="3" class="text-end fs-5">Grand Total:</th>
                        <td class="text-end fs-5 fw-bold text-primary">RS <?= number_format($purchase['grand_total'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($purchase['notes'])): ?>
            <div class="mt-3 p-2 border-start border-4 border-info bg-light">
                <small class="text-muted d-block">Notes:</small>
                <?= nl2br(htmlspecialchars($purchase['notes'])) ?>
            </div>
        <?php endif; ?>

        <?php
    } else {
        echo '<div class="alert alert-danger">Error: Purchase record not found.</div>';
    }
} else {
    echo '<div class="alert alert-warning">Invalid Request: No Purchase ID provided.</div>';
}
?>