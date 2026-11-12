/**
 * 게시판 공통 스크립트 (View & Write)
 */

// ==========================================
// 1. View Page Logic (읽기 화면)
// ==========================================

function copyUrl() {
    var url = window.location.href;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert("주소가 복사되었습니다.");
        }).catch(function() {
            alert("복사에 실패했습니다.");
        });
    } else {
        var textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert("주소가 복사되었습니다.");
    }
}

function copyContent() {
    var contentElement = document.querySelector('.post-content');
    if (!contentElement) {
        alert("본문 내용을 찾을 수 없습니다.");
        return;
    }
    var contentText = contentElement.innerText;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(contentText).then(function() {
            alert("본문 내용이 복사되었습니다.");
        }).catch(function() {
            alert("복사에 실패했습니다.");
        });
    } else {
        var textarea = document.createElement('textarea');
        textarea.value = contentText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert("본문 내용이 복사되었습니다.");
    }
}


// ==========================================
// 2. Write Page Logic (쓰기 화면)
// ==========================================

// 기존 파일 삭제 (Async/Await)
// HTML onclick에서 호출하므로 window 객체에 할당
window.deleteExistingFile = async function(fileId) {
    if (typeof BOARD_CONFIG === 'undefined') {
        console.error('BOARD_CONFIG가 정의되지 않았습니다.');
        return;
    }

    if (!confirm('정말 이 파일을 삭제하시겠습니까? 삭제 후에는 복구할 수 없습니다.')) return;

    // 현재 페이지의 토큰 값 가져오기
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

        // CSRF 토큰 갱신 로직
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
    if (!dropZone) return; // 드롭존이 없으면 실행 중단 (View 페이지 등)

    if (typeof BOARD_CONFIG === 'undefined') {
        console.warn('BOARD_CONFIG가 설정되지 않아 기본값을 사용합니다.');
        window.BOARD_CONFIG = { MAX_FILES: 5 };
    }

    const selectedFiles = [];
    const realInput = document.getElementById('real_file_upload');
    const listView = document.getElementById('file_list_view');

    // 파일 확장자별 아이콘 매핑
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
        const type = Object.values(ICON_MAP).find(t => t.exts && t.exts.includes(ext)) || ICON_MAP.default;
        return type;
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
    };

    // 이벤트 리스너 등록
    const fileSelector = document.getElementById('file_selector');
    if (fileSelector) {
        fileSelector.addEventListener('change', (e) => {
            addFiles(e.target.files);
            e.target.value = '';
        });
    } else {
        // Fallback for old implementations
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

// DOM 로드 시 실행
document.addEventListener('DOMContentLoaded', () => {
    initFileUpload();
});
