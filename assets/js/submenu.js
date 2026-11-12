/**
   *  서브 메뉴
   */
  const navBg = document.querySelector('.nav__bg');
  const navWrapper = document.querySelector('.nav');
  const navItems = document.querySelectorAll('.nav__menu > li');

  let wasTransparent = false;
  let navReady = false;

  // 일정 시간 후에 메뉴 동작 허용
  window.addEventListener('load', () => {
    setTimeout(() => {
      navReady = true;
    }, 300);
  });

  // 서브메뉴가 하나라도 있는지 확인
  const hasAnySubMenu = () => document.querySelectorAll('.nav__menu__sub').length > 0;

  // 메뉴 열기
  function openSubmenus() {
    document.querySelectorAll('.nav__menu__sub').forEach((sub) => sub.classList.add('show'));
    navBg.classList.add('show');
    overlay?.classList.add('show');
    document.documentElement.classList.add('no-scroll');

    if (header.classList.contains('transparent')) {
      wasTransparent = true;
      header.classList.remove('transparent');
    }

    header.style.backgroundColor = '#fff';
  }

  // 메뉴 닫기
  function closeSubmenus() {
    if (hasAnySubMenu()) {
      document.querySelectorAll('.nav__menu__sub').forEach((sub) => sub.classList.remove('show'));
      navBg.classList.remove('show');
      overlay?.classList.remove('show');
    }

    document.documentElement.classList.remove('no-scroll');

    if (wasTransparent) {
      header.classList.add('transparent');
      wasTransparent = false;
    }

    header.style.backgroundColor = '';
  }

  // 데스크탑 동작
  function setupDesktopNav() {
    navItems.forEach((item) => {
      item.addEventListener('mouseenter', () => {
        if (!isDesktop() || !navReady) return; // navReady 체크 추가

        if (!hasAnySubMenu()) {
          if (header.classList.contains('transparent')) {
            wasTransparent = true;
            header.classList.remove('transparent');
          }
          header.style.backgroundColor = '#fff';
          return;
        }

        openSubmenus();
      });
    });

    navWrapper?.addEventListener('mouseleave', () => {
      if (!isDesktop()) return;
      closeSubmenus();
    });
  }

  // 모바일 동작
  function setupMobileNav() {
    navItems.forEach((item) => {
      const link = item.querySelector('a');
      const submenu = item.querySelector('.nav__menu__sub');

      link.addEventListener('click', (e) => {
        if (isDesktop()) return;

        if (submenu) {
          e.preventDefault();

          // 다른 서브메뉴 닫기
          document.querySelectorAll('.nav__menu__sub').forEach((sub) => {
            if (sub !== submenu) sub.classList.remove('show');
          });

          submenu.classList.toggle('show');
        }
      });
    });
  }

  // 데스크탑/모바일 상태 변경 시 리로드
  let lastIsDesktop = isDesktop();
  function handleResizeReload() {
    const currentIsDesktop = isDesktop();

    if (currentIsDesktop !== lastIsDesktop) {
      location.reload();
    }
  }
  window.addEventListener('resize', handleResizeReload);

  // 초기 실행
  setupDesktopNav();
  setupMobileNav();