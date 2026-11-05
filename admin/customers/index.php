<?php
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isLoggedIn() || getRole() !== 'manager') {
    http_response_code(403);
    exit('Chức năng Quản lý khách hàng chỉ dành cho tài khoản Quản lý.');
}
$current_page = 'customers';
$page_title = 'Quản lý khách hàng - VNN Travel';
function ch($x): string { return htmlspecialchars((string)($x ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($x): string { return number_format((float)$x, 0, ',', '.') . ' đ'; }
function dateVN($x): string { $t = strtotime((string)$x); return $t ? date('d/m/Y H:i', $t) : '—'; }
function intFilter($name, $default = 0): int { $v = filter_var($_GET[$name] ?? $default, FILTER_VALIDATE_INT); return $v === false ? (int)$default : max(0, (int)$v); }
function numFilter($name): ?float {
    if (!isset($_GET[$name]) || trim((string)$_GET[$name]) === '') return null;
    $v = filter_var($_GET[$name], FILTER_VALIDATE_FLOAT);
    return ($v === false || $v < 0) ? null : (float)$v;
}
function filterDate($name): string {
    $v = (string)($_GET[$name] ?? '');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : '';
}

/* 2 bang tong hop duoc tinh DOC LAP theo user_id: khong nhan dong khi JOIN. 
   orders: 'Đã giao'/'Hoàn thành' la GIAO DICH HOAN TAT, chua phai chung tu thanh toan.
   tour_bookings: chi 'Đã thanh toán'/'Đã hoàn thành' duoc tinh vao doanh thu tour.
   Khach vang lai (user_id NULL) khong duoc gan suy doan theo ten/email. */
$aggregate = "
 SELECT u.id, u.fullname, u.email, u.phone, u.address, u.status, u.created_at,
 COALESCE(o.order_count,0) AS order_count, COALESCE(o.order_amount,0) AS order_amount,
 COALESCE(o.last_order,NULL) AS last_order,
 COALESCE(t.tour_count,0) AS tour_count, COALESCE(t.tour_amount,0) AS tour_amount,
 COALESCE(t.last_tour,NULL) AS last_tour,
 COALESCE(r.review_count,0) AS review_count,
 (COALESCE(o.order_amount,0)+COALESCE(t.tour_amount,0)) AS total_spent
 FROM users u
 LEFT JOIN (
   SELECT user_id, COUNT(*) AS order_count, SUM(total_amount) AS order_amount,
          MAX(created_at) AS last_order
   FROM orders WHERE user_id IS NOT NULL AND status IN ('Đã giao','Hoàn thành')
   GROUP BY user_id
 ) o ON o.user_id=u.id
 LEFT JOIN (
   SELECT user_id, COUNT(*) AS tour_count, SUM(total_price) AS tour_amount,
          MAX(created_at) AS last_tour
   FROM tour_bookings WHERE user_id IS NOT NULL AND status IN ('Đã thanh toán','Đã hoàn thành')
   GROUP BY user_id
 ) t ON t.user_id=u.id
 LEFT JOIN (
   SELECT user_id, COUNT(*) AS review_count FROM reviews GROUP BY user_id
 ) r ON r.user_id=u.id
 WHERE u.role='customer'
";

$q = mb_substr(trim((string)($_GET['q'] ?? '')),0,100,'UTF-8');
$account = (string)($_GET['account'] ?? 'all');
$activity = (string)($_GET['activity'] ?? 'all');
$review = (string)($_GET['review'] ?? 'all');
$spendType = (string)($_GET['spend_type'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'latest');
$joinedFrom = filterDate('joined_from'); $joinedTo = filterDate('joined_to');
$spentMin = numFilter('spent_min'); $spentMax = numFilter('spent_max');
$ordersMin = intFilter('orders_min'); $toursMin = intFilter('tours_min');
$page = max(1,intFilter('page',1));
$sorts = [
 'latest' => 'c.id DESC', 'oldest' => 'c.id ASC',
 'name_asc' => 'c.fullname ASC', 'name_desc' => 'c.fullname DESC',
 'spent_desc' => 'c.total_spent DESC, c.id DESC',
 'spent_asc' => 'c.total_spent ASC, c.id DESC',
 'orders_desc' => 'c.order_count DESC, c.id DESC',
 'tours_desc' => 'c.tour_count DESC, c.id DESC',
 'reviews_desc' => 'c.review_count DESC, c.id DESC'
];
if (!isset($sorts[$sort])) $sort='latest';
if (!in_array($account,['all','active','locked'],true)) $account='all';
if (!in_array($activity,['all','orders','tours','both','none'],true)) $activity='all';
if (!in_array($review,['all','yes','no'],true)) $review='all';
if (!in_array($spendType,['all','products','tours'],true)) $spendType='all';

$where = ['1=1']; $args=[];
if ($q !== '') {
 $where[]='(c.fullname LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR CAST(c.id AS CHAR) LIKE ? OR c.address LIKE ?)';
 for ($i=0;$i<5;$i++) $args[]='%'.$q.'%';
}
if ($account !== 'all') $where[]='c.status=' . ($account === 'active' ? '1' : '0');
if ($activity === 'orders') $where[]='c.order_count>0';
if ($activity === 'tours') $where[]='c.tour_count>0';
if ($activity === 'both') $where[]='c.order_count>0 AND c.tour_count>0';
if ($activity === 'none') $where[]='c.order_count=0 AND c.tour_count=0';
if ($review === 'yes') $where[]='c.review_count>0';
if ($review === 'no') $where[]='c.review_count=0';
if ($spendType === 'products') $where[]='c.order_amount>0';
if ($spendType === 'tours') $where[]='c.tour_amount>0';
if ($joinedFrom !== '') { $where[]='c.created_at>=?'; $args[]=$joinedFrom.' 00:00:00'; }
if ($joinedTo !== '') { $where[]='c.created_at<?'; $args[]=(new DateTimeImmutable($joinedTo))->modify('+1 day')->format('Y-m-d 00:00:00'); }
if ($spentMin !== null) { $where[]='c.total_spent>=?'; $args[]=$spentMin; }
if ($spentMax !== null) { $where[]='c.total_spent<=?'; $args[]=$spentMax; }
if ($ordersMin) { $where[]='c.order_count>=?'; $args[]=$ordersMin; }
if ($toursMin) { $where[]='c.tour_count>=?'; $args[]=$toursMin; }
$filtered = ' FROM ('.$aggregate.') c WHERE '.implode(' AND ',$where);
$customers=[];
$total=0; $stats=['orders'=>0,'tours'=>0,'spend'=>0,'reviewers'=>0]; $error='';
$pageSize=10; $pageCount=1;
try {
 $stmt=$pdo->prepare('SELECT COUNT(*)'.$filtered); $stmt->execute($args); $total=(int)$stmt->fetchColumn();
 $pageCount=max(1,(int)ceil($total/$pageSize)); $page=min($page,$pageCount);
 $stat=$pdo->prepare('SELECT SUM(c.order_count),SUM(c.tour_count),SUM(c.total_spent),SUM(c.review_count>0)'.$filtered);
 $stat->execute($args); $vals=$stat->fetch(PDO::FETCH_NUM) ?: [];
 $stats=['orders'=>(int)($vals[0]??0),'tours'=>(int)($vals[1]??0),'spend'=>(float)($vals[2]??0),'reviewers'=>(int)($vals[3]??0)];
 $sql='SELECT c.*'.$filtered.' ORDER BY '.$sorts[$sort].' LIMIT '.$pageSize.' OFFSET '.(($page-1)*$pageSize);
 $stmt=$pdo->prepare($sql); $stmt->execute($args); $customers=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) { error_log('Customer manager page: '.$e->getMessage()); $error='Không tải được dữ liệu khách hàng. Hãy kiểm tra cấu trúc bảng trên MySQL.'; }
function qs(array $replace=[]): string { return '?'.http_build_query(array_merge($_GET,$replace)); }
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=ch($page_title)?></title>
<link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box}.cm-page{flex:1;min-width:0;background:#f2f5fa;padding:24px;color:#173454;font:15px/1.55 Arial,sans-serif}
.cm-card{background:white;border:1px solid #e0e8f1;border-radius:14px;padding:21px;margin-bottom:18px;box-shadow:0 3px 15px #1231  }
.cm-heading{font-size:25px;margin:0;font-weight:800;color:#10294b}.cm-muted{color:#64748b;font-size:14px;margin:5px 0 0}
.cm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.cm-stat{background:#fff;border:1px solid #dde7f1;border-radius:13px;padding:17px;min-width:0}
.cm-stat span{display:block;color:#64748b;font-size:13px}.cm-stat strong{display:block;margin-top:6px;font-size:23px;color:#10345b;overflow-wrap:anywhere}
.cm-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.cm-field{min-width:0}.cm-field.wide{grid-column:span 2}.cm-field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}
.cm-control{width:100%;height:43px;border:1px solid #cbd5e1;background:#fff;border-radius:9px;padding:8px 11px;font:14px Arial,sans-serif;color:#173454}
.cm-actions{grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap}.cm-button{background:#166534;color:#fff!important;padding:11px 17px;border:0;border-radius:9px;text-decoration:none;font-size:14px;font-weight:700;cursor:pointer}.cm-button.alt{background:#e2e8f0;color:#334155!important}
.cm-table-wrap{max-width:100%;overflow:auto;border:1px solid #e2e8f0;border-radius:9px}.cm-table{width:100%;min-width:920px;border-collapse:collapse;table-layout:auto}.cm-table th,.cm-table td{padding:12px 13px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:middle;font-size:13px;white-space:normal;overflow-wrap:break-word}
.cm-table th{background:#f8fafc;color:#173454;font-weight:800;white-space:nowrap}.cm-table tr.selected{background:#ecfdf5}.cm-table tr:hover{background:#f8fafc}.cm-table a{font-weight:700;color:#0369a1;text-decoration:none}.cm-table .cm-amount{white-space:nowrap;color:#166534;font-weight:800}
.cm-pager{display:flex;gap:8px;justify-content:center;align-items:center;margin:17px 0;flex-wrap:wrap}.cm-pager a,.cm-pager strong{padding:7px 12px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#173454}.cm-pager strong{background:#166534;color:white}
.cm-profile-head{display:flex;justify-content:space-between;align-items:start;gap:15px;flex-wrap:wrap}.cm-profile-head h2{margin:0;font-size:21px}.cm-profile-head p{margin:5px 0;color:#64748b}.cm-two{display:grid;grid-template-columns:1fr 1fr;gap:15px}.cm-group{border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}.cm-group h3{margin:0;background:#f8fafc;padding:13px 15px;font-size:17px}.cm-group .cm-table th,.cm-group .cm-table td{font-size:12px}.cm-group .cm-table{min-width:520px}.cm-review{padding:13px 15px;border-bottom:1px solid #e2e8f0}.cm-review:last-child{border:0}.cm-review small{color:#64748b}.cm-review p{margin:6px 0 0;overflow-wrap:anywhere}.cm-stars{color:#ea580c}.cm-tag{display:inline-block;background:#dcfce7;color:#166534;border-radius:30px;padding:3px 9px;font-size:12px;font-weight:700}.cm-tag.off{background:#fee2e2;color:#991b1b}.cm-note{font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fcd34d;padding:12px 15px;border-radius:10px;margin-top:12px}.cm-empty{padding:24px;text-align:center;color:#64748b}
@media(max-width:1200px){.cm-stats{grid-template-columns:repeat(2,1fr)}.cm-filter{grid-template-columns:repeat(2,1fr)}.cm-two{grid-template-columns:1fr}}@media(max-width:660px){.cm-page{padding:12px}.cm-stats,.cm-filter{grid-template-columns:1fr}.cm-field.wide{grid-column:span 1}.cm-card{padding:14px}}
</style></head><body class="admin-body">
<?php require_once __DIR__.'/../includes/sidebar.php'; ?>
<main class="admin-main cm-page">
<section class="cm-card"><h1 class="cm-heading"><i class="fa-solid fa-users"></i> QUẢN LÝ KHÁCH HÀNG</h1><p class="cm-muted">Chỉ tài khoản Quản lý — thống kê khách đăng ký, giao dịch hoàn tất, đặt tour và đánh giá</p></section>
<?php if($error):?><div class="cm-card" style="color:#b91c1c"><?=ch($error)?></div><?php endif;?>
<section class="cm-stats" style="margin-bottom:18px">
<div class="cm-stat"><span>Khách phù hợp bộ lọc</span><strong><?=number_format($total,0,',','.')?></strong></div>
<div class="cm-stat"><span>Đơn hàng hoàn tất</span><strong><?=number_format($stats['orders'],0,',','.')?></strong></div>
<div class="cm-stat"><span>Tour đã thanh toán/hoàn thành</span><strong><?=number_format($stats['tours'],0,',','.')?></strong></div>
<div class="cm-stat"><span>Tổng giao dịch tính theo quy tắc</span><strong><?=money($stats['spend'])?></strong></div>
</section>
<section class="cm-card">
<form class="cm-filter" method="GET" action="index.php">
<div class="cm-field wide"><label>Tìm tên, email, SĐT, mã KH, địa chỉ</label><input class="cm-control" name="q" value="<?=ch($q)?>" placeholder="Nhập từ khóa..."></div>
<div class="cm-field"><label>Trạng thái tài khoản</label><select class="cm-control" name="account"><option value="all">Tất cả</option><option value="active" <?=$account==='active'?'selected':''?>>Đang hoạt động</option><option value="locked" <?=$account==='locked'?'selected':''?>>Bị khóa</option></select></div>
<div class="cm-field"><label>Loại hoạt động</label><select class="cm-control" name="activity"><option value="all">Tất cả</option><option value="orders" <?=$activity==='orders'?'selected':''?>>Có mua sản phẩm</option><option value="tours" <?=$activity==='tours'?'selected':''?>>Có đặt tour</option><option value="both" <?=$activity==='both'?'selected':''?>>Cả mua hàng và đặt tour</option><option value="none" <?=$activity==='none'?'selected':''?>>Chưa có giao dịch hoàn tất</option></select></div>
<div class="cm-field"><label>Đánh giá</label><select class="cm-control" name="review"><option value="all">Tất cả</option><option value="yes" <?=$review==='yes'?'selected':''?>>Đã từng đánh giá</option><option value="no" <?=$review==='no'?'selected':''?>>Chưa đánh giá</option></select></div>
<div class="cm-field"><label>Chi tiêu thuộc</label><select class="cm-control" name="spend_type"><option value="all">Tất cả</option><option value="products" <?=$spendType==='products'?'selected':''?>>Sản phẩm</option><option value="tours" <?=$spendType==='tours'?'selected':''?>>Tour</option></select></div>
<div class="cm-field"><label>Đăng ký từ ngày</label><input class="cm-control" type="date" name="joined_from" value="<?=ch($joinedFrom)?>"></div>
<div class="cm-field"><label>Đăng ký đến ngày</label><input class="cm-control" type="date" name="joined_to" value="<?=ch($joinedTo)?>"></div>
<div class="cm-field"><label>Chi tiêu tối thiểu (đ)</label><input class="cm-control" type="number" min="0" name="spent_min" value="<?=ch($spentMin)?>" placeholder="0"></div>
<div class="cm-field"><label>Chi tiêu tối đa (đ)</label><input class="cm-control" type="number" min="0" name="spent_max" value="<?=ch($spentMax)?>" placeholder="Không giới hạn"></div>
<div class="cm-field"><label>Ít nhất bao nhiêu đơn hoàn tất</label><input class="cm-control" type="number" min="0" name="orders_min" value="<?=$ordersMin?>"></div>
<div class="cm-field"><label>Ít nhất bao nhiêu tour đã trả</label><input class="cm-control" type="number" min="0" name="tours_min" value="<?=$toursMin?>"></div>
<div class="cm-field wide"><label>Sắp xếp theo</label><select class="cm-control" name="sort">
<?php foreach(['latest'=>'Tài khoản mới nhất','oldest'=>'Tài khoản cũ nhất','name_asc'=>'Tên A → Z','name_desc'=>'Tên Z → A','spent_desc'=>'Chi tiêu cao nhất','spent_asc'=>'Chi tiêu thấp nhất','orders_desc'=>'Nhiều đơn hàng nhất','tours_desc'=>'Nhiều tour nhất','reviews_desc'=>'Nhiều đánh giá nhất'] as $key=>$label):?><option value="<?=ch($key)?>" <?=$sort===$key?'selected':''?>><?=ch($label)?></option><?php endforeach;?></select></div>
<div class="cm-actions"><button class="cm-button" type="submit"><i class="fa-solid fa-filter"></i> Áp dụng bộ lọc</button><a class="cm-button alt" href="index.php">Xóa tất cả bộ lọc</a></div>
</form></section>
<section class="cm-card"><h2 style="font-size:19px;margin:0 0 13px">Danh sách khách hàng (<?=number_format($total,0,',','.')?>)</h2>
<div class="cm-table-wrap"><table class="cm-table"><thead><tr><th>Mã</th><th>Khách hàng</th><th>Email / SĐT</th><th>Trạng thái</th><th>Đơn hoàn tất</th><th>Tour đã trả</th><th>Đánh giá</th><th>Tổng giao dịch</th><th>Chi tiết</th></tr></thead><tbody>
<?php foreach($customers as $c):?><tr ><td>#<?=(int)$c['id']?></td><td><strong><?=ch($c['fullname'])?></strong></td><td><?=ch($c['email'])?><br><small><?=ch($c['phone']?:'—')?></small></td><td><span class="cm-tag <?=(int)$c['status']===1?'':'off'?>"><?=(int)$c['status']===1?'Hoạt động':'Khóa'?></span></td><td><?=(int)$c['order_count']?></td><td><?=(int)$c['tour_count']?></td><td><?=(int)$c['review_count']?></td><td class="cm-amount"><?=money($c['total_spent'])?></td><td><a href="detail.php?id=<?=(int)$c['id']?>">Xem</a></td></tr><?php endforeach;?>
<?php if(!$customers):?><tr><td colspan="9" class="cm-empty">Không có khách hàng phù hợp với các bộ lọc.</td></tr><?php endif;?></tbody></table></div>
<?php if($pageCount>1):?><nav class="cm-pager" aria-label="Phân trang"><a href="<?=ch(qs(['page'=>max(1,$page-1)]))?>">‹ Trước</a><strong>Trang <?=$page?> / <?=$pageCount?></strong><a href="<?=ch(qs(['page'=>min($pageCount,$page+1)]))?>">Sau ›</a></nav><?php endif;?></section>
</main></body></html>
