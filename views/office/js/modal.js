/**
 * Modal (v2025.11.04 - data-reload fix)
 * -------------------------------------------------------------
 * 사이즈: sm, md, lg, xl, full
 * 타입: alert, confirm, iframe, split
 * ESC 닫기 / Body 스크롤 잠금 / reload 지원
 * -------------------------------------------------------------
 */
if (!window.__modal_initialized__) {
  window.__modal_initialized__ = true;

  document.addEventListener('DOMContentLoaded', () => {
    function create(type, options = {}) {
      const overlay = document.createElement('div');
      overlay.className = 'modal-overlay visible';

      const content = document.createElement('div');
      content.classList.add('modal-content');
      if (options.size) content.classList.add('modal-' + options.size);

      // iframe / split
      if (type === 'iframe' || type === 'split') {
        content.classList.add(type === 'split' ? 'modal-split' : 'modal-iframe');

        const title = options.title || '상세 보기';
        const header = `
          <div class="modal-header">
            <h3 class="modal-title">${title}</h3>
            <button type="button" class="btn-close" aria-label="닫기">&times;</button>
          </div>
        `;

        if (type === 'split' && Array.isArray(options.urls)) {
          content.innerHTML =
            header + options.urls.map((u) => `<iframe src="${u}"></iframe>`).join('');
        } else {
          content.innerHTML =
            header + `<iframe src="${options.url || 'about:blank'}"></iframe>`;
        }
      }

      // alert / confirm
      else {
        const msg = options.message || '확인하시겠습니까?';
        const title = options.title || (type === 'alert' ? '알림' : '확인');
        const header = `
          <div class="modal-header">
            <h3 class="modal-title">${title}</h3>
            <button type="button" class="btn-close" aria-label="닫기">&times;</button>
          </div>
        `;

        content.innerHTML = `
          ${header}
          <div class="modal-body">
            <p>${msg}</p>
            <div class="modal-actions">
              ${
                type === 'confirm'
                  ? `<button class="btn btn-secondary" data-role="cancel">취소</button>`
                  : ''
              }
              <button class="btn ${
                type === 'confirm' ? 'btn-danger-outline' : 'btn-primary'
              }" data-role="ok">
                ${type === 'confirm' ? '확인' : '닫기'}
              </button>
            </div>
          </div>
        `;
      }

      overlay.appendChild(content);
      document.body.appendChild(overlay);
      document.body.style.overflow = 'hidden';

      // 닫기 함수
      const close = () => {
        overlay.classList.remove('visible');
        setTimeout(() => {
          overlay.remove();
          document.body.style.overflow = '';

          // ✅ data-reload="true" 이면 새로고침
          if (options.reload === true) {
            location.reload();
          }
        }, 250);
        window.removeEventListener('keydown', escHandler);
      };

      // ESC 닫기
      function escHandler(e) {
        if (e.key === 'Escape') close();
      }
      window.addEventListener('keydown', escHandler);

      // 오버레이 / 닫기버튼 클릭 시 닫기
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay || e.target.classList.contains('btn-close')) close();
      });

      // 버튼 이벤트
      const ok = content.querySelector('[data-role="ok"]');
      const cancel = content.querySelector('[data-role="cancel"]');
      ok?.addEventListener('click', () => {
        close();
        if (typeof options.onOk === 'function') options.onOk();
      });
      cancel?.addEventListener('click', () => {
        close();
        if (typeof options.onCancel === 'function') options.onCancel();
      });

      return overlay;
    }

    // 전역 API
    window.Modal = {
      alert(msg, onOk, size = 'sm') {
        create('alert', { message: msg, onOk, size });
      },
      confirm(msg, onOk, onCancel, size = 'sm') {
        create('confirm', { message: msg, onOk, onCancel, size });
      },
      iframe(url, title = '상세 보기', size = 'lg', reload = false) {
        create('iframe', { url, title, size, reload });
      },
      split(urls, title = '분할 보기', size = 'xl', reload = false) {
        create('split', { urls, title, size, reload });
      },
    };

    // 클릭 위임
    document.addEventListener('click', (e) => {
      const el = e.target.closest('[data-modal-type]');
      if (!el) return;
      e.preventDefault();
      e.stopPropagation();

      const type = el.dataset.modalType;
      const url = el.dataset.url;
      const urls = el.dataset.urls ? el.dataset.urls.split(',') : null;
      const title = el.dataset.title || '상세 보기';
      const msg = el.dataset.message;
      const size = el.dataset.size || (type === 'iframe' ? 'lg' : 'sm');
      const action = el.dataset.action;
      const reload = el.dataset.reload === 'true'; // ✅ 읽기

      if (type === 'iframe') {
        create('iframe', { url, title, size, reload });
      } else if (type === 'split') {
        create('split', { urls, title, size, reload });
      } else if (type === 'confirm') {
        Modal.confirm(
          msg,
          () => {
            if (!action) return;
            if (/^(https?:|\/)/.test(action)) {
              window.location.href = action;
            } else {
              try {
                new Function(action)();
              } catch (err) {
                console.error(`[Modal.confirm] 실행 오류: ${err.message}`);
              }
            }
          },
          null,
          size
        );
      } else {
        Modal.alert(msg, null, size);
      }
    });
  });
}
