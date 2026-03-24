<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$reviews = [];
$reviewsTableExists = false;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'reviews'");
    $reviewsTableExists = (bool)$check->fetchColumn();
} catch (Throwable $e) {
    $reviewsTableExists = false;
}

if ($reviewsTableExists) {
    try {
        $sql = "SELECT r.id, r.rating, r.comment, r.created_at,
                       p.name AS product_name, u.name AS user_name
                FROM reviews r
                LEFT JOIN products p ON p.id = r.product_id
                LEFT JOIN users u ON u.id = r.user_id
                ORDER BY r.created_at DESC
                LIMIT 80";
        $stmt = $pdo->query($sql);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $reviews = [];
    }
}

staffPageStart($pdo, '評價管理', 'reviews');
?>
<section class="staff-panel">
    <?php if (!$reviewsTableExists): ?>
        <div class="staff-empty-hint">
            目前資料庫尚未建立 <code>reviews</code> 資料表，因此暫時無法顯示評論清單。完成資料表後此頁會自動接上真實評論資料。
        </div>
    <?php else: ?>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>會員</th>
                        <th>評分</th>
                        <th>評論內容</th>
                        <th>日期</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reviews)): ?>
                        <tr>
                            <td colspan="6">目前沒有評論資料。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($row['product_name'] ?? '未知商品')); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['user_name'] ?? '未知會員')); ?></td>
                                <td><?php echo number_format((int)($row['rating'] ?? 0)); ?>/5</td>
                                <td><?php echo htmlspecialchars((string)($row['comment'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)$row['created_at']))); ?></td>
                                <td><a href="#" class="staff-link-btn danger">刪除</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>

