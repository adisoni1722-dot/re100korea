/*! formAjax-lite v2025.11 (MiniCI Edition) — CSRF Auto-Refresh + Validation */
(() => {
  'use strict';

  const qs = (sel, el = document) => el.querySelector(sel);
  const qsa = (sel, el = document) => Array.from(el.querySelectorAll(sel));
  const getMeta = (name) => (qs(`meta[name="${name}"]`) || {}).content || '';

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

  const submitAjax = async (form) => {
    const action = form.getAttribute('action') || location.href;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const fd = new FormData(form);

    // [Optional] form_open() 사용 시 이미 존재하므로 중복 추가 방지
    const csrfName = getMeta('csrf-token-name') || 'csrf_token';
    const csrfVal = getMeta('csrf-token');
    if (csrfVal && !fd.has(csrfName)) fd.append(csrfName, csrfVal);

    const btns = qsa('button[type="submit"],input[type="submit"]', form);
    btns.forEach((b) => (b.disabled = true));

    try {
      const res = await fetch(action, {
        method,
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });

      const json = await res.json().catch(() => null);
      if (!json) throw new Error('서버 응답을 해석할 수 없습니다.');

      // [NEW] 최신 CSRF 토큰 자동 반영 (hidden + meta)
      if (json.token && json.token.name && json.token.value) {
        const csrfInput = qs(`input[name="${esc(json.token.name)}"]`, form);
        if (csrfInput) csrfInput.value = json.token.value;

        const metaToken = qs('meta[name="csrf-token"]');
        if (metaToken) metaToken.content = json.token.value;
      }

      if (json.ok || json.success || json.status === 'success') {
        form.dispatchEvent(new CustomEvent('ajax:success', { detail: json }));
      } else {
        if (json.errors && typeof json.errors === 'object') {
          Object.entries(json.errors).forEach(([name, msg]) => {
            const input = qs(`[name="${esc(name)}"]`, form);
            if (input) showFieldError(input, Array.isArray(msg) ? msg.join(', ') : String(msg));
          });
        }
        form.dispatchEvent(new CustomEvent('ajax:error', { detail: json }));
      }
    } catch (e) {
      form.dispatchEvent(new CustomEvent('ajax:error', { detail: { ok: false, message: e.message } }));
    } finally {
      btns.forEach((b) => (b.disabled = false));
    }
  };

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.matches('form[data-ajax]')) return;
    e.preventDefault();
    if (!validateForm(form)) return;
    submitAjax(form);
  });

  // 외부 접근용 헬퍼
  window.formAjaxLite = { validateForm, submitAjax, clearErrors };
})();
