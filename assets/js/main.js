/**
 * 메인 JavaScript 파일 (최종 수정본)
 */
document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('#header');
  const mobileToggle = document.querySelector('.nav-mobile-toggle');

  // 요소가 없으면 종료
  if (!header) return;

  // 1. 모바일 메뉴 토글 (햄버거 버튼)
  mobileToggle?.addEventListener('click', () => {
    const isActive = header.classList.toggle('active');
    document.documentElement.classList.toggle('no-scroll', isActive);
  });

  // 2. 백그라운드 이미지 처리
  const bgElements = document.querySelectorAll('.bg-cover');
  if (bgElements) {
    bgElements.forEach((el) => {
      const bg = el.getAttribute('data-bg');
      if (bg) el.style.backgroundImage = `url(${bg})`;
    });
  }

  // 3. 스크롤 핸들러 (헤더 고정 및 투명 처리)
  function handleHeaderEvents() {
    const scrollY = window.scrollY;

    // 165px 이상 스크롤 시 헤더 고정
    header.classList.toggle('fixed', scrollY >= 165);

    // 투명 헤더 페이지인지 확인
    const transparentElement = document.querySelector('.transparent-on');
    header.classList.toggle('transparent', !!transparentElement);
  }

  window.addEventListener('scroll', handleHeaderEvents);
  window.addEventListener('resize', () => {
    handleHeaderEvents();
    // PC 화면으로 커지면 모바일 메뉴 상태 초기화
    if (window.innerWidth > 768) {
      header.classList.remove('active');
      document.documentElement.classList.remove('no-scroll');
    }
  });
  handleHeaderEvents(); // 초기 실행


  // ============================================================
  // [핵심] 네비게이션 활성화 (Active) 및 모바일 자동 펼침 로직
  // ============================================================
  const currentUrl = window.location.pathname;
  const allLinks = document.querySelectorAll('.nav-menu a');

  allLinks.forEach((link) => {
    const linkHref = link.getAttribute('href');

    // 1. 현재 주소와 일치하는지 확인 (메인, 서브 모두 포함)
    if (linkHref && (currentUrl === linkHref || (linkHref !== '/' && currentUrl.startsWith(linkHref)))) {

      // [서브 메뉴 활성화]
      link.classList.add('active');
      link.closest('li')?.classList.add('active');

      // 2. 부모(메인 메뉴) 찾아서 활성화 시키기
      // .nav-menu 바로 아래에 있는 li가 '메인 메뉴'입니다.
      const mainMenuLi = link.closest('.nav-menu > li');

      if (mainMenuLi) {
        mainMenuLi.classList.add('active'); // 메인 메뉴 li 활성화
        // mainMenuLi.classList.add('open');   // 모바일에서 펼침 상태 유지

        // 메인 메뉴의 텍스트(a 태그)에도 직접 active 클래스 부여 (확실한 색상 변경)
        const mainMenuLink = mainMenuLi.querySelector('a');
        if (mainMenuLink) {
            mainMenuLink.classList.add('active');
        }
      }
    }
  });


  // ============================================================
  // [핵심] 모바일 아코디언 클릭 이벤트 (수정됨: 하나만 열리기)
  // ============================================================
  const menuItems = document.querySelectorAll('.nav-menu > li');

  menuItems.forEach((item) => {
    const hasSubMenu = item.querySelector('.nav-mega-wrap') || item.querySelector('.nav-menu-sub');
    const link = item.querySelector('a');

    if (hasSubMenu && link) {
      link.classList.add('has-sub'); // 화살표 아이콘 표시용 클래스

      link.addEventListener('click', (e) => {
        // 모바일 화면일 때만 동작
        if (window.innerWidth <= 768) {
          e.preventDefault(); // 링크 이동 막고 메뉴 펼치기

          // [추가된 로직] 클릭한 메뉴(item)를 제외한 나머지 모든 메뉴 닫기
          menuItems.forEach((otherItem) => {
            if (otherItem !== item) {
              otherItem.classList.remove('open');
            }
          });

          // 현재 클릭한 메뉴 토글 (열려있으면 닫고, 닫혀있으면 열기)
          item.classList.toggle('open');
        }
      });
    }
  });

  // ============================================================
  // PC 서브메뉴 열림 시 오버레이 작동
  // ============================================================
  const overlay = document.querySelector('.main-overlay');
  const navListItems = document.querySelectorAll('.nav-menu > li');

  if (overlay && window.innerWidth > 768) { // PC 화면일 때만
    navListItems.forEach((li) => {
      // 서브 메뉴(.nav-mega-wrap)가 있는 항목만 감지
      const hasMegaMenu = li.querySelector('.nav-mega-wrap');

      if (hasMegaMenu) {
        li.addEventListener('mouseenter', () => {
          overlay.classList.add('show');
        });

        li.addEventListener('mouseleave', () => {
          overlay.classList.remove('show');
        });
      }
    });
  }
});
