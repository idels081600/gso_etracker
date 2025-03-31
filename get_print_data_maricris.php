<?php
require_once 'db.php';

$query = "SELECT * FROM `Maam_mariecris_print` ORDER BY `id` DESC";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) : ?>
    <tr>
        <td><?php echo htmlspecialchars($row["SR_DR"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["date"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["department"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["store"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["activity"] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row["no_of_pax"] ?? ''); ?></td>
        <td>₱<?php echo number_format($row["amount"] ?? 0, 2); ?></td>
        <td>₱<?php echo number_format($row["total"] ?? 0, 2); ?></td>
        <td><?php echo htmlspecialchars($row["PO_no"] ?? ''); ?></td>
        <td>₱<?php echo number_format($row["PO_amount"] ?? 0, 2); ?></td>
        <td><?php echo htmlspecialchars($row["Remarks"] ?? ''); ?></td>
        <td>
            <!-- <input type="checkbox" class="form-check-input select-item" data-id="<?php echo $row['id']; ?>" style="width: 25px; height: 25px; cursor: pointer; border: 2px solid #0d6efd;"> -->
            <button class="btn btn-danger btn-sm delete-print-btn" data-id="<?php echo $row['id']; ?>" style="width: 25px; height: 25px; padding: 0;">
                <i class="fas fa-trash" style="font-size: 10px;"></i>
            </button>
        </td>
    </tr>
<?php endwhile; ?>                                                                                
