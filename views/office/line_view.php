<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>매트릭스</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
html,body{height:100%;margin:0;background:#f9fafb;overflow:hidden;font-family:'Noto Sans KR',sans-serif;}
.org-chart-container{
  position:relative;width:100%;height:100%;
  overflow:auto;cursor:grab;padding:120px 0 0px;
}
.org-chart-container.active{cursor:grabbing;}
#line-svg{position:absolute;top:0;left:0;z-index:0;pointer-events:none;}
#org-chart{
  position:relative;z-index:1;
  display:flex;flex-direction:column;align-items:center;width:max-content;margin:auto;
}

/* 노드 카드 */
.node-card{
  width:130px;height:90px;background:#fff;border:1px solid #ddd;border-radius:0.6rem;
  display:flex;flex-direction:column;justify-content:center;align-items:center;
  font-size:0.85rem;color:#333;box-shadow:0 3px 6px rgba(0,0,0,0.08);
  transition:transform .15s ease;user-select:none;position:relative;
}
.node-card:hover{transform:scale(1.07);}
.node-name{font-weight:600;font-size:.95rem;}
.node-id{font-size:.8rem;color:#666;margin-top:4px;}

/* 등록순 뱃지 */
.order-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #1976d2;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  border-radius: 999px;
  padding: 6px 10px;
  line-height: 1;
  box-shadow: 0 3px 6px rgba(0,0,0,0.3);
  opacity: 0;
  transform: scale(0.5);
  animation: fadeInBadge 0.7s forwards cubic-bezier(0.22, 1.0, 0.36, 1.0);
}
@keyframes fadeInBadge {
  0%   { opacity: 0; transform: scale(0.5); }
  70%  { opacity: 1; transform: scale(1.1); }
  100% { opacity: 1; transform: scale(1); }
}

.menu-btn.hidden {display: none !important;}

/* 트리 구조 */
.org-chart-node{display:flex;flex-direction:column;align-items:center;position:relative;}
.children-container{
  display:flex;justify-content:center;gap:40px;margin-top:1.2rem;position:relative;
  padding-top:24px;
}

/* 상단 메뉴 */
.top-menu-bar{
  position:fixed;top:1rem;left:50%;transform:translateX(-50%);
  display:flex;align-items:center;gap:0.6rem;background:#fff;padding:0.5rem 1rem;border-radius:2.5rem;
  box-shadow:0 8px 25px rgba(0,0,0,0.12);z-index:1000;
}
.menu-btn{width:48px;height:48px;border:none;background:transparent;border-radius:50%;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;color:#444;transition:all .2s ease;}
.menu-btn:hover{background:#f3f4f6;}
.menu-btn.active{background:#1976d2;color:#fff;}

/* 등록순 아이콘 회전 효과 */
@keyframes rotatePulse {
  0% { transform: rotate(0deg) scale(1); }
  50% { transform: rotate(360deg) scale(1.3); }
  100% { transform: rotate(720deg) scale(1); }
}
.menu-btn.animating i {animation: rotatePulse 0.8s ease-in-out;}
</style>
</head>
<body>

<div class="top-menu-bar">
  <button id="back-btn" class="menu-btn" title="뒤로가기"><i class="fa-solid fa-arrow-left"></i></button>
  <button id="referrer-btn" class="menu-btn" title="추천기반"><i class="fa-solid fa-share-nodes"></i></button>
  <button id="sponsor-btn" class="menu-btn active" title="후원기반"><i class="fa-solid fa-sitemap"></i></button>
  <button id="sort-btn" class="menu-btn" title="등록순"><i class="fa-solid fa-rotate-left"></i></button>
</div>

<div class="org-chart-container" id="org-chart-container">
  <svg id="line-svg"></svg>
  <div id="org-chart"></div>
</div>

<script>
window.LINE_DATA = {
  sponsor: <?= $line_json_sponsor ?: '[]'; ?>,
  referrer: <?= $line_json_referrer ?: '[]'; ?>
};
</script>

<?=load_asset('js/tree.js')?>


</body>
</html>
