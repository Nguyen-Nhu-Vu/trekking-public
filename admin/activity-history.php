
<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if (getRole() !== 'manager') {
    http_response_code(403);
    exit('Chỉ quản lý được xem lịch sử giao dịch.');
}
$current_page = 'activity-history';
$page_title = 'Lịch sử xử lý giao dịch - VNN Travel';
function ah($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$types = ['' => 'Tất cả loại', 'orders' => 'Đơn hàng', 'tour_bookings' => 'Đăng ký tour', 'products' => 'Nhập thêm kho'];
$type = (string)($_GET['type'] ?? '');
if (!array_key_exists($type, $types)) $type = '';
$keyword = mb_substr(trim((string)($_GET['keyword'] ?? '')), 0, 100);
$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');
foreach (['start','end'] as $key) {
    $d = $$key;
    $test = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    if (!$test || $test->format('Y-m-d') !== $d) $$key = '';
}
$where = ["category IN ('transactions','inventory')", "target_type IN ('orders','tour_bookings','products')"];
$params = [];
if ($type !== '') { $where[] = 'target_type = ?'; $params[] = $type; }
if ($keyword !== '') {
    $where[] = '(actor_name LIKE ? OR action LIKE ? OR target_id LIKE ? OR old_value LIKE ? OR new_value LIKE ?)';
    for ($i=0;$i<5;$i++) $params[] = '%'.$keyword.'%';
}
if ($start !== '') { $where[] = 'created_at >= ?'; $params[] = $start.' 00:00:00'; }
if ($end !== '') { $where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $end; }
$sqlWhere = implode(' AND ', $where);
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 20;
$count = $pdo->prepare('SELECT COUNT(*) FROM admin_audit_logs WHERE '.$sqlWhere);
$count->execute($params);
$total = (int)$count->fetchColumn();
$pageCount = max(1, (int)ceil($total/$size));
$page = min($page, $pageCount);
$sql = 'SELECT id,actor_name,action,target_type,target_id,old_value,new_value,created_at FROM admin_audit_logs WHERE '.$sqlWhere.' ORDER BY id DESC LIMIT '.$size.' OFFSET '.(($page-1)*$size);
$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
function ahRows(array $rows): void {
    foreach ($rows as $r): ?>
    <tr>
        <td><?= ah(date('d/m/Y H:i:s',strtotime($r['created_at']))) ?></td>
        <td><?= ah($r['actor_name']) ?></td>
        <td><?= ah($r['action']) ?></td>
        <td><?= ah(match($r['target_type']) {'orders'=>'Đơn hàng','tour_bookings'=>'Tour','products'=>'Kho',default=>$r['target_type']}) ?></td>
        <td>#<?= ah($r['target_id']) ?></td>
        <td><?= ah($r['old_value']) ?></td>
        <td><?= ah($r['new_value']) ?></td>
    </tr>
    <?php endforeach;
    if (!$rows): ?><tr><td colspan="7" style="text-align:center;padding:25px">Chưa có lịch sử phù hợp.</td></tr><?php endif;
}
if (isset($_GET['partial']) && $_GET['partial'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ahRows($rows);
    exit;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= ah($page_title) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box}.ah-page{flex:1;min-width:0;background:#f1f5f9;padding:24px;color:#173454}
.ah-box{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:20px;margin-bottom:16px}
.ah-box h1{font-size:22px;margin:0 0 10px}.ah-form{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.ah-form label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.ah-form input,.ah-form select{padding:10px;border:1px solid #cbd5e1;border-radius:8px}
.ah-form button,.ah-link{background:#166534;border:0;border-radius:8px;padding:11px 17px;color:white;text-decoration:none;cursor:pointer}
.ah-table{width:100%;border-collapse:collapse;min-width:980px;font-size:13px}.ah-table th,.ah-table td{padding:12px;text-align:left;border-bottom:1px solid #e2e8f0;vertical-align:top;overflow-wrap:anywhere}
.ah-table th{background:#f8fafc}.ah-table td:nth-child(6),.ah-table td:nth-child(7){max-width:230px}.ah-pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:20px}.ah-pagination a{padding:8px 13px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#173454}.ah-pagination a[aria-current]{background:#166534;color:white}
@media(max-width:700px){.ah-page{padding:12px}}
</style>
</head>
<body class="admin-body">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-main ah-page">
<section class="ah-box"><h1><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử xử lý thanh toán, đơn hàng & kho</h1>
<p style="color:#64748b;margin:0">Tự cập nhật 10 giây/lần. Mỗi thay đổi hiển thị người thao tác, thời gian, giá trị trước và sau.</p></section>
<section class="ah-box">
<form class="ah-form" method="GET">
<div><label for="type">Loại giao dịch</label><select id="type" name="type"><?php foreach($types as $v=>$label): ?><option value="<?= ah($v) ?>" <?= $type===$v?'selected':'' ?>><?= ah($label) ?></option><?php endforeach; ?></select></div>
<div><label for="keyword">Tìm theo người, hành động, mã, trạng thái</label><input id="keyword" name="keyword" value="<?= ah($keyword) ?>" placeholder="Từ khóa..."></div>
<div><label for="start">Từ ngày</label><input type="date" id="start" name="start" value="<?= ah($start) ?>"></div>
<div><label for="end">Đến ngày</label><input type="date" id="end" name="end" value="<?= ah($end) ?>"></div>
<button type="submit">Lọc</button><a class="ah-link" href="activity-history.php">Xóa lọc</a>
</form></section>
<section class="ah-box"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><strong><?= $total ?> bản ghi</strong><small id="lastSync">Dữ liệu hiện tại</small></div>
<div style="overflow:auto"><table class="ah-table"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Đối tượng</th><th>Mã</th><th>Trước</th><th>Sau</th></tr></thead><tbody id="activityRows"><?php ahRows($rows); ?></tbody></table></div>
<nav class="ah-pagination" aria-label="Trang lịch sử"><?php for($n=1;$n<=$pageCount;$n++): ?><a href="?<?= ah(http_build_query(array_merge($_GET,['page'=>$n]))) ?>" <?= $n===$page?'aria-current="page"':'' ?>><?= $n ?></a><?php endfor; ?></nav>
</section>
</main>
<script>
(() => {
  const tbody=document.getElementById('activityRows');
  const sync=document.getElementById('lastSync');
  async function refresh(){
    if(document.hidden) return;
    try{
      const url=new URL(location.href);url.searchParams.set('partial','1');
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store'});
      if(!response.ok) throw new Error('Lỗi tải lịch sử');
      tbody.innerHTML=await response.text();
      sync.textContent='Cập nhật: '+new Date().toLocaleTimeString('vi-VN');
    }catch(e){sync.textContent='Mất kết nối, sẽ thử lại';}
  }
  setInterval(refresh,10000);
})();
</script>
</body></html>
