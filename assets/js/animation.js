/**
 * 애니메이션 통합 스크립트 (Flickering Fix)
 */
document.addEventListener('DOMContentLoaded', () => {

    // 1. Blur 텍스트 등 기존 로직 유지
    document.querySelectorAll('[data-type="blur"]').forEach((group) => {
        const html = group.innerHTML;
        group.innerHTML = '';
        let i = 0, index = 0;
        while (i < html.length) {
            if (html[i] === '<') {
                const end = html.indexOf('>', i);
                const tag = html.slice(i, end + 1);
                group.innerHTML += tag;
                i = end + 1;
            } else {
                const span = document.createElement('span');
                span.className = 'letter';
                if (html[i] === ' ') span.innerHTML = '&nbsp;';
                else span.textContent = html[i];
                span.style.animationDelay = `${index * 80 + Math.random() * 50}ms`;
                group.appendChild(span);
                index++;
                i++;
            }
        }
    });

    // 2. 통합 Intersection Observer
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                const el = entry.target;

                if (entry.isIntersecting) {

                    // === CASE A: 카드 리스트 (data-card 존재) ===
                    if (el.hasAttribute('data-card')) {
                        // 1. 부모 컨테이너 먼저 활성화 (부모 애니메이션이 있다면)
                        el.classList.add('ani-on-active');

                        const children = el.querySelectorAll('.card-ani');
                        const parentDelay = parseInt(el.dataset.delay || '0', 10);

                        // [핵심 수정]
                        // 깜빡임은 스타일 적용과 렌더링 충돌 때문입니다.
                        // 1단계: 모든 자식의 딜레이를 먼저 세팅합니다.
                        children.forEach((child, index) => {
                            // 부모 딜레이 + 순차 간격 (150ms)
                            const totalDelay = parentDelay + (index * 150);
                            child.style.transitionDelay = `${totalDelay}ms`;
                            // 애니메이션(keyframes)을 쓸 경우를 대비해 둘 다 적용
                            child.style.animationDelay = `${totalDelay}ms`;
                        });

                        // 2단계: 아주 짧은 시간(50ms) 뒤에 클래스를 붙입니다.
                        // 브라우저가 위에서 설정한 delay 스타일을 "소화"할 시간을 줍니다.
                        setTimeout(() => {
                            children.forEach((child) => {
                                child.classList.add('ani-on-active');
                            });
                        }, 50);

                    }
                    // === CASE B: 일반 요소 ===
                    else {
                        el.classList.remove('ani-on-active');
                        // 리플로우 강제 (애니메이션 재시작용)
                        void el.offsetWidth;

                        const delayMs = el.dataset.delay || '0';
                        el.style.animationDelay = `${delayMs}ms`;
                        el.style.transitionDelay = `${delayMs}ms`;

                        el.classList.add('ani-on-active');
                    }

                    // 관찰 중지
                    observer.unobserve(el);
                }
            });
        },
        { threshold: 0.15 }
    );

    // 관찰 시작
    document.querySelectorAll('.ani-on').forEach((el) => {
        observer.observe(el);
    });

    // 3. 기타 기능 (카운트, 호버, 웨이브) - 기존 유지
    const counterElement = document.querySelector('.count-number');
    if (counterElement) {
        const startCounter = (element) => {
            const target = parseInt(element.dataset.target, 10) || 77;
            let current = 10, step = (target - current) / 50;
            const interval = setInterval(() => {
                current += step;
                if ((step > 0 && current >= target) || (step < 0 && current <= target)) {
                    element.textContent = target;
                    clearInterval(interval);
                } else {
                    element.textContent = Math.round(current);
                }
            }, 30);
        };
        new IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    startCounter(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 }).observe(counterElement);
    }

    document.querySelectorAll('.auto-hover').forEach((el) => {
        el.classList.add('hover-on');
        setTimeout(() => el.classList.remove('hover-on'), 1200);
    });

    const container = document.querySelector('.image-wave');
    if (container) {
        const sliceCount = 8;
        const imageURL = container.dataset.bg;
        if (imageURL) {
            for (let i = 0; i < sliceCount; i++) {
                const slice = document.createElement('div');
                slice.classList.add('slice');
                slice.style.backgroundImage = `url(${imageURL})`;
                slice.style.backgroundPosition = `${(i / (sliceCount - 1)) * 100}% 0`;
                slice.style.animationDelay = `${i * 0.1}s`;
                container.appendChild(slice);
            }
        }
    }
});
