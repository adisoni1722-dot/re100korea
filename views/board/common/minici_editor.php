<div class="editor-wrapper">
    <div class="toolbar">
        <button type="button" onclick="toggleSource()" title="HTML 소스 보기" id="btnSource">
            <i class="fas fa-code"></i>
        </button>
        <div class="separator"></div>

        <button type="button" data-cmd="bold" title="굵게"><i class="fas fa-bold"></i></button>

        <div class="color-wrapper">
            <button type="button" title="글자 색상"><i class="fas fa-palette"></i></button>
            <input type="color" id="foreColorInput" title="글자 색상 선택">
        </div>

        <div class="separator"></div>

        <button type="button" onclick="insertVideo()" title="동영상(유튜브)"><i class="fab fa-youtube"></i></button>
        <button type="button" onclick="triggerImageUpload()" title="이미지 삽입"><i class="far fa-image"></i></button>
        <button type="button" onclick="createLink()" title="링크"><i class="fas fa-link"></i></button>

        <div class="separator"></div>

        <button type="button" onclick="formatBlock('H2')"><b>H2</b></button>
        <button type="button" onclick="formatBlock('H3')"><b>H3</b></button>

        <div class="separator"></div>

        <button type="button" data-cmd="justifyLeft" title="왼쪽 정렬"><i class="fas fa-align-left"></i></button>
        <button type="button" data-cmd="justifyCenter" title="가운데 정렬"><i class="fas fa-align-center"></i></button>
        <button type="button" data-cmd="justifyRight" title="오른쪽 정렬"><i class="fas fa-align-right"></i></button>

        <div class="separator"></div>

        <button type="button" data-cmd="insertOrderedList" title="숫자 목록"><i class="fas fa-list-ol"></i></button>
        <button type="button" data-cmd="insertUnorderedList" title="점 목록"><i class="fas fa-list-ul"></i></button>
        <button type="button" onclick="formatBlock('blockquote')" title="인용구"><i class="fas fa-quote-right"></i></button>

        <div class="separator"></div>

        <button type="button" id="btnMore" onclick="toggleExtendedToolbar()" title="더보기 (서식 확장)">
            <i class="fas fa-ellipsis-h"></i>
        </button>


        <input type="file" id="imageInput" accept="image/*" style="display:none;" onchange="uploadImage(this)">
    </div>

    <div class="toolbar-extended" id="toolbarExtended">
        <button type="button" data-cmd="italic" title="기울임"><i class="fas fa-italic"></i></button>
        <button type="button" data-cmd="underline" title="밑줄"><i class="fas fa-underline"></i></button>
        <button type="button" data-cmd="strikeThrough" title="취소선"><i class="fas fa-strikethrough"></i></button>
    </div>

    <div id="editor" contenteditable="true"><?= $content ?? '' ?></div>
    <textarea id="sourceEditor"></textarea>
</div>

<style>
    .editor-wrapper {
        width: 100%;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        position: relative;
    }

    /* 1. 메인 툴바 */
    .toolbar {
        background-color: #f8f9fa;
        padding: 8px;
        border-bottom: 1px solid #ddd;
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 5px;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
        align-items: center;
        z-index: 10;
        position: relative;
    }

    /* 2. 확장 툴바 (기본 숨김) */
    .toolbar-extended {
        display: none;
        /* 숨김 */
        background-color: #f1f3f5;
        padding: 5px 8px;
        border-bottom: 1px solid #ddd;
        gap: 5px;
        align-items: center;
        animation: slideDown 0.2s ease-out;
    }

    .toolbar-extended.show {
        display: flex;
        /* 보임 */
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* 스크롤바 스타일 */
    .toolbar::-webkit-scrollbar {
        height: 10px;
    }

    .toolbar::-webkit-scrollbar-track {
        background: #e0e0e0;
    }

    .toolbar::-webkit-scrollbar-thumb {
        background: var(--primary, #8a00fc);
        border-radius: 5px;
    }

    /* 버튼 공통 스타일 */
    .toolbar button,
    .toolbar-extended button {
        background: transparent;
        border: 1px solid transparent;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 4px;
        color: #555;
        font-size: 14px;
        white-space: nowrap;
    }

    .toolbar button:hover:not(:disabled),
    .toolbar-extended button:hover:not(:disabled) {
        background-color: #e2e6ea;
        color: #333;
    }

    .toolbar button.active,
    .toolbar-extended button.active {
        color: #0098f7;
        background-color: #e5f5ff;
        border-color: #b3d7ff;
    }

    .toolbar button:disabled {
        color: #ccc;
        cursor: not-allowed;
    }

    .separator {
        width: 2px;
        height: 20px;
        background-color: #ccc;
        margin: 0 5px;
        flex-shrink: 0;
    }

    /* 컬러 피커 */
    .color-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
    }



    #foreColorInput {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    /* 에디터 영역 */
    #editor,
    #sourceEditor {
        min-height: 400px;
        padding: 20px;
        outline: none;
        line-height: 1.6;
        color: #333;
        overflow-y: auto;
        width: 100%;
        box-sizing: border-box;
        font-family: inherit;
        font-size: inherit;
    }

    #sourceEditor {
        display: none;
        background-color: #282c34;
        color: #abb2bf;
        font-family: 'Courier New', Courier, monospace;
        resize: vertical;
        border: none;
    }


    #editor img {
        display: block;
        margin: 30px auto;
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    #editor blockquote {
        border-left: 4px solid #ccc;
        margin: 10px 0;
        padding-left: 15px;
        color: #666;
        background: #f9f9f9;
        padding: 10px;
    }

    #editor a {
        color: #0098f7;
        text-decoration: underline;
    }

    #editor:empty:before {
        content: '내용을 입력하세요...';
        color: #ccc;
        display: block;
    }

    @media (max-width: 768px) {

        #editor,
        #sourceEditor {
            min-height: 250px;
        }
    }
</style>

<script>
    const editor = document.getElementById('editor');
    const sourceEditor = document.getElementById('sourceEditor');
    const toolbar = document.querySelector('.toolbar');
    let isSourceMode = false;

    /* ---------------------------
        0. UI Toggle Logic
    ---------------------------- */
    // 확장 툴바 열기/닫기
    function toggleExtendedToolbar() {
        const extToolbar = document.getElementById('toolbarExtended');
        const btnMore = document.getElementById('btnMore');
        extToolbar.classList.toggle('show');

        if (extToolbar.classList.contains('show')) btnMore.classList.add('active');
        else btnMore.classList.remove('active');
    }

    // 소스 보기 모드 전환
    function toggleSource() {
        const btnSource = document.getElementById('btnSource');
        // 메인 툴바 + 확장 툴바 내 모든 버튼 비활성화 대상
        const allButtons = document.querySelectorAll('.editor-wrapper button:not(#btnSource), .color-wrapper input');

        if (!isSourceMode) {
            // Visual -> Code
            sourceEditor.value = editor.innerHTML.trim();
            editor.style.display = 'none';
            sourceEditor.style.display = 'block';
            btnSource.classList.add('active');
            allButtons.forEach(el => el.disabled = true);
            isSourceMode = true;
        } else {
            // Code -> Visual
            editor.innerHTML = sourceEditor.value;
            sourceEditor.style.display = 'none';
            editor.style.display = 'block';
            btnSource.classList.remove('active');
            allButtons.forEach(el => el.disabled = false);
            isSourceMode = false;
        }
    }

    /* ---------------------------
        1. Command Execution
    ---------------------------- */
    function execCmd(cmd, value = null) {
        if (isSourceMode) return;
        document.execCommand(cmd, false, value);
        editor.focus();
        updateToolbarState();
    }

    function formatBlock(tag) {
        execCmd("formatBlock", tag);
    }

    function createLink() {
        if (isSourceMode) return;
        const url = prompt('URL을 입력하세요:', 'http://');
        if (url) execCmd('createLink', url);
    }

    // Color Picker
    document.getElementById('foreColorInput').addEventListener('change', function(e) {
        execCmd('foreColor', e.target.value);
    });

    // Button Click Listeners (메인 + 확장 툴바 모두 적용)
    document.querySelectorAll('.editor-wrapper button[data-cmd]').forEach(btn => {
        btn.addEventListener('click', () => execCmd(btn.dataset.cmd));
    });

    /* ---------------------------
        2. Status Sync (Active Highlight)
    ---------------------------- */
    function updateToolbarState() {
        if (isSourceMode) return;
        // 메인 및 확장 툴바의 모든 커맨드 버튼 상태 확인
        document.querySelectorAll('.editor-wrapper button[data-cmd]').forEach(btn => {
            const cmd = btn.dataset.cmd;
            try {
                if (document.queryCommandState(cmd)) btn.classList.add('active');
                else btn.classList.remove('active');
            } catch {}
        });
    }

    ["keyup", "mouseup", "click"].forEach(ev => {
        editor.addEventListener(ev, updateToolbarState);
    });

    /* ---------------------------
        3. Paste Logic (이미지 업로드 + 텍스트 서식 유지)
    ---------------------------- */
    editor.addEventListener('paste', function(e) {
        const clipboardData = (e.originalEvent || e).clipboardData;

        if (clipboardData && clipboardData.items) {
            const items = clipboardData.items;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf("image") !== -1) {
                    // 이미지인 경우에만 기본 동작 막고 업로드 진행
                    e.preventDefault();
                    const file = items[i].getAsFile();
                    uploadImageFile(file);
                    return;
                }
            }
        }
        // 텍스트/HTML인 경우 브라우저 기본 동작 허용 (서식 유지됨)
    });

    /* ---------------------------
        4. Image / Video Logic
    ---------------------------- */
    function triggerImageUpload() {
        if (isSourceMode) return alert('소스 보기 모드 불가');
        document.getElementById('imageInput').click();
    }

    function uploadImage(input) {
        uploadImageFile(input.files[0]);
        input.value = '';
    }

    function uploadImageFile(file) {
        if (!file) return;
        const formData = new FormData();
        formData.append("file", file);

        const csrf = document.querySelector('input[name^="csrf_"]');
        if (csrf) formData.append(csrf.name, csrf.value);

        fetch('/board/upload_image', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // 필수!
                }
            })
            .then(r => r.json())
            .then(d => {
                // 성공 여부 확인 (d.success)
                if (d.success && d.payload && d.payload.url) {
                    editor.focus();

                    // 경로 수정 (d.payload.url)
                    document.execCommand('insertImage', false, d.payload.url);

                    // CSRF 토큰 갱신 (Response 서비스가 주는 토큰 사용)
                    if (d.token && csrf) {
                        csrf.value = d.token.value;
                    }
                } else {
                    alert('이미지 업로드 실패: ' + (d.message || '알 수 없는 오류'));
                }
            })
            .catch(e => {
                console.error(e);
                alert('서버 통신 오류');
            });
    }

    function insertVideo() {
        if (isSourceMode) return;
        const url = prompt('유튜브 URL 입력:');
        if (!url) return;

        const match = url.match(/^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/);
        const videoId = (match && match[2].length === 11) ? match[2] : null;

        if (videoId) {
            const html = `<div class="video-container"><iframe src="https://www.youtube.com/embed/${videoId}" allowfullscreen></iframe></div><p><br></p>`;
            editor.focus();
            document.execCommand('insertHTML', false, html);
        } else {
            alert('올바른 유튜브 URL이 아닙니다.');
        }
    }

    /* ---------------------------
        5. Form Submit Validation
    ---------------------------- */
    document.addEventListener("DOMContentLoaded", () => {
        const form = editor.closest('form');
        if (!form) return;
        form.setAttribute('novalidate', 'novalidate');

        form.addEventListener('submit', e => {
            const finalContent = isSourceMode ? sourceEditor.value : editor.innerHTML;
            const inputContent = form.querySelector('input[name="content"]');
            if (inputContent) inputContent.value = finalContent.trim();

            // 유효성 검사 (필요 시 주석 해제하여 사용)

            const title = form.querySelector('input[name="title"]');
            const writer = form.querySelector('input[name="writer"]');
            const pass = form.querySelector('input[name="password"]');

            if (title && !title.value.trim()) { e.preventDefault(); return alert('제목 입력 필요'); }
            if (writer && !writer.value.trim()) { e.preventDefault(); return alert('작성자 입력 필요'); }
            if (pass && !pass.value.trim()) { e.preventDefault(); return alert('비밀번호 입력 필요'); }

            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = finalContent;
            const textOnly = tempDiv.innerText.replace(/\n+/g, '').trim();
            const hasMedia = finalContent.includes('<img') || finalContent.includes('<iframe');

            if (!textOnly && !hasMedia) {
                e.preventDefault();
                alert('내용을 입력해주세요.');
                return;
            }

        });
    });
</script>
