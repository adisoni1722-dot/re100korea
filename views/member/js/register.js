document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('register-form');
    if (!form) return;

    // --- Element Selectors ---
    const csrfTokenInput = document.getElementById('csrf_token');
    const memberIdInput = document.getElementById('member_id');
    const checkIdBtn = document.getElementById('check-id-btn');
    const nameInput = document.getElementById('name');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const phoneInput = document.getElementById('phone');

    const idCheckMsg = document.getElementById('id_check_msg');
    const nameCheckMsg = document.getElementById('name_check_msg');
    const passwordCheckMsg = document.getElementById('password_check_msg');
    const passwordConfirmMsg = document.getElementById('password_confirm_msg');
    const phoneCheckMsg = document.getElementById('phone_check_msg');

    let isIdCheckedAndAvailable = false;

    // --- Helper Functions ---
    const showMessage = (element, message, isSuccess = false) => {
        element.textContent = message;
        element.style.color = isSuccess ? '#28a745' : '#dc3545';
    };

    const clearMessage = (element, defaultText = '') => {
        element.textContent = defaultText;
        element.style.color = '';
    };

    const resetCheckIdButton = () => {
        checkIdBtn.textContent = '중복확인';
        checkIdBtn.style.backgroundColor = '';
        checkIdBtn.style.borderColor = '';
        checkIdBtn.style.color = ''; // ✅ 글자색 초기화 추가
        checkIdBtn.disabled = false;
    };

    // --- Event Listeners ---

    // 1. ID Check (AJAX)
    checkIdBtn.addEventListener('click', async () => {
        const memberId = memberIdInput.value.trim();
        const idRegex = /^[a-z0-9]{5,20}$/;

        if (!idRegex.test(memberId)) {
            showMessage(idCheckMsg, '아이디는 5~20자의 영문 소문자, 숫자만 사용 가능합니다.');
            isIdCheckedAndAvailable = false;
            return;
        }

        const formData = new FormData();
        formData.append('member_id', memberId);
        const csrfInput = document.querySelector('input[name*="csrf"]');
        if (csrfInput) {
            formData.append(csrfInput.name, csrfInput.value);
        }

        try {
            const response = await fetch(memberUrls.checkId, { method: 'POST', body: formData });
            const result = await response.json();

            if (result.token) {
                const token = result.token;
                if (csrfInput) csrfInput.value = token.value;
            }

            if (result.payload.exists === false) {
                showMessage(idCheckMsg, '사용 가능한 아이디입니다.', true);
                isIdCheckedAndAvailable = true;

                checkIdBtn.textContent = '확인완료';
                checkIdBtn.style.backgroundColor = '#28a745'; // ✅ 초록색
                checkIdBtn.style.borderColor = '#28a745'; // ✅ 테두리도 동일
                checkIdBtn.style.color = '#ffffff'; // ✅ 글자 흰색
                checkIdBtn.disabled = true;
            } else {
                showMessage(idCheckMsg, '이미 사용 중인 아이디입니다.', false);
                isIdCheckedAndAvailable = false;
                resetCheckIdButton();
            }
        } catch (error) {
            console.error('ID Check Error:', error);
            showMessage(idCheckMsg, '오류가 발생했습니다. 다시 시도해주세요.');
            isIdCheckedAndAvailable = false;
        }
    });

    // 2. Reset ID Check status on input change
    memberIdInput.addEventListener('input', () => {
        if (isIdCheckedAndAvailable) {
            isIdCheckedAndAvailable = false;
            clearMessage(idCheckMsg, '아이디가 변경되었습니다. 다시 중복확인을 해주세요.');
            idCheckMsg.style.color = '#dc3545';
            resetCheckIdButton();
        }
    });

    // 2-1. 아이디 입력창을 떠날 때 (focus out)
    memberIdInput.addEventListener('blur', () => {
        // 중복확인을 하지 않은 상태로 다른 칸으로 이동하면 안내 표시
        const memberId = memberIdInput.value.trim();
        if (memberId && !isIdCheckedAndAvailable) {
            showMessage(idCheckMsg, '아이디 중복확인을 완료해주세요.', false);
        }
    });

    // 3. Real-time Name Validation
    nameInput.addEventListener('input', () => {
        const nameValue = nameInput.value.trim();
        const nameRegex = /^[가-힣]{2,}$/;
        if (!nameValue || nameRegex.test(nameValue)) {
            clearMessage(nameCheckMsg);
        } else {
            showMessage(nameCheckMsg, '성명은 2자 이상의 한글로 입력해주세요.');
        }
    });

    // 4. Real-time Password Validation
    passwordInput.addEventListener('input', () => {
        const passwordValue = passwordInput.value;
        const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,}$/;

        if (!passwordValue) {
            clearMessage(passwordCheckMsg);
        } else if (passwordRegex.test(passwordValue)) {
            showMessage(passwordCheckMsg, '사용 가능한 비밀번호입니다.', true);
        } else {
            showMessage(passwordCheckMsg, '비밀번호는 6자 이상, 영문과 숫자를 포함해야 합니다.');
        }

        // ✅ 확인칸이 비어 있지 않을 때만 재검사
        if (passwordConfirmInput.value.trim() !== '') {
            const event = new Event('input');
            passwordConfirmInput.dispatchEvent(event);
        }
    });

    passwordInput.addEventListener('blur', () => {
        const pw = passwordInput.value.trim();
        const confirm = passwordConfirmInput.value.trim();

        // 비밀번호가 채워져 있는데, 확인칸이 비어 있으면 안내 문구 표시
        if (pw && !confirm) {
            showMessage(passwordConfirmMsg, '비밀번호 확인을 입력해주세요.', false);
        }
    });

    passwordConfirmInput.addEventListener('input', () => {
        const pw = passwordInput.value;
        const confirm = passwordConfirmInput.value;

        // ✅ 확인칸이 비어있을 땐 메시지 표시 안 함
        if (!confirm) {
            clearMessage(passwordConfirmMsg);
            return;
        }

        if (pw === confirm) {
            showMessage(passwordConfirmMsg, '비밀번호가 일치합니다.', true);
        } else {
            showMessage(passwordConfirmMsg, '비밀번호가 일치하지 않습니다.', false);
        }
    });

    // 6. Form Submit Validation
    form.addEventListener('submit', (event) => {
        if (!isIdCheckedAndAvailable) {
            clearMessage(idCheckMsg);
        }
        clearMessage(nameCheckMsg);
        clearMessage(passwordCheckMsg);
        clearMessage(passwordConfirmMsg);
        clearMessage(phoneCheckMsg, '휴대폰 번호 입력은 선택사항 입니다');

        let isValid = true;

        // ID Validation
        if (!memberIdInput.value.trim()) {
            isValid = false;
            showMessage(idCheckMsg, '아이디를 입력해주세요.');
            resetCheckIdButton();
        } else if (!isIdCheckedAndAvailable) {
            isValid = false;
            showMessage(idCheckMsg, '아이디 중복확인을 완료해주세요.');
        }

        // Name Validation
        const nameValue = nameInput.value.trim();
        const nameRegex = /^[가-힣]{2,}$/;
        if (!nameValue) {
            isValid = false;
            showMessage(nameCheckMsg, '성명을 입력해주세요.');
        } else if (!nameRegex.test(nameValue)) {
            isValid = false;
            showMessage(nameCheckMsg, '성명은 2자 이상의 한글로 입력해주세요.');
        }

        // ⭐ [수정됨] Password Validation Logic
        const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,}$/;
        if (!passwordInput.value) {
            isValid = false;
            showMessage(passwordCheckMsg, '비밀번호를 입력해주세요.');
        } else if (!passwordRegex.test(passwordInput.value)) {
            isValid = false;
            showMessage(passwordCheckMsg, '비밀번호는 6자 이상, 영문과 숫자를 포함해야 합니다.');
        }

        // Password Confirmation Validation
        if (!passwordConfirmInput.value) {
            isValid = false;
            showMessage(passwordConfirmMsg, '비밀번호 확인을 입력해주세요.');
        } else if (passwordInput.value !== passwordConfirmInput.value) {
            isValid = false;
            showMessage(passwordConfirmMsg, '비밀번호가 일치하지 않습니다.');
        }

        // Phone Number Validation
        const phoneValue = phoneInput.value.trim();
        const phoneRegex = /^010-?[0-9]{4}-?[0-9]{4}$/;
        if (phoneValue && !phoneRegex.test(phoneValue)) {
            isValid = false;
            showMessage(phoneCheckMsg, '올바른 휴대폰 번호 형식이 아닙니다. (예: 010-1234-5678)');
        }

        // Prevent form submission if invalid
        if (!isValid) {
            event.preventDefault();
        }
    });
});
