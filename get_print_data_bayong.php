<?php
require_once 'db.php';

$query = "SELECT * FROM `sir_bayong_print` ORDER BY `id` DESC";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) : ?>
    <tr>
        <td><?php echo htmlspecialchars($row["SR_DR"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["Date"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["Supplier"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["Quantity"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["Description"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["Office"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["Vehicle"] ?? ''); ?></td>
        <td>₱<?php echo number_format($row["Amount"] ?? 0, 2); ?></td>
        <td><?php echo htmlspecialchars($row["PO_no"] ?? ''); ?></td>
        <td>₱<?php echo number_format($row["PO_amount"] ?? 0, 2); ?></td>
        <td><?php echo htmlspecialchars($row["Remarks"] ?? ''); ?></td>
        <td>
            <input type="checkbox" class="form-check-input select-item" data-id="<?php echo $row['id']; ?>" style="width: 25px; height: 25px; cursor: pointer; border: 2px solid #0d6efd;">
            <button class="btn btn-danger btn-sm delete-print-btn" data-id="<?php echo $row['id']; ?>" style="width: 25px; height: 25px; padding: 0;">
                <i class="fas fa-trash" style="font-size: 10px;"></i>
            </button>

        </td>

    </tr>
<?php endwhile; ?>