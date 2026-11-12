/* ---------- 전역 변수 ---------- */
const members_sponsor  = window.LINE_DATA?.sponsor || [];
const members_referrer = window.LINE_DATA?.referrer || [];
let members = members_sponsor;
let lineType = 'sponsor';
let historyStack = [], currentRoot = null;
let orderActive = false;
const rootId = members.length ? members[0].mlm_id : null;

const container = document.getElementById('org-chart-container');
const orgChart  = document.getElementById('org-chart');
const svg       = document.getElementById('line-svg');

/* ---------- 유틸 ---------- */
function updateNavButtons() {
  const backBtn = document.getElementById('back-btn');
  if (currentRoot) backBtn.classList.remove('hidden');
  else backBtn.classList.add('hidden');
}

function safeLoadBonus(id) {
  if (!id) return;
  try {
    const pf = window.parent?.pointFrame || window.top?.frames['pointFrame'];
    if (pf && pf.location) {
      pf.location.href = '/office/bonus/list/' + id;
      console.log('[MiniCI] bonus 이동:', id);
    }
  } catch (e) {
    console.error('[MiniCI] pointFrame 접근 오류:', e);
  }
}

function switchLineType(type) {
  lineType = type;
  members = (type === 'referrer') ? members_referrer : members_sponsor;
}

/* ---------- 트리 ---------- */
function buildTree(data, parentId = null) {
  const key = lineType === 'referrer' ? 'referrer_id' : 'sponsor_id';
  const isRootLevel = parentId === null;
  return data
    .filter(m => isRootLevel ? !data.some(d => d.mlm_id === m[key]) : m[key] === parentId)
    .sort((a,b)=>new Date(a.order_created_at)-new Date(b.order_created_at))
    .map(m => ({ ...m, children: buildTree(data, m.mlm_id) }));
}

/* ---------- 렌더 ---------- */
function renderNode(node, parentEl) {
  const wrap = document.createElement('div');
  wrap.className = 'org-chart-node';

  const card = document.createElement('div');
  card.className = 'node-card';
  card.dataset.id = node.mlm_id;

  const isPending = node.order_status === 'pending';
  const color = isPending ? '#f87171' : 'inherit';

  card.innerHTML = `
    <div class="node-name" style="color:${color}">${node.name}</div>
    <div class="node-id" style="color:${color}">${node.display_mlm_id}</div>
  `;

  const thisRoot = currentRoot || rootId;
  if (node.mlm_id !== thisRoot)
    card.addEventListener('click', () => navigateTo(node.mlm_id));
  else
    card.style.cursor = 'default';

  wrap.appendChild(card);

  if (node.children?.length) {
    const cc = document.createElement('div');
    cc.className = 'children-container';
    node.children.forEach(ch => renderNode(ch, cc));
    wrap.appendChild(cc);
  }

  parentEl.appendChild(wrap);
}

function renderChart(rootMlmId = null, afterCb = null) {
  orgChart.textContent = '';
  let filteredMembers = members;

  if (lineType === 'referrer') {
    const startId = rootMlmId || members[0]?.mlm_id;
    const reachable = new Set();
    collectReachable(startId, reachable);
    filteredMembers = members.filter(m => reachable.has(m.mlm_id));
    if (filteredMembers.length === 0 && startId) {
      const self = members.find(m => m.mlm_id === startId) || members_sponsor.find(m => m.mlm_id === startId);
      if (self) filteredMembers = [self];
    }
  }

  const roots = rootMlmId
    ? filteredMembers.filter(m => m.mlm_id === rootMlmId)
        .map(m => ({ ...m, children: buildTree(filteredMembers, m.mlm_id) }))
    : buildTree(filteredMembers, null);

  if (roots.length === 0 && rootMlmId) {
    const self = members.find(m => m.mlm_id === rootMlmId) || members_sponsor.find(m => m.mlm_id === rootMlmId);
    if (self) roots.push({ ...self, children: [] });
  }

  roots.forEach(r => renderNode(r, orgChart));

  requestAnimationFrame(() => {
    syncSvg();
     orgChart.style.padding = '0 50px 150px'; // 위 0, 좌우 200px, 아래 150px
    if (typeof afterCb === 'function') afterCb();
  });
}

/* ---------- 연결된 노드 ---------- */
function collectReachable(startId, reachableSet) {
  if (!startId) return;
  const key = lineType === 'referrer' ? 'referrer_id' : 'sponsor_id';
  const queue = [startId];
  while (queue.length) {
    const id = queue.shift();
    if (reachableSet.has(id)) continue;
    reachableSet.add(id);
    members.filter(m => m[key] === id).forEach(child => queue.push(child.mlm_id));
  }
}

/* ---------- SVG ---------- */
function syncSvg() {
  const rect = container.getBoundingClientRect();
  const w = container.scrollWidth, h = container.scrollHeight;
  svg.setAttribute('width', w);
  svg.setAttribute('height', h);
  svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
  svg.innerHTML = '';

  const key = lineType === 'referrer' ? 'referrer_id' : 'sponsor_id';
  const pos = {};
  document.querySelectorAll('.node-card').forEach(el => {
    const r = el.getBoundingClientRect();
    pos[el.dataset.id] = {
      x:(r.left-rect.left)+container.scrollLeft+(el.offsetWidth/2),
      top:(r.top-rect.top)+container.scrollTop,
      bottom:(r.bottom-rect.top)+container.scrollTop
    };
  });

  members.forEach(m => {
    if (!m[key]) return;
    const p = pos[m[key]], c = pos[m.mlm_id];
    if (!p || !c) return;
    const midY = (p.bottom + c.top) / 2;
    const path = `M${p.x},${p.bottom} L${p.x},${midY} L${c.x},${midY} L${c.x},${c.top}`;
    const pathEl = document.createElementNS("http://www.w3.org/2000/svg", "path");
    pathEl.setAttribute("d", path);
    pathEl.setAttribute("stroke", "#4b5563");
    pathEl.setAttribute("stroke-width", "2");
    pathEl.setAttribute("fill", "none");
    svg.appendChild(pathEl);
  });
}

/* ---------- 중앙정렬 ---------- */
function centerOn(el) {
  const rect = el.getBoundingClientRect(), cont = container.getBoundingClientRect();
  const x = (rect.left - cont.left) + container.scrollLeft;
  const y = (rect.top - cont.top) + container.scrollTop;
  container.scrollTo({
    left: x - container.clientWidth / 2 + el.offsetWidth / 2,
    top:  y - container.clientHeight / 2 + el.offsetHeight / 2,
    behavior: 'auto'
  });
}

/* ---------- 이동 ---------- */
function navigateTo(id) {
  historyStack.push({ rootId: currentRoot, scrollLeft: container.scrollLeft, scrollTop: container.scrollTop });
  currentRoot = id;
  renderChart(id, () => {
    const el = document.querySelector(`.node-card[data-id="${id}"]`);
    if (el) centerOn(el);
    updateNavButtons();
    requestAnimationFrame(() => safeLoadBonus(id));
  });
}

/* ---------- 뒤로가기 ---------- */
document.getElementById('back-btn').addEventListener('click', () => {
  if (!historyStack.length) {
    currentRoot = null;
    renderChart(null, () => {
      const root = document.querySelector('.node-card');
      if (root) centerOn(root);
      updateNavButtons();
      requestAnimationFrame(() => {
        const targetId = root?.dataset.id || rootId;
        if (targetId) safeLoadBonus(targetId);
      });
    });
    return;
  }

  const prev = historyStack.pop();
  currentRoot = prev.rootId;
  renderChart(currentRoot, () => {
    container.scrollTo({ left: prev.scrollLeft, top: prev.scrollTop, behavior: 'auto' });
    updateNavButtons();
    requestAnimationFrame(() => {
      const targetId = currentRoot || rootId;
      if (targetId) safeLoadBonus(targetId);
    });
  });
});

/* ---------- 추천/후원 전환 ---------- */
document.getElementById('referrer-btn').addEventListener('click', () => {
  if (lineType === 'referrer') return;
  document.getElementById('referrer-btn').classList.add('active');
  document.getElementById('sponsor-btn').classList.remove('active');
  const targetId = currentRoot || null;
  switchLineType('referrer');
  renderChart(targetId, () => {
    const el = document.querySelector(`.node-card[data-id="${targetId}"]`) || document.querySelector('.node-card');
    if (el) centerOn(el);
    updateNavButtons();
    requestAnimationFrame(() => safeLoadBonus(currentRoot || rootId));
  });
});

document.getElementById('sponsor-btn').addEventListener('click', () => {
  if (lineType === 'sponsor') return;
  document.getElementById('sponsor-btn').classList.add('active');
  document.getElementById('referrer-btn').classList.remove('active');
  const targetId = currentRoot || null;
  switchLineType('sponsor');
  renderChart(targetId, () => {
    const el = document.querySelector(`.node-card[data-id="${targetId}"]`) || document.querySelector('.node-card');
    if (el) centerOn(el);
    updateNavButtons();
    requestAnimationFrame(() => safeLoadBonus(currentRoot || rootId));
  });
});

/* ---------- 등록순 뱃지 ---------- */
const sortBtn = document.getElementById('sort-btn');
sortBtn.addEventListener('click', () => {
  sortBtn.classList.add('animating');
  setTimeout(() => sortBtn.classList.remove('animating'), 800);
  if (orderActive) {
    document.querySelectorAll('.order-badge').forEach(b => b.remove());
    orderActive = false;
  } else {
    showBadgesSequentially();
    orderActive = true;
  }
});

function showBadgesSequentially() {
  document.querySelectorAll('.order-badge').forEach(b => b.remove());
  const cards = Array.from(document.querySelectorAll('.node-card'));
  const thisRoot = currentRoot || rootId;
  const rootCard = cards.find(card => card.dataset.id === thisRoot);
  if (rootCard) {
    const badge = document.createElement('div');
    badge.className = 'order-badge';
    badge.textContent = 'root';
    rootCard.appendChild(badge);
  }
  const cardsWithDate = cards
    .filter(card => card.dataset.id !== thisRoot)
    .map(card => {
      const id = card.dataset.id;
      const member = members.find(m => m.mlm_id === id);
      const date = member ? new Date(member.order_created_at) : new Date(0);
      return { card, date };
    })
    .sort((a, b) => a.date - b.date);
  cardsWithDate.forEach((item, i) => {
    const badge = document.createElement('div');
    badge.className = 'order-badge';
    badge.textContent = i + 1;
    badge.style.animationDelay = `${(i + 1) * 0.12}s`;
    item.card.appendChild(badge);
  });
}

/* ---------- 초기 로드 ---------- */
/* ---------- 드래그 스크롤(패닝) 기능 ---------- */
let isDragging = false;
let startX, startY;
let scrollLeftStart, scrollTopStart;

container.addEventListener('mousedown', (e) => {
  // 마우스 왼쪽 버튼 클릭 시에만 작동
  if (e.button !== 0) return;

  isDragging = true;
  // pageX: 문서 전체 기준 X좌표
  startX = e.pageX - container.offsetLeft;
  startY = e.pageY - container.offsetTop;
  scrollLeftStart = container.scrollLeft;
  scrollTopStart = container.scrollTop;

  container.style.cursor = 'grabbing'; // '잡는 중' 커서
});

container.addEventListener('mouseleave', () => {
  if (!isDragging) return;
  isDragging = false;
  container.style.cursor = 'grab'; // '잡을 수 있음' 커서
});

container.addEventListener('mouseup', () => {
  if (!isDragging) return;
  isDragging = false;
  container.style.cursor = 'grab'; // '잡을 수 있음' 커서
});

container.addEventListener('mousemove', (e) => {
  if (!isDragging) return;
  e.preventDefault(); // 드래그 중 텍스트 선택 등 기본 동작 방지

  const x = e.pageX - container.offsetLeft;
  const y = e.pageY - container.offsetTop;

  // 마우스가 처음 클릭한 위치에서 얼마나 움직였는지 계산
  const walkX = (x - startX);
  const walkY = (y - startY);

  // 스크롤 위치 업데이트 (시작 스크롤 위치에서 이동한 거리만큼 빼기)
  container.scrollLeft = scrollLeftStart - walkX;
  container.scrollTop = scrollTopStart - walkY;
});

window.addEventListener('load', () => {
  renderChart(null, () => {
    const root = document.querySelector('.node-card');
    if (root) centerOn(root);
    updateNavButtons();
    requestAnimationFrame(() => safeLoadBonus(currentRoot || rootId));
  });
});

/* ---------- 이벤트 ---------- */
let _resizeTid = null;
window.addEventListener('resize', () => {
  if (_resizeTid) cancelAnimationFrame(_resizeTid);
  _resizeTid = requestAnimationFrame(syncSvg);
});
container.addEventListener('scroll', () => requestAnimationFrame(syncSvg));
