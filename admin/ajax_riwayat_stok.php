<?php
// ajax_riwayat_stok.php
// Dipanggil via fetch() dari kelola_produk.php
// Mengembalikan HTML tabel riwayat stok_masuk untuk produk tertentu

session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$produkId = (int) ($_GET['produk_id'] ?? 0);
if ($produkId <= 0) {
    echo '<p style="text-align:center;padding:1.25rem;color:var(--text-muted);font-size:.85rem;margin:0">ID produk tidak valid.</p>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT sm.jumlah, sm.keterangan, sm.created_at,
           u.name AS admin_name
    FROM stok_masuk sm
    LEFT JOIN users u ON u.id = sm.admin_id
    WHERE sm.produk_id = ?
    ORDER BY sm.created_at DESC
    LIMIT 50
");
$stmt->execute([$produkId]);
$riwayat = $stmt->fetchAll();

if (empty($riwayat)):
    ?>
    <p style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:.85rem;margin:0">
        <i class="fa-solid fa-inbox" style="display:block;font-size:1.5rem;margin-bottom:.4rem;opacity:.3"></i>
        Belum ada riwayat stok masuk.
    </p>
<?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:.83rem">
        <thead>
            <tr>
                <th
                    style="background:var(--table-header-bg,#f1f5f9);padding:.55rem .9rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color)">
                    #</th>
                <th
                    style="background:var(--table-header-bg,#f1f5f9);padding:.55rem .9rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color)">
                    Jumlah</th>
                <th
                    style="background:var(--table-header-bg,#f1f5f9);padding:.55rem .9rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color)">
                    Keterangan</th>
                <th
                    style="background:var(--table-header-bg,#f1f5f9);padding:.55rem .9rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color)">
                    Oleh</th>
                <th
                    style="background:var(--table-header-bg,#f1f5f9);padding:.55rem .9rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--text-muted);border-bottom:1px solid var(--border-color)">
                    Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($riwayat as $idx => $r): ?>
                <tr>
                    <td style="padding:.55rem .9rem;border-bottom:1px solid var(--border-color);color:var(--text-muted)">
                        <?= $idx + 1 ?></td>
                    <td
                        style="padding:.55rem .9rem;border-bottom:1px solid var(--border-color);font-family:'JetBrains Mono',monospace;font-weight:700;color:#10b981">
                        +<?= number_format($r['jumlah']) ?>
                    </td>
                    <td style="padding:.55rem .9rem;border-bottom:1px solid var(--border-color);color:var(--text-muted)">
                        <?= $r['keterangan'] ? htmlspecialchars($r['keterangan']) : '<span style="opacity:.4">—</span>' ?>
                    </td>
                    <td style="padding:.55rem .9rem;border-bottom:1px solid var(--border-color);font-size:.78rem">
                        <?= htmlspecialchars($r['admin_name'] ?? '—') ?>
                    </td>
                    <td
                        style="padding:.55rem .9rem;border-bottom:1px solid var(--border-color);font-size:.78rem;color:var(--text-muted)">
                        <?= date('d M Y', strtotime($r['created_at'])) ?>
                        <span style="display:block;font-size:.72rem"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>