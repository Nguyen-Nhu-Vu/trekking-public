<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireStaff();
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['vnn_review_csrf'])) $_SESSION['vnn_review_csrf'] = bin2hex(random_bytes(24));
function rvh($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$current_page='reviews';
$flash = $_SESSION['review_notice'] ?? '';
unset($_SESSION['review_notice']);
function reviewRedirect(): void {
    $q = $_POST['return_filters'] ?? '';
    $q = is_string($q) ? $q : '';
    $url = 'index.php' . ($q !== '' ? '?' . ltrim($q,'?') : '');
    header('Location: '.$url); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals((string)$_SESSION['vnn_review_csrf'], (string)($_POST['csrf']??''))) {
        http_response_code(403); exit('Phiên làm việc không hợp lệ.');
    }
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'');
    if ($id > 0) {
        if ($action==='reply') {
            $reply=trim((string)($_POST['reply']??''));
            if (mb_strlen($reply,'UTF-8')>2000) $reply=mb_substr($reply,0,2000,'UTF-8');
            if ($reply==='') {
                $_SESSION['review_notice']='Nội dung phản hồi không được để trống.';
            } else {
                $stmt=$pdo->prepare('UPDATE reviews SET admin_reply=?, replied_at=NOW(), replied_by=? WHERE id=?');
                $stmt->execute([$reply,(int)$_SESSION['user_id'],$id]);
                $_SESSION['review_notice']='Đã lưu phản hồi đánh giá.';
            }
        } elseif ($action==='unreply') {
            $pdo->prepare('UPDATE reviews SET admin_reply=NULL,replied_at=NULL,replied_by=NULL WHERE id=?')->execute([$id]);
            $_SESSION['review_notice']='Đã xóa nội dung phản hồi.';
        } elseif ($action==='toggle') {
            $pdo->prepare('UPDATE reviews SET status=CASE WHEN status=1 THEN 0 ELSE 1 END WHERE id=?')->execute([$id]);
            $_SESSION['review_notice']='Đã đổi trạng thái hiển thị.';
        } elseif ($action==='delete') {
            $pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]);
            $_SESSION['review_notice']='Đã xóa đánh giá.';
        }
    }
    reviewRedirect();
}
$q=trim((string)($_GET['q']??''));
$type=(string)($_GET['type']??'all');
$replyFilter=(string)($_GET['reply']??'all');
$visibility=(string)($_GET['visibility']??'all');
$period=(string)($_GET['period']??'all');
$from=(string)($_GET['from']??''); $to=(string)($_GET['to']??'');
$rating=(int)($_GET['rating']??0);
$page=max(1,(int)($_GET['page']??1)); $perPage=10;
$where=['1=1']; $params=[];
if ($q!=='') { $where[]='(u.fullname LIKE ? OR r.comment LIKE ? OR t.title LIKE ? OR p.name LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like,$like); }
if ($type==='tour') $where[]='r.tour_id IS NOT NULL';
if ($type==='product') $where[]='r.product_id IS NOT NULL';
if ($replyFilter==='yes') $where[]="COALESCE(TRIM(r.admin_reply),'')<>''";
if ($replyFilter==='no') $where[]="COALESCE(TRIM(r.admin_reply),'')=''";
if ($visibility==='shown') $where[]='r.status=1';
if ($visibility==='hidden') $where[]='r.status=0';
if ($rating>=1 && $rating<=5) { $where[]='r.rating=?'; $params[]=$rating; }
if ($period==='day') $where[]='DATE(r.created_at)=CURDATE()';
if ($period==='week') $where[]='YEARWEEK(r.created_at,1)=YEARWEEK(CURDATE(),1)';
if ($period==='month') $where[]='YEAR(r.created_at)=YEAR(CURDATE()) AND MONTH(r.created_at)=MONTH(CURDATE())';
if ($period==='custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) { $where[]='r.created_at >= ?'; $params[]=$from.' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) { $where[]='r.created_at < DATE_ADD(?,INTERVAL 1 DAY)'; $params[]=$to; }
}
$sqlFrom=' FROM reviews r JOIN users u ON u.id=r.user_id LEFT JOIN tours t ON t.id=r.tour_id LEFT JOIN products p ON p.id=r.product_id WHERE '.implode(' AND ',$where);
$cnt=$pdo->prepare('SELECT COUNT(*) '.$sqlFrom); $cnt->execute($params); $count=(int)$cnt->fetchColumn();
$pages=max(1,(int)ceil($count/$perPage)); $page=min($pages,$page);
$offset=($page-1)*$perPage;
$stmt=$pdo->prepare('SELECT r.*,u.fullname,t.title AS tour_name,p.name AS product_name '.$sqlFrom.' ORDER BY r.created_at DESC,r.id DESC LIMIT '.$perPage.' OFFSET '.$offset);
$stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$filters=$_GET;unset($filters['page']);$query=http_build_query($filters);
function rvPage(int $p,array $filters): string { $filters['page']=$p;return 'index.php?'.http_build_query($filters); }
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đánh giá từ khách - VNN Travel</title>
<link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.vnn-rev-content{font-family:inherit}.vnn-rev-card{background:#fff;border:1px solid #e2e8f0;border-radius:13px;padding:18px;margin-bottom:16px}.vnn-rev-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}.vnn-rev-filter label{font-size:12px;font-weight:700;color:#243b53;display:block;margin-bottom:5px}.vnn-rev-filter input,.vnn-rev-filter select{height:40px;width:100%;min-width:0;padding:0 10px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;font-size:13px}.vnn-rev-buttons{display:flex;gap:8px;align-items:center;margin-top:13px}.vnn-btn{border:0;border-radius:8px;padding:9px 13px;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none}.vnn-btn-green{background:#166534;color:white}.vnn-btn-gray{background:#e2e8f0;color:#26364c}.vnn-btn-blue{background:#eaf2ff;color:#1859b5}.vnn-btn-red{background:#fee2e2;color:#b91c1c}.vnn-rev-table{width:100%;border-collapse:collapse;font-size:12px}.vnn-rev-table th{background:#f8fafc;font-size:12px;white-space:nowrap}.vnn-rev-table th,.vnn-rev-table td{padding:11px 9px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.vnn-rev-table td{overflow-wrap:anywhere}.vnn-rev-table .reply-box{background:#f0fdf4;border-left:3px solid #16a34a;border-radius:6px;padding:9px;margin-top:8px}.vnn-rev-table textarea{width:100%;min-height:65px;border:1px solid #cbd5e1;border-radius:7px;padding:9px;margin:8px 0 5px;font:inherit;resize:vertical}.vnn-rev-table .actions{display:flex;gap:5px;flex-wrap:wrap}.vnn-rev-status{padding:5px 8px;border-radius:20px;background:#dcfce7;color:#166534;display:inline-block;white-space:nowrap}.vnn-rev-pagination{display:flex;gap:7px;align-items:center;justify-content:flex-end;margin-top:15px;font-size:12px}.vnn-rev-pagination a,.vnn-rev-pagination strong{border:1px solid #cbd5e1;padding:7px 10px;border-radius:6px;text-decoration:none}.vnn-rev-pagination strong{background:#166534;color:white}.vnn-rev-alert{padding:10px 13px;background:#ecfdf5;color:#166534;border-radius:8px;margin-bottom:12px}@media(max-width:1150px){.vnn-rev-filter{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.vnn-rev-filter{grid-template-columns:1fr}.vnn-rev-card{padding:12px}.vnn-rev-table{min-width:930px}.vnn-rev-scroll{overflow-x:auto}}
</style></head><body class="admin-body">
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<main class="admin-main"><div class="admin-topbar"><strong>QUẢN LÝ ĐÁNH GIÁ TỪ KHÁCH HÀNG</strong></div><div class="admin-content vnn-rev-content">
<?php if ($flash): ?><div class="vnn-rev-alert"><?=rvh($flash)?></div><?php endif; ?>
<section class="vnn-rev-card"><form method="get"><div class="vnn-rev-filter">
<div><label>Tìm kiếm</label><input name="q" value="<?=rvh($q)?>" placeholder="Khách hàng, tour, sản phẩm, nội dung..."></div>
<div><label>Mục đánh giá</label><select name="type"><option value="all">Tất cả</option><option value="tour" <?=$type==='tour'?'selected':''?>>Tour</option><option value="product" <?=$type==='product'?'selected':''?>>Sản phẩm</option></select></div>
<div><label>Phản hồi</label><select name="reply"><option value="all">Tất cả phản hồi</option><option value="no" <?=$replyFilter==='no'?'selected':''?>>Chưa phản hồi</option><option value="yes" <?=$replyFilter==='yes'?'selected':''?>>Đã phản hồi</option></select></div>
<div><label>Thời gian</label><select name="period" id="rvPeriod"><option value="all">Toàn bộ</option><option value="day" <?=$period==='day'?'selected':''?>>Hôm nay</option><option value="week" <?=$period==='week'?'selected':''?>>Tuần này</option><option value="month" <?=$period==='month'?'selected':''?>>Tháng này</option><option value="custom" <?=$period==='custom'?'selected':''?>>Chọn khoảng ngày</option></select></div>
<div><label>Trạng thái hiển thị</label><select name="visibility"><option value="all">Tất cả</option><option value="shown" <?=$visibility==='shown'?'selected':''?>>Hiển thị</option><option value="hidden" <?=$visibility==='hidden'?'selected':''?>>Đang ẩn</option></select></div>
<div><label>Số sao</label><select name="rating"><option value="0">Tất cả sao</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>" <?=$rating===$i?'selected':''?>><?=$i?> sao</option><?php endfor;?></select></div>
<div class="rv-custom"><label>Từ ngày</label><input type="date" name="from" value="<?=rvh($from)?>"></div>
<div class="rv-custom"><label>Đến ngày</label><input type="date" name="to" value="<?=rvh($to)?>"></div>
</div><div class="vnn-rev-buttons"><button class="vnn-btn vnn-btn-green" type="submit"><i class="fa fa-filter"></i> Lọc / Tìm kiếm</button><a class="vnn-btn vnn-btn-gray" href="index.php">Xóa lọc</a><span style="margin-left:auto;font-size:12px;color:#64748b"><?=$count?> đánh giá</span></div></form></section>
<section class="vnn-rev-card"><div class="vnn-rev-scroll"><table class="vnn-rev-table"><thead><tr><th>STT</th><th>Khách hàng</th><th>Mục đánh giá</th><th>Số sao</th><th>Nội dung & phản hồi</th><th>Ngày gửi</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($rows as $i=>$r): ?><tr><td><?=$offset+$i+1?></td><td><strong><?=rvh($r['fullname'])?></strong></td><td><?=!empty($r['tour_id'])?'Tour: '.rvh($r['tour_name']):'SP: '.rvh($r['product_name'])?></td><td style="color:#ea9a08;white-space:nowrap"><?=str_repeat('★',(int)$r['rating'])?></td>
<td style="min-width:250px;max-width:390px"><div><?=nl2br(rvh($r['comment']))?></div>
<?php if(trim((string)($r['admin_reply']??''))!==''): ?><div class="reply-box"><strong>Đã phản hồi</strong><div><?=nl2br(rvh($r['admin_reply']))?></div><small><?=!empty($r['replied_at'])?rvh(date('d/m/Y H:i',strtotime($r['replied_at']))):''?></small></div><?php else: ?><small style="color:#d97706">Chưa phản hồi</small><?php endif; ?>
<details style="margin-top:9px"><summary style="color:#166534;cursor:pointer;font-weight:700"><?=trim((string)($r['admin_reply']??''))!==''?'Sửa phản hồi':'Phản hồi khách'?></summary><form method="post"><input type="hidden" name="csrf" value="<?=rvh($_SESSION['vnn_review_csrf'])?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="return_filters" value="<?=rvh($_SERVER['QUERY_STRING']??'')?>"><textarea name="reply" maxlength="2000" required placeholder="Nhập phản hồi của quản lý..."><?=rvh($r['admin_reply']??'')?></textarea><button class="vnn-btn vnn-btn-green">Lưu phản hồi</button></form></details>
</td><td style="white-space:nowrap"><?=rvh(date('d/m/Y H:i',strtotime($r['created_at'])))?></td><td><span class="vnn-rev-status" style="<?=$r['status']?'':'background:#fee2e2;color:#b91c1c'?>"><?=$r['status']?'Hiển thị':'Đang ẩn'?></span></td>
<td><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?=rvh($_SESSION['vnn_review_csrf'])?>"><input type="hidden" name="return_filters" value="<?=rvh($_SERVER['QUERY_STRING']??'')?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button name="action" value="toggle" class="vnn-btn vnn-btn-blue"><?=$r['status']?'Ẩn':'Hiện'?></button></form>
<?php if(trim((string)($r['admin_reply']??''))!==''): ?><form method="post" onsubmit="return confirm('Xóa phản hồi?')"><input type="hidden" name="csrf" value="<?=rvh($_SESSION['vnn_review_csrf'])?>"><input type="hidden" name="return_filters" value="<?=rvh($_SERVER['QUERY_STRING']??'')?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button name="action" value="unreply" class="vnn-btn vnn-btn-gray">Bỏ phản hồi</button></form><?php endif; ?>
<form method="post" onsubmit="return confirm('Xóa đánh giá này?')"><input type="hidden" name="csrf" value="<?=rvh($_SESSION['vnn_review_csrf'])?>"><input type="hidden" name="return_filters" value="<?=rvh($_SERVER['QUERY_STRING']??'')?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button name="action" value="delete" class="vnn-btn vnn-btn-red">Xóa</button></form></div></td></tr><?php endforeach; ?>
<?php if(!$rows):?><tr><td colspan="8" style="text-align:center;padding:30px">Không có đánh giá phù hợp.</td></tr><?php endif; ?>
</tbody></table></div><div class="vnn-rev-pagination"><span>Trang <?=$page?> / <?=$pages?></span><?php if($page>1):?><a href="<?=rvh(rvPage($page-1,$_GET))?>">Trước</a><?php endif; ?><?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><?php if($i===$page):?><strong><?=$i?></strong><?php else:?><a href="<?=rvh(rvPage($i,$_GET))?>"><?=$i?></a><?php endif;?><?php endfor;?><?php if($page<$pages):?><a href="<?=rvh(rvPage($page+1,$_GET))?>">Sau</a><?php endif;?></div></section>
</div></main><script>const pr=document.getElementById('rvPeriod');const custom=document.querySelectorAll('.rv-custom');function rvDates(){custom.forEach(e=>e.style.display=pr.value==='custom'?'block':'none')}pr.addEventListener('change',rvDates);rvDates();</script></body></html>
