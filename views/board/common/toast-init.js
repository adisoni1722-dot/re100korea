/**
 * Toast UI Editor 초기화 스크립트
 * write.php에서 TOAST_CONFIG 객체를 먼저 선언해야 합니다.
 */
document.addEventListener('DOMContentLoaded', function() {

    // 설정값이 없으면 실행하지 않음 (Toast Editor가 없는 페이지 대비)
    if (typeof TOAST_CONFIG === 'undefined' || !document.querySelector('#toast_editor')) {
        return;
    }

    const editor = new toastui.Editor({
        el: document.querySelector('#toast_editor'),
        height: '500px',
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        language: 'ko-KR',
        initialValue: TOAST_CONFIG.initialValue, // PHP에서 전달받은 값

        hooks: {
            addImageBlobHook: async (blob, callback) => {
                const formData = new FormData();
                // 서버 컨트롤러가 $_FILES['file']을 받으므로 'file'로 지정
                formData.append('file', blob);

                // CSRF 토큰값 가져오기 (config에서 이름 참조)
                const csrfName = TOAST_CONFIG.csrfName;
                const csrfInput = document.querySelector(`input[name="${csrfName}"]`);
                formData.append(csrfName, csrfInput.value);

                try {
                    const response = await fetch(TOAST_CONFIG.uploadUrl, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) throw new Error('서버 응답 오류');

                    const result = await response.json();

                    // [중요 1] CSRF 토큰 갱신
                    if (result.token) {
                        csrfInput.value = result.token.value;
                    }

                    // [중요 2] 응답 구조에 맞춰 URL 추출
                    if (result.success && result.payload && result.payload.url) {
                        callback(result.payload.url, 'image');
                    } else {
                        alert('이미지 업로드 실패: ' + (result.message || '주소 없음'));
                    }

                } catch (e) {
                    console.error(e);
                    alert('이미지 업로드 중 오류가 발생했습니다.');
                }
            }
        }
    });

    // 폼 전송 시 에디터 내용을 hidden input에 동기화
    const form = document.querySelector(`form[action="${TOAST_CONFIG.actionUrl}"]`);
    if (form) {
        form.addEventListener('submit', function() {
            const hiddenInput = document.getElementById('hidden_content');
            if (hiddenInput) {
                hiddenInput.value = editor.getHTML();
            }
        });
    }
});
