/**
 * ===================================================================
 * 주문 페이지 (order.js) - 한국 휴대폰 번호 검증 포함
 * ===================================================================
 * 1. 어카운트 아이디 중복 검사
 * 2. 추천인/후원인 아이디 검색
 * 3. 결제 수단 선택 기능
 * 4. 최종 폼 제출 유효성 검사 (연락처 규칙 변경 포함)
 * 5. 연락처 숫자와 하이픈만 입력 필터링
 * 6. 한국 휴대폰 번호 검증
 */
document.addEventListener('DOMContentLoaded', () => {


  /**
   * 추천인/후원인 검색 기능을 초기화하는 함수 (기존과 동일)
   */
function initMemberSearch() {
    const searchButtons = document.querySelectorAll('#referrer_id + .btn, #sponsor_id + .btn');

    searchButtons.forEach(button => {
        const inputField = button.previousElementSibling;
        const msgDiv = button.parentElement.nextElementSibling;

        button.addEventListener('click', () => {
            const memberId = inputField.value.trim();

            // 안전한 아이디 정규식: 영문, 숫자, 가운데 하이픈 허용, 처음/끝 하이픈 불가
            const idRegex = /^[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/;

            if (!idRegex.test(memberId)) {
                if (msgDiv) {
                    msgDiv.textContent = '아이디는 영문, 숫자만 사용 가능하며, 하이픈(-)은 가운데만 올 수 있습니다.';
                    msgDiv.className = 'help-text font-c-md error';
                }
                return;
            }

            const formData = new FormData();
            formData.append('member_id', memberId);
            formData.append(memberUrls.csrfTokenName, memberUrls.csrfHash);

            fetch(memberUrls.findMember, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    // CSRF 갱신
                    if (data.csrfHash) {
                        memberUrls.csrfHash = data.csrfHash;
                        const csrfInput = document.getElementById('csrf_token');
                        if (csrfInput) csrfInput.value = data.csrfHash;
                    }

                    if (data.error) {
                        if (msgDiv) {
                            msgDiv.textContent = data.error;
                            msgDiv.className = 'help-text font-c-md error';
                        }
                        return;
                    }

                    if (data.exists) {
                        if (msgDiv) {
                            msgDiv.textContent = '확인되었습니다.';
                            msgDiv.className = 'help-text font-c-md success';
                        }
                        inputField.readOnly = true;
                        button.disabled = true;
                        button.textContent = '확인 완료';
                        button.classList.remove('btn--secondary');
                        button.classList.add('btn--success');
                    } else {
                        if (msgDiv) {
                            msgDiv.textContent = '존재하지 않는 회원입니다.';
                            msgDiv.className = 'help-text font-c-md error';
                        }
                    }
                })
                .catch(error => console.error('회원 검색 오류:', error));
        });

        // 입력 수정 기능
        inputField.addEventListener('click', () => {
            if (inputField.readOnly) {
                if (confirm('입력한 아이디를 변경하시겠습니까?')) {
                    inputField.readOnly = false;
                    button.disabled = false;
                    button.textContent = '회원 검색';
                    button.classList.remove('btn--success');
                    button.classList.add('btn--secondary');
                    if (msgDiv) {
                        msgDiv.textContent = '';
                        msgDiv.className = 'help-text font-c-md';
                    }
                    inputField.focus();
                }
            }
        });

        // 입력 중 메시지 초기화
        inputField.addEventListener('input', () => {
            if (!inputField.readOnly && msgDiv) {
                msgDiv.textContent = '';
                msgDiv.className = 'help-text font-c-md';
            }
        });
    });
}

  /**
   * ⭐ 결제 수단 선택 및 셀렉트 박스(수령지) 하이라이트 기능을 초기화하는 함수 ⭐
   */
  function initSelectHighlight() {
    const paymentButtons = document.querySelectorAll('.method-btn');
    const hiddenPaymentInput = document.getElementById('payment_method');
    const pickupSelect = document.getElementById('pickup_location'); // 방문 수령지

    // 1. 결제 수단 선택 처리
    if (paymentButtons.length > 0 && hiddenPaymentInput) {
      paymentButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
          if (button.disabled) return; // 비활성화된 버튼은 무시

          paymentButtons.forEach((btn) => btn.classList.remove('--is-active'));
          const clickedButton = event.currentTarget;
          clickedButton.classList.add('--is-active');
          const selectedMethod = clickedButton.dataset.method;
          hiddenPaymentInput.value = selectedMethod;
        });
      });
    }

    // 2. 방문 수령지 선택 시 하이라이트 효과 적용
    if (pickupSelect) {
      function applySelectHighlight() {
        // 첫 번째 옵션(placeholder)이 선택되지 않은 경우에만 하이라이트 적용
        if (pickupSelect.value !== '') {
          pickupSelect.classList.add('--is-selected');
        } else {
          pickupSelect.classList.remove('--is-selected');
        }
      }

      // 초기 로딩 시와 값이 변경될 때 하이라이트 적용
      applySelectHighlight();
      pickupSelect.addEventListener('change', applySelectHighlight);
    }
  }

  /**
   * 연락처 필드에 숫자와 하이픈만 입력되도록 실시간 필터링 (기존과 동일)
   */
  function initPhoneInputFilter() {
    const phoneInput = document.getElementById('phone');
    if (!phoneInput) return;

    phoneInput.addEventListener('input', (event) => {
      event.target.value = event.target.value.replace(/[^0-9-]/g, '');
    });
  }

  /**
   * 폼 최종 제출 유효성 검사 (한국 휴대폰 번호 검증 및 수령지 검증 추가)
   */
  function initFormSubmission() {
    const form = document.getElementById('register-form');
    if (!form) return;
    form.addEventListener('submit', (event) => {
      event.preventDefault();

      const phoneInput = document.getElementById('phone');
      const mlmInput = document.getElementById('mlm_id');
      const referrerInput = document.getElementById('referrer_id');
      const sponsorInput = document.getElementById('sponsor_id');
      const pickupSelect = document.getElementById('pickup_location');
      const agreeTerms = document.getElementById('agreeTerms');

      const phone = phoneInput.value.trim();
      const phoneRegex = /^(01[016789])-?([0-9]{3,4})-?([0-9]{4})$/;

      // 1. 연락처 검증
      if (phone === '') {
        alert('연락처를 입력해주세요.');
        phoneInput.focus();
        return;
      }
      if (!phoneRegex.test(phone)) {
        alert('올바른 휴대폰 번호를 입력해주세요. (예: 010-1234-5678)');
        phoneInput.focus();
        return;
      }

      // 2.방문 수령지 검증
      if (pickupSelect && pickupSelect.value === '') {
        alert('방문 수령지를 선택해주세요.');
        pickupSelect.focus();
        // 미선택 시 하이라이트 제거
        pickupSelect.classList.remove('--is-selected');
        // 에러 스타일링을 위해 임시 클래스를 추가할 수도 있습니다.
        return;
      }

      // 3. 추천인/후원인 검증
      if (!referrerInput.readOnly) {
        alert('추천인 어카운트 회원 검색을 완료해주세요.');
        referrerInput.focus();
        return;
      }
      if (!sponsorInput.readOnly) {
        alert('후원인 어카운트 회원 검색을 완료해주세요.');
        sponsorInput.focus();
        return;
      }

      // 4. 약관 동의 검증
      if (!agreeTerms.checked) {
        alert('구매 동의 약관에 체크해주세요.');
        agreeTerms.focus();
        return;
      }

      // 모든 검증 통과
      form.submit();
    });
  }

  // -- 스크립트 실행 --
  initMemberSearch();
  initSelectHighlight(); // 함수명 변경 및 수령지 로직 통합
  initFormSubmission();
  initPhoneInputFilter();
});
