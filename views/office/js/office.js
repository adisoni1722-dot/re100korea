document.addEventListener('DOMContentLoaded', function () {
    const hamburgerButton = document.getElementById('hamburger-button');
    const sidebar = document.getElementById('office-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const body = document.body;

    // 메뉴를 열고 닫는 함수
    function toggleSidebar() {
        sidebar.classList.toggle('is-open');
        body.classList.toggle('sidebar-open');
    }

    // 햄버거 버튼 클릭 시 메뉴 토글
    if (hamburgerButton) {
        hamburgerButton.addEventListener('click', toggleSidebar);
    }

    // 오버레이 클릭 시 메뉴 닫기
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    //링크

    const links = document.querySelectorAll('.sidebar__link');

    // 현재 경로의 끝 슬래시 제거
    let currentPath = window.location.pathname.replace(/\/+$/, '');
    if (currentPath === '') currentPath = '/';

    links.forEach((link) => {
        const href = (link.getAttribute('href') || '').replace(/\/+$/, '') || '/';
        link.classList.remove('sidebar__link--is-active');

        // 홈('/')
        if (href === '/' && currentPath === '/') {
            link.classList.add('sidebar__link--is-active');
            return;
        }

        // 세그먼트 단위로 정확히 비교
        const currentParts = currentPath.split('/').filter(Boolean);
        const hrefParts = href.split('/').filter(Boolean);

        const isExact = currentParts.length === hrefParts.length && currentPath === href;

        if (isExact) {
            link.classList.add('sidebar__link--is-active');
        }
    });
});
