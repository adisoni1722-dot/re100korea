/*! formSubmit-lite v2025.11 (MiniCI Edition) — Classic Submit + Validation + CSRF Refresh */
(() => {
  'use strict';

  const qs = (sel, el = document) => el.querySelector(sel);
  const qsa = (sel, el = document) => Array.from(el.querySelectorAll(sel));

  const hasCSSescape = typeof CSS !== 'undefined' && typeof CSS.escape === 'function';
  const esc = (s) => (hasCSSescape ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_\-]/g, '\\$&'));

  const getRules = (input) => {
    const d = input.dataset;
    return {
      required: d.required === '' || d.required === 'true',
      type: d.type || '',
      min: d.min != null ? Number(d.min) : null,
      max: d.max != null ? Number(d.max) : null,
      minlength: d.minlength != null ? Number(d.minlength) : null,
      maxlength: d.maxlength != null ? Number(d.maxlength) : null,
      pattern: d.pattern ? new RegExp(d.pattern) : null,
      nice: d.nice || input.name || input.id || '이 항목',
    };
  };

  const validateInput = (input) => {
    const val = (input.value ?? '').trim();
    const r = getRules(input);

    if (r.required && !val) return `${r.nice}을(를) 입력하세요.`;
    if (!val) return '';

    if (r.type === 'email') {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val))
        return `${r.nice}의 형식이 올바르지 않습니다.`;
    } else if (r.type === 'number') {
      if (!/^-?\d+(\.\d+)?$/.test(val)) return `${r.nice}은(는) 숫자여야 합니다.`;
      const num = Number(val);
      if (r.min != null && num < r.min) return `${r.nice}은(는) ${r.min} 이상이어야 합니다.`;
      if (r.max != null && num > r.max) return `${r.nice}은(는) ${r.max} 이하여야 합니다.`;
    } else if (r.type === 'memberid') {
      if (!/^(?=.{4,16}$)[A-Za-z][A-Za-z0-9_]*$/.test(val))
        return `${r.nice}은(는) 영문으로 시작하고 영문/숫자/_만, 4~16자로 입력하세요.`;
    }

    if (r.minlength != null && val.length < r.minlength)
      return `${r.nice}은(는) 최소 ${r.minlength}자입니다.`;
    if (r.maxlength != null && val.length > r.maxlength)
      return `${r.nice}은(는) 최대 ${r.maxlength}자입니다.`;
    if (r.pattern && !r.pattern.test(val))
      return `${r.nice} 형식이 올바르지 않습니다.`;

    return '';
  };

  const clearErrors = (form) => {
    qsa('.field-error', form).forEach((el) => el.remove());
    qsa('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
  };

  const showFieldError = (input, message) => {
    input.classList.add('is-invalid');
    const div = document.createElement('div');
    div.className = 'field-error';
    div.textContent = message;
    input.insertAdjacentElement('afterend', div);
  };

  const validateForm = (form) => {
    clearErrors(form);
    const inputs = qsa('input[name], textarea[name], select[name]', form);
    for (const input of inputs) {
      const msg = validateInput(input);
      if (msg) {
        showFieldError(input, msg);
        input.focus();
        return false;
      }
    }
    return true;
  };

  const showLoading = (form, show = true) => {
    let overlay = qs('.form-loading', form);
    if (show) {
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'form-loading';
        overlay.innerHTML = '<div class="spinner"></div><p>처리 중...</p>';
        Object.assign(overlay.style, {
          position: 'absolute',
          inset: 0,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: 'rgba(255,255,255,0.7)',
          zIndex: 100,
        });
        form.style.position = 'relative';
        form.appendChild(overlay);
      }
    } else if (overlay) {
      overlay.remove();
    }
  };

  // 폼 제출 이벤트
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.matches('form[data-submit]')) return;

    if (!validateForm(form)) {
      e.preventDefault();
      return;
    }

    // 중복 제출 방지
    const btns = qsa('button[type="submit"],input[type="submit"]', form);
    btns.forEach((b) => (b.disabled = true));
    showLoading(form, true);

    // 폼 제출 후 CSRF 메타 초기화 (옵션)
    setTimeout(() => {
      btns.forEach((b) => (b.disabled = false));
      showLoading(form, false);
    }, 5000); // 5초 후 자동 해제 (예외 방지)
  });

  window.formSubmitLite = { validateForm, clearErrors };
})();
