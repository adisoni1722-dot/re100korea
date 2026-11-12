/* ========================================================= */
/* 본문 텍스트 복사 기능 (copyContent)                       */
/* ========================================================= */
function copyContent() {
    const contentDiv = document.querySelector('#board .content');

    if (!contentDiv) {
        alert('복사할 본문 내용을 찾을 수 없습니다.');
        return;
    }

    const textToCopy = contentDiv.innerText;
    const textArea = document.createElement("textarea");
    textArea.value = textToCopy;

    textArea.style.position = "fixed";
    textArea.style.left = "-9999px";
    document.body.appendChild(textArea);

    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert('본문 내용이 클립보드에 복사되었습니다.');
        } else {
            alert('복사에 실패했습니다.');
        }
    } catch (err) {
        console.error('복사 중 오류 발생:', err);
        alert('이 브라우저에서는 복사 기능을 지원하지 않습니다.');
    }

    document.body.removeChild(textArea);
}

/* ========================================================= */
/* URL 복사 기능 (copyUrl)                                   */
/* ========================================================= */
function copyUrl() {
    const url = window.location.href;

    const textArea = document.createElement("textarea");
    textArea.value = url;
    textArea.style.position = "fixed";
    textArea.style.left = "-9999px";
    document.body.appendChild(textArea);

    textArea.select();

    try {
        document.execCommand('copy');
        alert('주소가 복사되었습니다.');
    } catch (err) {
        alert('주소 복사에 실패했습니다.');
    }

    document.body.removeChild(textArea);
}

// ==========================================
// 2. Write Page Logic (쓰기 화면)
// ==========================================

// 기존 파일 삭제 (Async/Await)
window.deleteExistingFile = async function(fileId) {
    if (typeof BOARD_CONFIG === 'undefined') {
        console.error('BOARD_CONFIG가 정의되지 않았습니다.');
        return;
    }

    if (!confirm('정말 이 파일을 삭제하시겠습니까? 삭제 후에는 복구할 수 없습니다.')) return;

    const csrfInput = document.querySelector(`input[name="${BOARD_CONFIG.CSRF_NAME}"]`);

    const formData = new FormData();
    formData.append('file_id', fileId);
    formData.append(BOARD_CONFIG.CSRF_NAME, csrfInput.value);

    try {
        const response = await fetch('/board/delete_file', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.token) {
            const tokenName = data.token.name;
            const tokenValue = data.token.value;
            document.querySelectorAll(`input[name="${tokenName}"]`)
                .forEach(el => el.value = tokenValue);
        }

        if (data.success) {
            const item = document.getElementById(`existing_file_${fileId}`);
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateY(-10px)';
                setTimeout(() => item.remove(), 300);
            }
        } else {
            alert(`삭제 실패: ${data.message || '오류 발생'}`);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('서버 통신 오류가 발생했습니다.');
    }
};

// 새 파일 업로드 관리 초기화 함수
function initFileUpload() {
    const dropZone = document.getElementById('drop_zone');
    if (!dropZone) return;

    if (typeof BOARD_CONFIG === 'undefined') {
        window.BOARD_CONFIG = { MAX_FILES: 5 };
    }

    const selectedFiles = [];
    const realInput = document.getElementById('real_file_upload');
    const listView = document.getElementById('file_list_view');
    // [수정됨] 라벨 엘리먼트 가져오기
    const newFileLabel = document.getElementById('new_file_label');

    const ICON_MAP = {
        img: { cls: 'img', icon: 'fa-file-image', exts: ['jpg', 'jpeg', 'png', 'gif', 'webp'] },
        pdf: { cls: 'pdf', icon: 'fa-file-pdf', exts: ['pdf'] },
        doc: { cls: 'doc', icon: 'fa-file-word', exts: ['doc', 'docx', 'hwp', 'hwpx'] },
        xls: { cls: 'xls', icon: 'fa-file-excel', exts: ['xls', 'xlsx', 'csv'] },
        ppt: { cls: 'ppt', icon: 'fa-file-powerpoint', exts: ['ppt', 'pptx'] },
        zip: { cls: 'zip', icon: 'fa-file-archive', exts: ['zip', 'rar', '7z', 'tar', 'gz'] },
        txt: { cls: 'txt', icon: 'fa-file-alt', exts: ['txt'] },
        default: { cls: 'default', icon: 'fa-file' }
    };

    const getFileInfo = (filename) => {
        const ext = filename.split('.').pop().toLowerCase();
        return Object.values(ICON_MAP).find(t => t.exts && t.exts.includes(ext)) || ICON_MAP.default;
    };

    const formatSize = (bytes) => {
        return bytes < 1048576 ?
            (bytes / 1024).toFixed(1) + " KB" :
            (bytes / 1048576).toFixed(1) + " MB";
    };

    const addFiles = (newFiles) => {
        let isUpdated = false;
        Array.from(newFiles).forEach(file => {
            if (selectedFiles.length >= BOARD_CONFIG.MAX_FILES) {
                alert(`최대 ${BOARD_CONFIG.MAX_FILES}개까지만 업로드 가능합니다.`);
                return;
            }
            if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                alert(`[${file.name}] 파일은 이미 추가되었습니다.`);
                return;
            }
            selectedFiles.push(file);
            isUpdated = true;
        });
        if (isUpdated) updateInputAndRender();
    };

    const updateInputAndRender = () => {
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        realInput.files = dataTransfer.files;
        render();
    };

    const render = () => {
        listView.innerHTML = '';
        const fragment = document.createDocumentFragment();

        selectedFiles.forEach((file, index) => {
            const { cls, icon } = getFileInfo(file.name);
            const div = document.createElement('div');
            div.className = 'file-item';
            div.innerHTML = `
                <div class="icon-box ${cls}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="file-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${formatSize(file.size)}</span>
                </div>
                <button type="button" class="btn-remove" data-index="${index}" title="취소">
                    <i class="fas fa-times"></i>
                </button>
            `;
            fragment.appendChild(div);
        });

        listView.appendChild(fragment);

        // [수정됨] 파일 개수에 따라 라벨 표시/숨김 처리
        if (newFileLabel) {
            if (selectedFiles.length > 0) {
                newFileLabel.style.display = 'flex'; // CSS에 맞춰 flex로 설정
            } else {
                newFileLabel.style.display = 'none';
            }
        }
    };

    // 이벤트 리스너 등록
    const fileSelector = document.getElementById('file_selector');
    if (fileSelector) {
        fileSelector.addEventListener('change', (e) => {
            addFiles(e.target.files);
            e.target.value = '';
        });
    } else {
        window.handleFileSelect = (input) => {
            addFiles(input.files);
            input.value = '';
        }
    }

    listView.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remove');
        if (btn) {
            const index = parseInt(btn.dataset.index);
            selectedFiles.splice(index, 1);
            updateInputAndRender();
        }
    });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'));
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'));
    });

    dropZone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files.length > 0) {
            addFiles(e.dataTransfer.files);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initFileUpload();
});
