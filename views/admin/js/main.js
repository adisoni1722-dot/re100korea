/**
 * Sidebar & Theme Script (v2025.11.03 - Safe Edition)
 * -------------------------------------------------------------
 * • iframe(모달) 내부에서는 실행하지 않음
 * • 요소 존재 여부 검사 (sidebar, toggle 등)
 * • resize 대응 / 다크모드 / sidebar-active 기능 포함
 * -------------------------------------------------------------
 */
document.addEventListener('DOMContentLoaded', function () {
  // ✅ 모달 iframe 내부에서는 실행 금지
  if (window.self !== window.top) return;

  const sidebar = document.querySelector('.sidebar');
  const mainContent = document.querySelector('.main-content');
  const toggleButton = document.getElementById('sidebar-toggle');
  const backdrop = document.querySelector('.backdrop');

  // ✅ 안전 검사: 주요 요소가 없으면 종료
  if (!sidebar || !mainContent || !toggleButton) return;

  // -------------------------------------------------------------
  // 사이드바 열기 / 닫기
  // -------------------------------------------------------------
  function closeSidebar() {
    sidebar.classList.add('collapsed');
    if (window.innerWidth > 768) {
      mainContent.classList.add('expanded');
    }
    backdrop?.classList.remove('active');
  }

  function openSidebar() {
    sidebar.classList.remove('collapsed');
    if (window.innerWidth > 768) {
      mainContent.classList.remove('expanded');
    } else {
      backdrop?.classList.add('active');
    }
  }

  // -------------------------------------------------------------
  // 토글 버튼 클릭
  // -------------------------------------------------------------
  toggleButton.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation(); // tr[data-href] 등과의 충돌 방지

    if (sidebar.classList.contains('collapsed')) {
      openSidebar();
    } else {
      closeSidebar();
    }
  });

  // -------------------------------------------------------------
  // 백드롭 클릭 시 닫기
  // -------------------------------------------------------------
  backdrop?.addEventListener('click', closeSidebar);

  // -------------------------------------------------------------
  // 초기 상태 및 반응형 처리
  // -------------------------------------------------------------
  function setInitialSidebarState() {
    if (window.innerWidth <= 768) {
      sidebar.classList.add('collapsed');
      mainContent.classList.add('expanded');
    } else {
      sidebar.classList.remove('collapsed');
      mainContent.classList.remove('expanded');
    }
    backdrop?.classList.remove('active');
  }

  setInitialSidebarState();
  window.addEventListener('resize', setInitialSidebarState);

  // -------------------------------------------------------------
  // 다크 모드 토글
  // -------------------------------------------------------------
  const themeToggleButton = document.getElementById('theme-toggle');
  const themeIcon = themeToggleButton?.querySelector('i');

  const getCurrentTheme = () => {
    const storedTheme = localStorage.getItem('theme');
    if (storedTheme) return storedTheme;
    return window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light';
  };

  const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);

    if (themeIcon) {
      if (theme === 'dark') {
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
      } else {
        themeIcon.classList.remove('fa-sun');
        themeIcon.classList.add('fa-moon');
      }
    }

    // 차트 등에서 테마 감지 가능하도록 이벤트 발생
    window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
  };

  themeToggleButton?.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
  });

  applyTheme(getCurrentTheme());
});

/* -------------------------------------------------------------
 * Sidebar Active Menu
 * ------------------------------------------------------------- */
const sidebarNav = document.querySelector('.sidebar__nav');
if (sidebarNav && window.self === window.top) {
  const navLinks = sidebarNav.querySelectorAll('a');
  const currentPage = window.location.pathname;

  navLinks.forEach((link) => {
    const linkHref = link.getAttribute('href');
    if (linkHref === currentPage) {
      link.classList.add('active');

      const parentSubmenu = link.closest('.has-submenu');
      if (parentSubmenu) {
        parentSubmenu.classList.add('open');
        const toggle = parentSubmenu.querySelector('.submenu-toggle');
        if (toggle) toggle.classList.add('active');
      }
    } else {
      link.classList.remove('active');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
    // 'menu-toggle' 클래스를 가진 모든 요소를 찾음
    const menuToggles = document.querySelectorAll('.menu-toggle');

    menuToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault(); // 링크 이동 방지

            // 클릭된 메뉴의 부모 li 요소를 찾음
            const parentLi = toggle.closest('.has-submenu');

            // 다른 열려있는 메뉴들을 자동으로 닫으려면 아래 주석 해제 (아코디언 효과)
            /*
            document.querySelectorAll('.has-submenu').forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove('open');
                }
            });
            */

            // 현재 메뉴의 열림/닫힘 상태 토글
            parentLi.classList.toggle('open');
        });
    });
});
