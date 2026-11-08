
<?php
$page_title = "Trang Chủ - VNN Travel";
$active_menu = 'home';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$featured_tours = $pdo->query("
    SELECT t.*,
           IFNULL(l.name, 'Tây Bắc') AS loc_name
    FROM tours t
    LEFT JOIN locations l ON t.location_id = l.id
    ORDER BY t.id ASC
    LIMIT 9
")->fetchAll(PDO::FETCH_ASSOC);

$featured_products = $pdo->query("
    SELECT *
    FROM products
    WHERE status = 1
    ORDER BY id DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

function home_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.about-gl-section {
    padding:70px 0;
    background:#fff;
}
.about-gl-grid {
    display:grid;
    grid-template-columns:1.1fr 1fr;
    gap:50px;
    align-items:center;
}
.about-gl-badge {
    display:inline-block;
    background:#ffedd5;
    color:#ea580c;
    font-size:.85rem;
    font-weight:800;
    padding:6px 16px;
    border-radius:20px;
    text-transform:uppercase;
    margin-bottom:15px;
}
.about-gl-title {
    font-size:2.8rem;
    font-weight:800;
    color:#0f172a;
    line-height:1.25;
    margin-bottom:20px;
}
.about-gl-title span {color:#f97316}
.about-gl-desc {
    color:#475569;
    font-size:1.05rem;
    line-height:1.8;
    margin-bottom:25px;
}
.about-feature-cards {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}
.about-f-card {
    background:#fff;
    border:1px solid #f1f5f9;
    padding:24px;
    border-radius:16px;
    box-shadow:0 4px 20px rgba(0,0,0,.04);
    transition:transform .25s ease,border-color .25s ease;
}
.about-f-card:hover {
    transform:translateY(-4px);
    border-color:#fed7aa;
}
.about-f-icon {
    width:48px;
    height:48px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.3rem;
    margin-bottom:16px;
}
.about-f-card h4 {
    font-size:1.1rem;
    color:#0f172a;
    margin-bottom:8px;
}
.about-f-card p {
    font-size:.88rem;
    color:#64748b;
    line-height:1.6;
    margin:0;
}
.tour-card {
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(0,0,0,.06);
    border:1px solid #e2e8f0;
    display:flex;
    flex-direction:column;
    transition:transform .25s ease;
}
.tour-card:hover {transform:translateY(-5px)}
.card-img-wrap {
    position:relative;
    height:220px;
    background:#f1f5f9;
    overflow:hidden;
}
.card-img-wrap img {
    width:100%;
    height:100%;
    object-fit:cover;
}
.tour-badge {
    position:absolute;
    top:14px;
    left:14px;
    background:#ea580c;
    color:#fff;
    font-size:.78rem;
    font-weight:700;
    padding:4px 12px;
    border-radius:20px;
}
/* Khong hien so cho tren the tour trang chu */
.tour-card .tour-slots {display:none!important}
.card-body {
    padding:20px;
    flex:1;
    display:flex;
    flex-direction:column;
}
.home-prod-card {
    background:#fff;
    border-radius:12px;
    padding:16px;
    border:1px solid #e2e8f0;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    transition:transform .25s ease,box-shadow .25s ease;
}
.home-prod-card:hover {
    transform:translateY(-4px);
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}
.home-prod-thumb {
    height:195px;
    border-radius:8px;
    overflow:hidden;
    background:#f8fafc;
    margin-bottom:12px;
}
.home-prod-thumb img {
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}
.home-prod-brand {
    font-size:.72rem;
    font-weight:800;
    text-transform:uppercase;
    color:#94a3b8;
    letter-spacing:.8px;
    margin-bottom:6px;
    display:block;
}
.home-prod-title {
    font-size:.96rem!important;
    font-weight:700!important;
    color:#0f172a!important;
    line-height:1.5!important;
    margin:0 0 14px!important;
    padding:0!important;
    height:auto!important;
    max-height:none!important;
    min-height:56px;
    display:block!important;
    word-break:break-word;
}
.home-prod-title a {
    color:#0f172a;
    text-decoration:none;
}
.home-prod-title a:hover {color:#ea580c}
.home-prod-bottom {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding-top:10px;
    border-top:1px solid #f1f5f9;
    margin-top:auto;
}
.home-prod-price {
    color:#ea580c;
    font-size:1.15rem;
    font-weight:800;
}
@media(max-width:992px) {
    .about-gl-grid {grid-template-columns:1fr}
}
@media(max-width:600px) {
    .about-gl-title {font-size:2rem}
    .about-feature-cards {grid-template-columns:1fr}
}

/* VNN Travel: mountain background embedded directly into this PHP file. */
.home-scenic {
  position:relative;
  isolation:isolate;
  background-color:#eaf3ee;
  background-image: url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%221600%22%20height%3D%22980%22%20viewBox%3D%220%200%201600%20980%22%20preserveAspectRatio%3D%22none%22%3E%0A%3Cdefs%3E%0A%3ClinearGradient%20id%3D%22sky%22%20x2%3D%220%22%20y2%3D%221%22%3E%3Cstop%20stop-color%3D%22%23edf7f3%22%2F%3E%3Cstop%20offset%3D%22.57%22%20stop-color%3D%22%23e4f1eb%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23eef7ed%22%2F%3E%3C%2FlinearGradient%3E%0A%3ClinearGradient%20id%3D%22hill%22%20x2%3D%220%22%20y2%3D%221%22%3E%3Cstop%20stop-color%3D%22%238db7a2%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23bad6bc%22%2F%3E%3C%2FlinearGradient%3E%0A%3C%2Fdefs%3E%0A%3Crect%20width%3D%221600%22%20height%3D%22980%22%20fill%3D%22url%28%23sky%29%22%2F%3E%0A%3C%21--%20Snowy%20ridge%20peaks%20with%20crisp%20contours%20--%3E%0A%3Cpath%20d%3D%22M0%20230%20L120%20176%20207%20224%20322%2091%20424%20209%20531%20126%20649%20230%20777%20130%20913%20226%201069%2086%201170%20219%201285%20129%201397%20212%201522%2098%201600%20170%201600%20390%200%20390Z%22%20fill%3D%22%23a9c9cc%22%20opacity%3D%22.74%22%2F%3E%0A%3Cpath%20d%3D%22M245%20166%20L322%2091%20390%20170%20354%20155%20339%20176%20321%20136%20298%20174%20285%20156Z%20M1008%20157%20L1069%2086%201138%20169%201098%20153%201080%20169%201058%20130%201035%20163Z%20M1472%20155%20L1522%2098%201586%20171%201547%20159%201529%20174%201514%20139%201497%20162Z%22%20fill%3D%22%23eff7f2%22%20opacity%3D%22.87%22%2F%3E%0A%3Cpath%20d%3D%22M0%20297%20L121%20219%20273%20309%20422%20202%20573%20310%20724%20189%20860%20282%20987%20201%201111%20309%201248%20211%201420%20300%201600%20205%201600%20530%200%20530Z%22%20fill%3D%22%2381aaae%22%20opacity%3D%22.72%22%2F%3E%0A%3Cpath%20d%3D%22M0%20355%20Q180%20236%20335%20339%20Q510%20256%20658%20337%20Q840%20250%201020%20340%20Q1203%20251%201354%20329%20Q1479%20280%201600%20342%20L1600%20640%200%20640Z%22%20fill%3D%22%23679985%22%20opacity%3D%22.62%22%2F%3E%0A%3Cpath%20d%3D%22M0%20425%20Q164%20310%20338%20395%20Q523%20324%20708%20410%20Q886%20310%201051%20396%20Q1221%20317%201391%20399%20Q1510%20360%201600%20399%20L1600%20785%200%20785Z%22%20fill%3D%22url%28%23hill%29%22%20opacity%3D%22.90%22%2F%3E%0A%3Cpath%20d%3D%22M0%20613%20Q228%20469%20443%20583%20Q628%20498%20791%20585%20Q1024%20478%201254%20565%20Q1440%20478%201600%20552%20L1600%20980%200%20980Z%22%20fill%3D%22%2382ab8b%22%20opacity%3D%22.47%22%2F%3E%0A%3Cpath%20d%3D%22M57%20348%20L37%20400%20H47%20L17%20452%20H97%20L67%20400%20H77%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M57%20456%20V506%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M114%20407%20L94%20459%20H104%20L74%20511%20H154%20L124%20459%20H134%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M114%20515%20V565%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M302%20642%20L295%20662%20H299%20L287%20681%20H317%20L305%20662%20H309%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M302%20683%20V710%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M111%20485%20L96%20527%20H104%20L80%20568%20H142%20L118%20527%20H126%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M111%20571%20V613%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M287%20440%20L268%20491%20H278%20L248%20542%20H326%20L296%20491%20H306%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M287%20546%20V595%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M112%20584%20L95%20629%20H104%20L77%20674%20H147%20L120%20629%20H129%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M112%20678%20V723%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M81%20733%20L67%20771%20H74%20L52%20808%20H110%20L88%20771%20H95%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M81%20811%20V851%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M79%20443%20L59%20496%20H69%20L38%20549%20H120%20L89%20496%20H99%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M79%20554%20V604%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M47%20612%20L39%20635%20H43%20L30%20657%20H64%20L51%20635%20H55%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M47%20659%20V688%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M309%20462%20L288%20518%20H299%20L267%20573%20H351%20L319%20518%20H330%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M309%20577%20V629%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M274%20445%20L261%20481%20H268%20L247%20516%20H301%20L280%20481%20H287%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M274%20519%20V557%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M185%20701%20L175%20728%20H180%20L165%20755%20H205%20L190%20728%20H195%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M185%20757%20V789%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M116%20788%20L104%20820%20H110%20L92%20851%20H140%20L122%20820%20H128%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M116%20854%20V889%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M51%20589%20L40%20620%20H46%20L28%20651%20H74%20L56%20620%20H62%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M51%20653%20V688%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M83%20574%20L70%20609%20H77%20L57%20643%20H109%20L89%20609%20H96%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M83%20646%20V683%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M36%20660%20L18%20708%20H27%20L0%20755%20H72%20L45%20708%20H54%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M36%20759%20V805%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M83%20618%20L70%20654%20H77%20L56%20689%20H110%20L89%20654%20H96%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M83%20692%20V730%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M166%20763%20L146%20817%20H156%20L125%20870%20H207%20L176%20817%20H186%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M166%20874%20V925%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M16%20802%20L4%20835%20H10%20L-9%20867%20H41%20L22%20835%20H28%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M16%20870%20V906%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M33%20466%20L16%20510%20H25%20L-1%20554%20H67%20L41%20510%20H50%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M33%20558%20V602%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M255%20519%20L233%20578%20H244%20L210%20637%20H300%20L266%20578%20H277%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M255%20642%20V696%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M135%20470%20L124%20500%20H130%20L113%20529%20H157%20L140%20500%20H146%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M135%20531%20V565%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M299%20535%20L277%20595%20H288%20L254%20654%20H344%20L310%20595%20H321%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M299%20658%20V713%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M185%20525%20L176%20550%20H181%20L167%20574%20H203%20L189%20550%20H194%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M185%20576%20V606%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M46%20810%20L39%20831%20H43%20L31%20851%20H61%20L49%20831%20H53%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M46%20852%20V880%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M81%20748%20L62%20798%20H72%20L43%20847%20H119%20L90%20798%20H100%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M81%20851%20V899%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M197%20549%20L180%20595%20H189%20L162%20640%20H232%20L205%20595%20H214%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M197%20644%20V689%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M283%20869%20L276%20888%20H280%20L269%20906%20H297%20L286%20888%20H290%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M283%20908%20V934%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M174%20450%20L162%20482%20H168%20L150%20513%20H198%20L180%20482%20H186%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M174%20516%20V551%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M232%20339%20L213%20391%20H223%20L193%20442%20H271%20L241%20391%20H251%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M232%20446%20V495%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M259%20884%20L251%20907%20H255%20L242%20930%20H276%20L263%20907%20H267%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M259%20932%20V961%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M78%20524%20L58%20577%20H68%20L37%20630%20H119%20L88%20577%20H98%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M78%20635%20V685%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M306%20533%20L291%20574%20H299%20L275%20614%20H337%20L313%20574%20H321%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M306%20617%20V659%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M185%20773%20L164%20830%20H175%20L142%20886%20H228%20L195%20830%20H206%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M185%20890%20V943%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M29%20441%20L7%20500%20H18%20L-16%20558%20H74%20L40%20500%20H51%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M29%20563%20V617%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M43%20742%20L28%20783%20H36%20L12%20823%20H74%20L50%20783%20H58%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M43%20826%20V868%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M65%20707%20L50%20747%20H58%20L35%20787%20H95%20L72%20747%20H80%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M65%20790%20V831%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M135%20589%20L113%20647%20H124%20L90%20705%20H180%20L146%20647%20H157%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M135%20710%20V764%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M108%20836%20L92%20879%20H100%20L75%20922%20H141%20L116%20879%20H124%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M108%20926%20V969%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M204%20690%20L186%20738%20H195%20L167%20786%20H241%20L213%20738%20H222%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M204%20790%20V837%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M264%20646%20L255%20670%20H260%20L246%20693%20H282%20L268%20670%20H273%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M264%20695%20V725%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M32%20601%20L25%20620%20H29%20L18%20639%20H46%20L35%20620%20H39%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M32%20640%20V667%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M301%20542%20L294%20560%20H298%20L287%20578%20H315%20L304%20560%20H308%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M301%20580%20V606%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1397%20460%20L1390%20480%20H1394%20L1382%20499%20H1412%20L1400%20480%20H1404%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1397%20501%20V528%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1543%20516%20L1532%20547%20H1538%20L1520%20578%20H1566%20L1548%20547%20H1554%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1543%20580%20V615%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1556%20405%20L1537%20457%20H1547%20L1517%20508%20H1595%20L1565%20457%20H1575%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1556%20512%20V561%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1404%20771%20L1389%20811%20H1397%20L1374%20851%20H1434%20L1411%20811%20H1419%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1404%20854%20V895%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1328%20395%20L1310%20444%20H1319%20L1291%20492%20H1365%20L1337%20444%20H1346%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1328%20496%20V543%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1496%20581%20L1481%20621%20H1489%20L1466%20660%20H1526%20L1503%20621%20H1511%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1496%20663%20V704%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1311%20543%20L1291%20595%20H1301%20L1271%20646%20H1351%20L1321%20595%20H1331%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1311%20651%20V700%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1407%20504%20L1397%20531%20H1402%20L1387%20558%20H1427%20L1412%20531%20H1417%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1407%20560%20V592%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1351%20623%20L1341%20650%20H1346%20L1331%20676%20H1371%20L1356%20650%20H1361%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1351%20678%20V710%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1407%20868%20L1399%20890%20H1403%20L1391%20911%20H1423%20L1411%20890%20H1415%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1407%20913%20V941%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1305%20694%20L1289%20737%20H1297%20L1272%20780%20H1338%20L1313%20737%20H1321%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1305%20784%20V827%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1401%20463%20L1387%20500%20H1394%20L1373%20537%20H1429%20L1408%20500%20H1415%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1401%20540%20V579%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1389%20821%20L1375%20858%20H1382%20L1361%20894%20H1417%20L1396%20858%20H1403%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1389%20897%20V936%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1474%20382%20L1461%20418%20H1468%20L1447%20454%20H1501%20L1480%20418%20H1487%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1474%20457%20V495%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1426%20557%20L1407%20608%20H1417%20L1388%20658%20H1464%20L1435%20608%20H1445%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1426%20662%20V710%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1359%20490%20L1347%20522%20H1353%20L1335%20553%20H1383%20L1365%20522%20H1371%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1359%20556%20V591%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1576%20737%20L1560%20780%20H1568%20L1543%20823%20H1609%20L1584%20780%20H1592%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1576%20827%20V870%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1309%20381%20L1292%20426%20H1301%20L1275%20471%20H1343%20L1317%20426%20H1326%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1309%20474%20V519%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1309%20856%20L1293%20898%20H1301%20L1277%20939%20H1341%20L1317%20898%20H1325%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1309%20943%20V985%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1315%20726%20L1307%20747%20H1311%20L1299%20768%20H1331%20L1319%20747%20H1323%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1315%20770%20V798%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1341%20799%20L1319%20858%20H1330%20L1296%20917%20H1386%20L1352%20858%20H1363%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1341%20922%20V976%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1576%20729%20L1569%20749%20H1573%20L1561%20769%20H1591%20L1579%20749%20H1583%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1576%20771%20V798%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1494%20692%20L1477%20737%20H1486%20L1460%20782%20H1528%20L1502%20737%20H1511%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1494%20785%20V830%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1413%20449%20L1395%20498%20H1404%20L1376%20547%20H1450%20L1422%20498%20H1431%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1413%20551%20V598%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1415%20616%20L1406%20640%20H1411%20L1397%20664%20H1433%20L1419%20640%20H1424%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1415%20666%20V696%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1441%20809%20L1421%20862%20H1431%20L1401%20915%20H1481%20L1451%20862%20H1461%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1441%20919%20V969%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1514%20676%20L1497%20720%20H1506%20L1480%20764%20H1548%20L1522%20720%20H1531%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1514%20768%20V812%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1555%20475%20L1540%20517%20H1548%20L1524%20558%20H1586%20L1562%20517%20H1570%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1555%20561%20V603%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1458%20873%20L1450%20894%20H1454%20L1442%20915%20H1474%20L1462%20894%20H1466%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1458%20917%20V945%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1425%20454%20L1411%20493%20H1418%20L1396%20531%20H1454%20L1432%20493%20H1439%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1425%20534%20V574%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1593%20760%20L1575%20808%20H1584%20L1556%20856%20H1630%20L1602%20808%20H1611%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1593%20860%20V907%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.55%22%2F%3E%3Cpath%20d%3D%22M1563%20499%20L1545%20548%20H1554%20L1526%20596%20H1600%20L1572%20548%20H1581%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1563%20600%20V647%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1415%20376%20L1393%20435%20H1404%20L1370%20494%20H1460%20L1426%20435%20H1437%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1415%20499%20V553%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1419%20497%20L1402%20543%20H1411%20L1384%20589%20H1454%20L1427%20543%20H1436%20Z%22%20fill%3D%22%234b8068%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1419%20593%20V638%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1384%20700%20L1366%20748%20H1375%20L1348%20795%20H1420%20L1393%20748%20H1402%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1384%20799%20V845%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1408%20785%20L1386%20842%20H1397%20L1364%20899%20H1452%20L1419%20842%20H1430%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1408%20904%20V957%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1496%20819%20L1485%20850%20H1491%20L1473%20881%20H1519%20L1501%20850%20H1507%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1496%20883%20V918%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.27%22%2F%3E%3Cpath%20d%3D%22M1450%20808%20L1441%20832%20H1446%20L1432%20856%20H1468%20L1454%20832%20H1459%20Z%22%20fill%3D%22%23417a68%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1450%20858%20V888%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1506%20622%20L1487%20673%20H1497%20L1467%20723%20H1545%20L1515%20673%20H1525%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1506%20727%20V776%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.48%22%2F%3E%3Cpath%20d%3D%22M1337%20355%20L1315%20414%20H1326%20L1292%20473%20H1382%20L1348%20414%20H1359%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1337%20478%20V532%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1469%20658%20L1453%20702%20H1461%20L1436%20745%20H1502%20L1477%20702%20H1485%20Z%22%20fill%3D%22%23668f69%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1469%20748%20V792%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1345%20412%20L1333%20445%20H1339%20L1321%20477%20H1369%20L1351%20445%20H1357%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1345%20479%20V515%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.41%22%2F%3E%3Cpath%20d%3D%22M1463%20450%20L1444%20500%20H1454%20L1425%20549%20H1501%20L1472%20500%20H1482%20Z%22%20fill%3D%22%233b785f%22%20opacity%3D%220.34%22%2F%3E%3Cpath%20d%3D%22M1463%20553%20V601%22%20stroke%3D%22%233e7358%22%20stroke-width%3D%223%22%20opacity%3D%220.34%22%2F%3E%3Crect%20x%3D%22370%22%20y%3D%22130%22%20width%3D%22860%22%20height%3D%22650%22%20fill%3D%22%23f2f8f3%22%20opacity%3D%22.22%22%2F%3E%3C%2Fsvg%3E");
  background-repeat:repeat-y;
  background-position:center top;
  background-size:100% 940px;
  padding-bottom:2px;
}
.home-scenic .about-gl-section { background:transparent !important; }
.home-scenic .about-f-card { background:rgba(255,255,255,.94); border:1px solid #dce9e3; box-shadow:0 7px 22px rgba(30,70,53,.10); }
.home-scenic .tour-card,.home-scenic .home-prod-card { background:#fff; box-shadow:0 7px 22px rgba(23,60,48,.12); }
.home-scenic .section-header,.home-scenic .about-gl-grid > div:first-child { text-shadow:0 1px 3px rgba(255,255,255,.55); }
@media(max-width:768px) { .home-scenic { background-size:auto 850px; } .home-scenic .about-f-card { background:rgba(255,255,255,.97); } }

</style>

<!-- BANNER TRANG CHU -->
<section class="hero">
    <div class="hero-content">
        <h1>Khám phá - Chinh phục - Trải nghiệm</h1>
        <p>
            Hành trình đặt chân lên những đỉnh núi huyền thoại cùng VNN Travel.
        </p>
        <a
            href="<?= BASE_URL ?>tours/tour.php"
            class="btn btn-accent"
            style="padding:14px 34px;font-size:1.1rem;border-radius:30px"
        >
            <i class="fa-solid fa-compass"></i>
            KHÁM PHÁ TẤT CẢ TOUR
        </a>
    </div>
</section>

<div class="home-scenic">
<!-- VE CHUNG TOI -->
<section class="about-gl-section">
    <div class="container">
        <div class="about-gl-grid">

            <div>
                <span class="about-gl-badge">VỀ CHÚNG TÔI</span>

                <h2 class="about-gl-title">
                    Khám Phá <span>& Chinh Phục</span>
                    Cùng VNN Travel
                </h2>

                <p class="about-gl-desc">
                    <strong>VNN Travel</strong> không chỉ mang đến
                    các hành trình trekking chinh phục thiên nhiên
                    hùng vĩ với độ an toàn cao nhất, mà còn trao
                    gửi cho bạn những chuyến đi
                    <strong>chạm đến trái tim</strong>
                    và bứt phá mọi giới hạn của bản thân.
                </p>

                <div style="
                    background:#fff7ed;
                    border-left:4px solid #f97316;
                    padding:18px 22px;
                    border-radius:8px;
                    margin-bottom:25px;
                ">
                    <p style="
                        margin:0;
                        font-size:.95rem;
                        color:#7c2d12;
                        line-height:1.7;
                    ">
                        "Mỗi bước chân trên cung đường đại ngàn
                        cùng VNN Travel là một trải nghiệm rèn
                        luyện ý chí, gắn kết đồng đội và tận hưởng
                        vẻ đẹp hoang sơ đích thực của đất trời Việt Nam."
                    </p>
                </div>

                <a
                    href="<?= BASE_URL ?>tours/tour.php"
                    style="
                        color:#ea580c;
                        font-weight:700;
                        text-decoration:none;
                        font-size:1rem;
                    "
                >
                    Khám phá các hành trình trekking &rarr;
                </a>
            </div>

            <div class="about-feature-cards">

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#ffedd5;color:#ea580c">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4>Hướng dẫn viên tận tâm</h4>
                    <p>
                        Năng động, nhiệt tình, am hiểu địa hình
                        rừng núi, mang đến trải nghiệm thân thiện
                        và an toàn tuyệt đối suốt hành trình.
                    </p>
                </div>

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#fee2e2;color:#dc2626">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                    <h4>Ẩm thực phong phú</h4>
                    <p>
                        Thực đơn nóng sốt đa dạng với tiệc BBQ,
                        lẩu gà đồi thơm lừng giữa mây trời,
                        đầy đủ dinh dưỡng và hỗ trợ ăn chay theo yêu cầu.
                    </p>
                </div>

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#dbeafe;color:#0284c7">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h4>An toàn & Chuẩn quốc tế</h4>
                    <p>
                        Túi cứu thương y tế dã ngoại, gậy leo núi
                        trợ lực, túi ngủ ấm áp chịu âm độ và bảo hiểm
                        du lịch lên đến 100.000.000đ/vụ.
                    </p>
                </div>

                <div class="about-f-card">
                    <div class="about-f-icon"
                         style="background:#fef3c7;color:#d97706">
                        <i class="fa-solid fa-fire"></i>
                    </div>
                    <h4>Năng lượng tích cực</h4>
                    <p>
                        Cộng đồng trẻ, yêu khám phá và đam mê leo núi,
                        luôn san sẻ từng ngụm nước, tạo nên không khí
                        sôi nổi và tràn đầy niềm vui.
                    </p>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- TOUR TREKKING NOI BAT -->
<section class="container section" style="padding-top:20px">

    <div class="section-header">
        <span class="section-subtitle">
            Chinh Phục Đỉnh Cao
        </span>
        <h2 class="section-title">
            Các Cung Đường Trekking Nổi Bật
        </h2>
    </div>

    <div class="grid grid-3">

        <?php if (!empty($featured_tours)): ?>

            <?php foreach ($featured_tours as $t): ?>

                <div class="tour-card">

                    <div class="card-img-wrap">

                        <img
                            src="<?= home_h($t['image']) ?>"
                            alt="<?= home_h($t['title']) ?>"
                            loading="lazy"
                        >

                        <span class="tour-badge">
                            <?= home_h($t['difficulty']) ?>
                        </span>

                        <!-- DA BO NHAN CON BAO NHIEU CHO -->

                    </div>

                    <div class="card-body">

                        <span style="
                            font-size:.82rem;
                            color:var(--primary);
                            font-weight:bold;
                            margin-bottom:4px;
                            display:inline-block;
                        ">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= home_h($t['loc_name']) ?>
                        </span>

                        <h3 style="margin:4px 0 10px;font-size:1.15rem">

                            <a
                                href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= (int)$t['id'] ?>"
                                style="color:#0f172a;text-decoration:none"
                            >
                                <?= home_h($t['title']) ?>
                            </a>

                        </h3>

                        <div class="tour-meta" style="
                            display:flex;
                            gap:15px;
                            font-size:.85rem;
                            color:#64748b;
                            margin-bottom:12px;
                        ">

                            <span>
                                <i class="fa-regular fa-clock"></i>
                                <?= home_h($t['duration']) ?>
                            </span>

                            <span>
                                <i class="fa-solid fa-mountain"></i>
                                <?= home_h($t['altitude']) ?>
                            </span>

                        </div>

                        <div class="tour-footer" style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-top:auto;
                            padding-top:10px;
                            border-top:1px solid #f1f5f9;
                        ">

                            <span class="price-tag" style="
                                color:#ea580c;
                                font-weight:800;
                                font-size:1.1rem;
                            ">
                                <?= format_price($t['price']) ?>
                            </span>

                            <a
                                href="<?= BASE_URL ?>tours/tour-detail.php?id=<?= (int)$t['id'] ?>"
                                class="btn btn-primary btn-sm"
                            >
                                Xem chi tiết
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div style="
                grid-column:span 3;
                text-align:center;
                padding:50px 0;
                color:#64748b;
            ">
                Chưa có dữ liệu tour nào trong hệ thống.
            </div>

        <?php endif; ?>

    </div>

    <div style="text-align:center;margin-top:35px">

        <a
            href="<?= BASE_URL ?>tours/tour.php"
            class="btn btn-outline"
            style="
                padding:12px 35px;
                border-radius:30px;
                font-weight:bold;
            "
        >
            Xem tất cả các chuyến tour trekking &rarr;
        </a>

    </div>

</section>

<!-- SAN PHAM BAN CHAY -->
<section class="container section" style="padding-top:0">

    <div class="section-header">

        <span class="section-subtitle">
            Trang Bị Chuyên Nghiệp
        </span>

        <h2 class="section-title">
            Dụng Cụ Leo Núi Bán Chạy
        </h2>

    </div>

    <div class="grid grid-4">

        <?php foreach ($featured_products as $p): ?>

            <div class="home-prod-card">

                <div>

                    <div class="home-prod-thumb">

                        <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= (int)$p['id'] ?>">

                            <img
                                src="<?= home_h($p['image']) ?>"
                                alt="<?= home_h($p['name']) ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=500';"
                            >

                        </a>

                    </div>

                    <span class="home-prod-brand">
                        <?= home_h($p['brand'] ?? '') ?>
                    </span>

                    <h4 class="home-prod-title">

                        <a href="<?= BASE_URL ?>shop/product-detail.php?id=<?= (int)$p['id'] ?>">
                            <?= home_h($p['name']) ?>
                        </a>

                    </h4>

                </div>

                <div class="home-prod-bottom">

                    <span class="home-prod-price">
                        <?= format_price($p['price']) ?>
                    </span>

                    <a
                        href="<?= BASE_URL ?>shop/product-detail.php?id=<?= (int)$p['id'] ?>"
                        class="btn btn-primary btn-sm"
                        style="border-radius:8px"
                    >
                        <i class="fa-solid fa-cart-plus"></i>
                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <div style="text-align:center;margin-top:35px">
        <a
            href="<?= BASE_URL ?>shop/shop.php"
            class="btn btn-outline"
            style="padding:12px 35px;border-radius:30px;font-weight:bold;"
        >
            Xem tất cả các sản phẩm &rarr;
        </a>
    </div>

</section>

</div><!-- /.home-scenic -->

<?php
require_once __DIR__ . '/includes/footer.php';
?>
