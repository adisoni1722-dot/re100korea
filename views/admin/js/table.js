// --- Interactive Table Script ---
if (!document.getElementById('interactive-table-styles')) {
    const style = document.createElement('style');
    style.id = 'interactive-table-styles';
    style.textContent = `
            @media (max-width: 767px) {
                .interactive-table-total-count {
                    display: none;
                }
            }
        `;
    document.head.appendChild(style);
}

document.querySelectorAll('[data-table-interactive]').forEach((container) => {
    const searchInput = container.querySelector('[data-search-input]');
    const tableBody = container.querySelector('[data-table-body]');
    const paginationNav = container.querySelector('[data-pagination-nav]');
    const totalCountEl = container.querySelector('[data-total-count]');
    const tableHeaders = container.querySelectorAll('th[data-sort]');
    const allTh = container.querySelectorAll('th');
    const tableElement = container.querySelector('.list-table');
    const emptyStateEl = container.querySelector('.empty-state');

    if (!searchInput || !tableBody || !paginationNav || !totalCountEl || !tableElement || !emptyStateEl) {
        console.error('테이블 초기화에 필요한 요소를 찾을 수 없습니다. data-* 속성 또는 클래스를 확인해주세요.');
        return;
    }

    totalCountEl.classList.add('interactive-table-total-count');

    const rowsPerPage = 6;
    const maxPageLinksToShow = 5;

    const searchableColumnIndices = [];
    allTh.forEach((th, index) => {
        if (th.dataset.search !== undefined) {
            searchableColumnIndices.push(index);
        }
    });

    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    let currentPage = 1;
    let filteredRows = [...allRows];
    let sortColumnIndex;
    let sortDirection = 'asc';

    function sortRows() {
        if (sortColumnIndex === undefined) return;

        const currentHeader = Array.from(allTh).find((th) => th.cellIndex === sortColumnIndex);
        const sortType = currentHeader?.dataset.sortType;

        filteredRows.sort((a, b) => {
            let cellA = a.cells[sortColumnIndex]?.textContent.trim() || '';
            let cellB = b.cells[sortColumnIndex]?.textContent.trim() || '';

            if (sortType === 'number') {
                const numA = parseFloat(cellA.replace(/[^0-9.-]+/g, '')) || 0;
                const numB = parseFloat(cellB.replace(/[^0-9.-]+/g, '')) || 0;
                return sortDirection === 'asc' ? numA - numB : numB - numA;
            }

            const comparison = cellA.localeCompare(cellB, 'ko');
            return sortDirection === 'desc' ? comparison * -1 : comparison;
        });
    }

    function updateSortIndicators() {
        tableHeaders.forEach((header) => {
            const icon = header.querySelector('.sort-icon');
            if (icon) {
                icon.className = 'sort-icon'; // Reset class
                if (header.cellIndex === sortColumnIndex) {
                    if (sortDirection === 'asc') {
                        icon.classList.add('fa-solid', 'fa-arrow-up-short-wide');
                    } else {
                        icon.classList.add('fa-solid', 'fa-arrow-down-wide-short');
                    }
                }
            }
        });
    }

    function renderPagination() {
        paginationNav.innerHTML = '';
        const totalEntries = filteredRows.length;

        if (totalEntries > 0) {
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, totalEntries);
            totalCountEl.textContent = `총 ${totalEntries}개 중 ${startIndex + 1} - ${endIndex} 표시`;
        } else {
            totalCountEl.textContent = '총 0개';
        }

        const pageCount = Math.ceil(totalEntries / rowsPerPage);
        if (pageCount <= 1) return;

        let startPage, endPage;
        if (pageCount <= maxPageLinksToShow) {
            startPage = 1;
            endPage = pageCount;
        } else {
            const maxPagesBeforeCurrent = Math.floor(maxPageLinksToShow / 2);
            const maxPagesAfterCurrent = Math.ceil(maxPageLinksToShow / 2) - 1;
            if (currentPage <= maxPagesBeforeCurrent) {
                startPage = 1;
                endPage = maxPageLinksToShow;
            } else if (currentPage + maxPagesAfterCurrent >= pageCount) {
                startPage = pageCount - maxPageLinksToShow + 1;
                endPage = pageCount;
            } else {
                startPage = currentPage - maxPagesBeforeCurrent;
                endPage = currentPage + maxPagesAfterCurrent;
            }
        }

        const createPageLink = (text, pageNum, isDisabled = false, isActive = false) => {
            const link = document.createElement('a');
            link.href = '#';
            link.innerHTML = text;
            if (isDisabled) link.classList.add('disabled');
            if (isActive) link.classList.add('active');

            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (!isDisabled) {
                    currentPage = pageNum;
                    renderTable();
                }
            });
            return link;
        };

        paginationNav.appendChild(createPageLink('&laquo;', currentPage - 1, currentPage === 1));

        for (let i = startPage; i <= endPage; i++) {
            paginationNav.appendChild(createPageLink(i, i, false, i === currentPage));
        }

        paginationNav.appendChild(createPageLink('&raquo;', currentPage + 1, currentPage === pageCount));
    }

    function renderTable() {
        sortRows();
        updateSortIndicators();

        tableBody.innerHTML = '';
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const pageRows = filteredRows.slice(startIndex, endIndex);

        if (pageRows.length === 0) {
            tableElement.style.display = 'none';
            emptyStateEl.style.display = 'block';
        } else {
            tableElement.style.display = 'table';
            emptyStateEl.style.display = 'none';
            pageRows.forEach((row) => {
                tableBody.appendChild(row);
            });
        }

        renderPagination();
    }

    searchInput.addEventListener('keyup', () => {
        const filter = searchInput.value.toLowerCase();
        filteredRows = allRows.filter((row) => {
            return searchableColumnIndices.some((index) => {
                const cell = row.cells[index];
                return cell ? cell.textContent.toLowerCase().includes(filter) : false;
            });
        });
        currentPage = 1;
        renderTable();
    });

    tableHeaders.forEach((header) => {
        header.addEventListener('click', () => {
            const clickedIndex = header.cellIndex;
            if (sortColumnIndex === clickedIndex) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumnIndex = clickedIndex;
                sortDirection = 'asc';
            }
            renderTable();
        });
    });

    tableBody.addEventListener('click', function (event) {
        const row = event.target.closest('tr');
        if (!row) return;

        // 클릭한 요소가 아래 중 하나라면 tr 클릭 무시
        if (
            event.target.closest('button') ||
            event.target.closest('a') ||
            event.target.closest('input') ||
            event.target.closest('select') ||
            event.target.closest('[data-modal-type]') ||
            event.target.closest('.btn') ||
             event.target.closest('.btn-action') ||
             event.target.closest('select')

        ) {
            return;
        }

        // 정상적인 행 이동만 허용
        const href = row.dataset.href;
        if (href) window.location.href = href;
    });

    renderTable();
});
