<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireStaff();
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['vnn_contact_csrf'])) $_SESSION['vnn_contact_csrf']=bin2hex(random_bytes(24));
function cth($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$current_page='contacts';
$msg=$_SESSION['vnn_contact_notice']??'';unset($_SESSION['vnn_contact_notice']);
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    if (!hash_equals((string)$_SESSION['vnn_contact_csrf'],(string)($_POST['csrf']??''))) {http_response_code(403);exit('Phiên làm việc không hợp lệ.');}
    $id=(int)($_POST['id']??0);
    if($id>0){$pdo->prepare('DELETE FROM contacts WHERE id=?')->execute([$id]);$_SESSION['vnn_contact_notice']='Đã xóa tin nhắn.';}
    $qs=(string)($_POST['return_filters']??''); header('Location: index.php'.($qs!==''?'?'.ltrim($qs,'?'):''));exit;
}
$q=trim((string)($_GET['q']??'')); $period=(string)($_GET['period']??'all');$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');
$where=['1=1'];$params=[];
if($q!==''){ $where[]='(fullname LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like,$like);}
if($period==='day')$where[]='DATE(created_at)=CURDATE()';
if($period==='week')$where[]='YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)';
if($period==='month')$where[]='YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())';
if($period==='custom'){
 if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='created_at>=?';$params[]=$from.' 00:00:00';}
 if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='created_at<DATE_ADD(?,INTERVAL 1 DAY)';$params[]=$to;}
}
$condition=implode(' AND ',$where);$c=$pdo->prepare('SELECT COUNT(*) FROM contacts WHERE '.$condition);$c->execute($params);$total=(int)$c->fetchColumn();
$perPage=10;$pages=max(1,(int)ceil($total/$perPage));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*$perPage;
$stmt=$pdo->prepare('SELECT * FROM contacts WHERE '.$condition.' ORDER BY created_at DESC,id DESC LIMIT '.$perPage.' OFFSET '.$offset);$stmt->execute($params);$contacts=$stmt->fetchAll(PDO::FETCH_ASSOC);
function ctPage(int $num,array $params):string{$params['page']=$num;return 'index.php?'.http_build_query($params);}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tin nhắn liên hệ - VNN Travel</title>
<link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>.ct-card{background:#fff;border:1px solid #e2e8f0;border-radius:13px;padding:17px;margin-bottom:16px}.ct-filter{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px;align-items:end}.ct-filter label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.ct-filter input,.ct-filter select{width:100%;min-width:0;height:40px;background:#fff;border:1px solid #cbd5e1;padding:0 10px;border-radius:8px;font-size:13px}.ct-button{border:0;border-radius:8px;padding:9px 13px;display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:12px;text-decoration:none;cursor:pointer}.ct-green{background:#166534;color:white}.ct-gray{background:#e2e8f0;color:#334155}.ct-red{background:#fee2e2;color:#b91c1c}.ct-actions{display:flex;align-items:center;gap:8px;margin-top:13px}.ct-table{width:100%;border-collapse:collapse;font-size:12px}.ct-table td,.ct-table th{padding:11px 9px;text-align:left;border-bottom:1px solid #e2e8f0;vertical-align:top}.ct-table th{background:#f8fafc}.ct-paging{display:flex;gap:7px;justify-content:flex-end;margin-top:12px;font-size:12px;align-items:center}.ct-paging a,.ct-paging strong{padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none}.ct-paging strong{background:#166534;color:white}@media(max-width:1050px){.ct-filter{grid-template-columns:1fr 1fr}}@media(max-width:650px){.ct-filter{grid-template-columns:1fr}.ct-scroll{overflow-x:auto}.ct-table{min-width:790px}}</style></head>
<body class="admin-body"><?php require __DIR__.'/../includes/sidebar.php'; ?>
<main class="admin-main"><div class="admin-topbar"><strong>HỘP THƯ TIN NHẮN TỪ KHÁCH HÀNG</strong></div><div class="admin-content">
<?php if($msg):?><div class="ct-card" style="background:#ecfdf5;color:#166534"><?=cth($msg)?></div><?php endif;?>
<div class="ct-card"><form method="get"><div class="ct-filter">
<div><label>Tìm kiếm</label><input name="q" placeholder="Họ tên, số điện thoại, email, nội dung..." value="<?=cth($q)?>"></div>
<div><label>Khoảng thời gian</label><select name="period" id="ctPeriod"><option value="all">Tất cả</option><option value="day" <?=$period==='day'?'selected':''?>>Hôm nay</option><option value="week" <?=$period==='week'?'selected':''?>>Tuần này</option><option value="month" <?=$period==='month'?'selected':''?>>Tháng này</option><option value="custom" <?=$period==='custom'?'selected':''?>>Theo lịch</option></select></div>
<div class="ct-custom"><label>Từ ngày</label><input type="date" name="from" value="<?=cth($from)?>"></div>
<div class="ct-custom"><label>Đến ngày</label><input type="date" name="to" value="<?=cth($to)?>"></div>
</div><div class="ct-actions"><button class="ct-button ct-green" type="submit"><i class="fa fa-search"></i> Tìm kiếm / Lọc</button><a class="ct-button ct-gray" href="index.php">Xóa lọc</a><span style="margin-left:auto;font-size:12px;color:#64748b"><?=$total?> tin nhắn</span></div></form></div>
<div class="ct-card"><div class="ct-scroll"><table class="ct-table"><thead><tr><th>STT</th><th>Họ tên</th><th>Email</th><th>Số điện thoại</th><th>Nội dung thư</th><th>Ngày gửi</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($contacts as $i=>$c):?><tr><td><?=$offset+$i+1?></td><td><strong><?=cth($c['fullname'])?></strong></td><td><?=cth($c['email'])?></td><td><?=cth($c['phone']?:'—')?></td><td style="max-width:420px;overflow-wrap:anywhere"><?=nl2br(cth($c['message']))?></td><td style="white-space:nowrap"><?=cth(date('d/m/Y H:i',strtotime($c['created_at'])))?></td><td><form method="post" onsubmit="return confirm('Xóa tin nhắn này?')"><input type="hidden" name="csrf" value="<?=cth($_SESSION['vnn_contact_csrf'])?>"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><input type="hidden" name="return_filters" value="<?=cth($_SERVER['QUERY_STRING']??'')?>"><button class="ct-button ct-red" name="action" value="delete">Xóa</button></form></td></tr><?php endforeach; ?>
<?php if(!$contacts):?><tr><td colspan="7" style="text-align:center;padding:30px">Không tìm thấy tin nhắn phù hợp.</td></tr><?php endif;?>
</tbody></table></div><div class="ct-paging"><span>Trang <?=$page?> / <?=$pages?></span><?php if($page>1):?><a href="<?=cth(ctPage($page-1,$_GET))?>">Trước</a><?php endif;?><?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><?php if($i===$page):?><strong><?=$i?></strong><?php else:?><a href="<?=cth(ctPage($i,$_GET))?>"><?=$i?></a><?php endif;?><?php endfor;?><?php if($page<$pages):?><a href="<?=cth(ctPage($page+1,$_GET))?>">Sau</a><?php endif;?></div></div>
</div></main><script>const cp=document.getElementById('ctPeriod'),cd=document.querySelectorAll('.ct-custom');function ctDates(){cd.forEach(e=>e.style.display=cp.value==='custom'?'block':'none')}cp.addEventListener('change',ctDates);ctDates();</script></body></html>
