document.addEventListener('DOMContentLoaded', function () {
    initializeRevealAnimations();
    initializeDismissibleAlerts(document);
    initializePasswordToggles();
    initializeLogoutConfirmation();
    initializeMedicamentApps();
    initializeCountUp();
});

function initializeRevealAnimations() {
    var revealElements = document.querySelectorAll('[data-reveal]');

    if (!('IntersectionObserver' in window)) {
        revealElements.forEach(function (element) {
            element.classList.add('is-visible');
        });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.15
    });

    revealElements.forEach(function (element, index) {
        element.style.transitionDelay = (index % 6) * 70 + 'ms';
        observer.observe(element);
    });
}

function initializeDismissibleAlerts(root) {
    var scope = root || document;

    scope.querySelectorAll('[data-alert]').forEach(function (alertElement) {
        if (alertElement.querySelector('[data-alert-close]')) {
            return;
        }

        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.setAttribute('data-alert-close', '');
        closeButton.className = 'ml-4 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 text-lg leading-none text-slate-200 transition hover:bg-white/10';
        closeButton.setAttribute('aria-label', 'Затвори съобщението');
        closeButton.innerHTML = '&times;';

        closeButton.addEventListener('click', function () {
            dismissAlert(alertElement);
        });

        var wrapper = document.createElement('div');
        wrapper.className = 'flex items-start justify-between gap-3';

        while (alertElement.firstChild) {
            wrapper.appendChild(alertElement.firstChild);
        }

        wrapper.appendChild(closeButton);
        alertElement.appendChild(wrapper);
    });
}

function dismissAlert(alertElement) {
    alertElement.classList.add('alert-leave');
    window.setTimeout(function () {
        alertElement.remove();
    }, 220);
}

function initializePasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var inputId = button.getAttribute('data-password-toggle');
            var input = document.getElementById(inputId);

            if (!input) {
                return;
            }

            var showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            button.textContent = showPassword ? 'Скрий' : 'Покажи';
            button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
        });
    });
}

function initializeLogoutConfirmation() {
    document.querySelectorAll('[data-confirm-logout]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var shouldContinue = window.confirm('Сигурни ли сте, че искате да излезете от профила?');

            if (!shouldContinue) {
                event.preventDefault();
            }
        });
    });
}

function initializeMedicamentApps() {
    document.querySelectorAll('[data-medicament-app]').forEach(function (root) {
        createMedicamentApp(root);
    });
}

function createMedicamentApp(root) {
    var apiEndpoint = root.getAttribute('data-api-endpoint');
    var csrfToken = root.getAttribute('data-csrf-token') || '';
    var shouldSyncSearchUrl = root.getAttribute('data-sync-search-url') === 'true';
    var searchParamName = root.getAttribute('data-search-param') || 'search';

    if (!apiEndpoint) {
        return;
    }

    var state = {
        query: root.getAttribute('data-initial-query') || '',
        records: parseJsonScript(root.querySelector('[data-records-json]')),
        totals: parseJsonScript(root.querySelector('[data-totals-json]')),
        pendingDeleteId: null,
        debounceId: null,
        isBusy: false
    };

    var elements = {
        alertHost: root.querySelector('[data-app-alerts]'),
        searchForm: root.querySelector('[data-remote-search-form]'),
        searchInput: root.querySelector('[data-remote-search-input]'),
        cardsWrap: root.querySelector('[data-record-cards]'),
        tableWrap: root.querySelector('[data-record-table-wrap]'),
        tableBody: root.querySelector('[data-record-rows]'),
        emptyState: root.querySelector('[data-records-empty]'),
        countTargets: root.querySelectorAll('[data-records-count]'),
        totalTargets: root.querySelectorAll('[data-total-count]'),
        expiredTargets: root.querySelectorAll('[data-expired-count]'),
        activeTargets: root.querySelectorAll('[data-active-count]'),
        editModal: root.querySelector('[data-record-modal]'),
        deleteModal: root.querySelector('[data-delete-modal]')
    };

    bindSearch();
    bindRecordActions();
    bindEditModal();
    bindDeleteModal();
    renderState();

    function bindSearch() {
        if (!elements.searchForm || !elements.searchInput) {
            return;
        }

        elements.searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            fetchRecords(elements.searchInput.value);
        });

        elements.searchInput.addEventListener('input', function () {
            window.clearTimeout(state.debounceId);
            state.debounceId = window.setTimeout(function () {
                fetchRecords(elements.searchInput.value);
            }, 280);
        });
    }

    function bindRecordActions() {
        root.addEventListener('click', function (event) {
            var editTrigger = event.target.closest('[data-edit-trigger]');
            var deleteTrigger = event.target.closest('[data-delete-trigger]');

            if (editTrigger) {
                var editId = Number(editTrigger.getAttribute('data-edit-trigger'));
                var record = findRecordById(editId);

                if (record) {
                    openEditModal(record);
                }
            }

            if (deleteTrigger) {
                var deleteId = Number(deleteTrigger.getAttribute('data-delete-trigger'));
                var deleteRecord = findRecordById(deleteId);

                if (deleteRecord) {
                    openDeleteModal(deleteRecord);
                }
            }
        });
    }

    function bindEditModal() {
        if (!elements.editModal) {
            return;
        }

        var form = elements.editModal.querySelector('[data-edit-form]');
        var closeButtons = elements.editModal.querySelectorAll('[data-modal-close]');

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeEditModal);
        });

        elements.editModal.addEventListener('click', function (event) {
            if (event.target === elements.editModal) {
                closeEditModal();
            }
        });

        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var formData = new FormData(form);
            formData.append('action', 'update');

            submitAction(formData, 'Промените са записани успешно.').then(function (payload) {
                if (!payload) {
                    return;
                }

                closeEditModal();
                fetchRecords(state.query, payload.message || 'Промените са записани успешно.', 'success');
            });
        });
    }

    function bindDeleteModal() {
        if (!elements.deleteModal) {
            return;
        }

        var confirmButton = elements.deleteModal.querySelector('[data-delete-confirm]');
        var cancelButton = elements.deleteModal.querySelector('[data-delete-cancel]');

        if (cancelButton) {
            cancelButton.addEventListener('click', closeDeleteModal);
        }

        elements.deleteModal.addEventListener('click', function (event) {
            if (event.target === elements.deleteModal) {
                closeDeleteModal();
            }
        });

        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                if (!state.pendingDeleteId) {
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', String(state.pendingDeleteId));

                submitAction(formData, 'Записът е изтрит успешно.').then(function (payload) {
                    if (!payload) {
                        return;
                    }

                    closeDeleteModal();
                    fetchRecords(state.query, payload.message || 'Записът е изтрит успешно.', 'success');
                });
            });
        }
    }

    function openEditModal(record) {
        if (!elements.editModal) {
            return;
        }

        elements.editModal.querySelector('[data-edit-id]').value = String(record.id);
        elements.editModal.querySelector('[data-edit-client]').value = record.client || '';
        elements.editModal.querySelector('[data-edit-address]').value = record.address || '';
        elements.editModal.querySelector('[data-edit-date-produce]').value = record.input_date_produce || '';
        elements.editModal.querySelector('[data-edit-date-expiri]').value = record.input_date_expiri || '';
        var newMedicamentInput = elements.editModal.querySelector('[data-edit-new-medicament]');
        if (newMedicamentInput) {
            newMedicamentInput.value = '';
        }
        setMedicamentSelection(elements.editModal.querySelectorAll('[data-edit-medicament]'), record.medicament_items || []);
        elements.editModal.querySelector('[data-modal-title]').textContent = 'Редакция на ' + record.client;
        elements.editModal.querySelector('[data-modal-subtitle]').textContent = record.medicament + ' • ' + record.address;
        showModal(elements.editModal);
    }

    function closeEditModal() {
        hideModal(elements.editModal);
    }

    function openDeleteModal(record) {
        if (!elements.deleteModal) {
            return;
        }

        state.pendingDeleteId = record.id;
        elements.deleteModal.querySelector('[data-delete-name]').textContent = record.client + ' / ' + record.medicament;
        showModal(elements.deleteModal);
    }

    function closeDeleteModal() {
        state.pendingDeleteId = null;
        hideModal(elements.deleteModal);
    }

    function submitAction(formData, fallbackMessage) {
        if (csrfToken && !formData.has('csrf_token')) {
            formData.append('csrf_token', csrfToken);
        }

        setBusy(true);

        return fetch(apiEndpoint, {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json'
            }
        })
            .then(parseJsonResponse)
            .then(function (payload) {
                return payload;
            })
            .catch(function (error) {
                showAlert(error.message || fallbackMessage, 'error');
                return null;
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function fetchRecords(query, successMessage, successType) {
        state.query = (query || '').trim();

        if (elements.searchInput && elements.searchInput.value !== state.query) {
            elements.searchInput.value = state.query;
        }

        if (shouldSyncSearchUrl) {
            syncSearchUrl(state.query);
        }

        setBusy(true);

        var url = apiEndpoint + '?search=' + encodeURIComponent(state.query);

        fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        })
            .then(parseJsonResponse)
            .then(function (payload) {
                state.records = Array.isArray(payload.records) ? payload.records : [];
                state.totals = payload.totals || { total: state.records.length, expired: 0, active: state.records.length };
                renderState();

                if (successMessage) {
                    showAlert(successMessage, successType || 'success');
                }
            })
            .catch(function (error) {
                showAlert(error.message || 'Заявката не беше успешна.', 'error');
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function renderState() {
        var records = Array.isArray(state.records) ? state.records : [];
        var totals = state.totals || { total: records.length, expired: 0, active: records.length };

        if (elements.cardsWrap) {
            elements.cardsWrap.innerHTML = records.map(renderCardMarkup).join('');
            elements.cardsWrap.classList.toggle('hidden', records.length === 0);
        }

        if (elements.tableBody) {
            elements.tableBody.innerHTML = records.map(renderRowMarkup).join('');
        }

        if (elements.tableWrap) {
            elements.tableWrap.classList.toggle('hidden', records.length === 0);
        }

        if (elements.emptyState) {
            elements.emptyState.textContent = root.getAttribute('data-empty-message') || 'Няма записи.';
            elements.emptyState.classList.toggle('hidden', records.length !== 0);
        }

        updateTextTargets(elements.countTargets, records.length);
        updateTextTargets(elements.totalTargets, totals.total);
        updateTextTargets(elements.expiredTargets, totals.expired);
        updateTextTargets(elements.activeTargets, totals.active);
    }

    function showAlert(message, type) {
        if (!elements.alertHost || !message) {
            return;
        }

        var colorMap = {
            success: 'rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-100',
            error: 'rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm font-medium text-rose-100',
            info: 'rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm font-medium text-cyan-100'
        };

        var alertElement = document.createElement('div');
        alertElement.setAttribute('data-alert', '');
        alertElement.className = colorMap[type] || colorMap.info;
        alertElement.textContent = message;

        elements.alertHost.prepend(alertElement);
        initializeDismissibleAlerts(elements.alertHost);
    }

    function findRecordById(recordId) {
        return state.records.find(function (record) {
            return Number(record.id) === Number(recordId);
        }) || null;
    }

    function setBusy(isBusy) {
        state.isBusy = isBusy;
        root.classList.toggle('is-busy', isBusy);

        if (elements.searchInput) {
            elements.searchInput.disabled = isBusy;
        }

        if (elements.searchForm) {
            var submitButton = elements.searchForm.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.disabled = isBusy;
            }
        }
    }

    function syncSearchUrl(query) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var nextUrl = new URL(window.location.href);

        if (query) {
            nextUrl.searchParams.set(searchParamName, query);
        } else {
            nextUrl.searchParams.delete(searchParamName);
            nextUrl.searchParams.delete('search');
        }

        window.history.replaceState({}, '', nextUrl.toString());
    }
}

function setMedicamentSelection(inputs, selectedItems) {
    var selected = Array.isArray(selectedItems) ? selectedItems : [];

    Array.prototype.forEach.call(inputs || [], function (input) {
        input.checked = selected.indexOf(input.value) !== -1;
    });
}

function renderCardMarkup(record) {
    return [
        '<article data-record data-record-id="', escapeHtml(record.id), '" data-search="', escapeHtml(record.search_text || ''), '" class="rounded-3xl border border-white/10 bg-white/5 p-5">',
        '<div class="flex items-start justify-between gap-4">',
        '<div>',
        '<p class="text-xs uppercase tracking-[0.25em] text-slate-400">Запис #', escapeHtml(record.id), '</p>',
        '<h3 class="mt-2 text-lg font-semibold text-white">', escapeHtml(record.client), '</h3>',
        '<p class="mt-1 text-sm text-slate-300">', escapeHtml(record.address), '</p>',
        '</div>',
        '<span class="', statusBadgeClasses(Boolean(record.is_expired)), '">', escapeHtml(record.status_text), '</span>',
        '</div>',
        '<dl class="mt-4 grid gap-3 text-sm text-slate-300">',
        '<div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Медикамент</dt><dd class="text-right text-slate-100">', escapeHtml(record.medicament), '</dd></div>',
        '<div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Производство</dt><dd class="text-right text-slate-100">', escapeHtml(record.formatted_date_produce), '</dd></div>',
        '<div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Срок</dt><dd class="text-right text-slate-100">', escapeHtml(record.formatted_date_expiri), '</dd></div>',
        '</dl>',
        '<div class="mt-5 grid gap-3 sm:grid-cols-2">',
        '<button type="button" data-edit-trigger="', escapeHtml(record.id), '" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-300/40 hover:bg-white/10 focus:outline-none focus:ring-4 focus:ring-white/10 w-full">Промени</button>',
        '<button type="button" data-delete-trigger="', escapeHtml(record.id), '" class="inline-flex items-center justify-center rounded-full border border-amber-300/30 bg-amber-400/10 px-5 py-3 text-sm font-semibold text-amber-100 transition hover:bg-amber-400/20 focus:outline-none focus:ring-4 focus:ring-amber-300/20 w-full">Изтрий</button>',
        '</div>',
        '</article>'
    ].join('');
}

function renderRowMarkup(record) {
    return [
        '<tr data-record data-record-id="', escapeHtml(record.id), '" data-search="', escapeHtml(record.search_text || ''), '" class="transition hover:bg-white/5">',
        '<td class="px-6 py-4 text-slate-400">', escapeHtml(record.id), '</td>',
        '<td class="px-6 py-4 font-semibold text-white">', escapeHtml(record.client), '</td>',
        '<td class="px-6 py-4 text-slate-300">', escapeHtml(record.address), '</td>',
        '<td class="px-6 py-4 text-slate-200">', escapeHtml(record.medicament), '</td>',
        '<td class="px-6 py-4">', escapeHtml(record.formatted_date_produce), '</td>',
        '<td class="px-6 py-4">', escapeHtml(record.formatted_date_expiri), '</td>',
        '<td class="px-6 py-4"><span class="', statusBadgeClasses(Boolean(record.is_expired)), '">', escapeHtml(record.status_text), '</span></td>',
        '<td class="px-6 py-4"><div class="flex gap-3">',
        '<button type="button" data-edit-trigger="', escapeHtml(record.id), '" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-300/40 hover:bg-white/10 focus:outline-none focus:ring-4 focus:ring-white/10">Промени</button>',
        '<button type="button" data-delete-trigger="', escapeHtml(record.id), '" class="inline-flex items-center justify-center rounded-full border border-amber-300/30 bg-amber-400/10 px-5 py-3 text-sm font-semibold text-amber-100 transition hover:bg-amber-400/20 focus:outline-none focus:ring-4 focus:ring-amber-300/20">Изтрий</button>',
        '</div></td>',
        '</tr>'
    ].join('');
}

function statusBadgeClasses(isExpired) {
    if (isExpired) {
        return 'inline-flex items-center rounded-full border border-rose-400/20 bg-rose-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-rose-100';
    }

    return 'inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100';
}

function showModal(modalElement) {
    if (!modalElement) {
        return;
    }

    modalElement.classList.remove('hidden');
    modalElement.classList.add('flex');
}

function hideModal(modalElement) {
    if (!modalElement) {
        return;
    }

    modalElement.classList.add('hidden');
    modalElement.classList.remove('flex');
}

function parseJsonResponse(response) {
    return response.json().catch(function () {
        return {};
    }).then(function (payload) {
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || 'Заявката не беше успешна.');
        }

        return payload;
    });
}

function parseJsonScript(element) {
    if (!element) {
        return [];
    }

    try {
        return JSON.parse(element.textContent || '[]');
    } catch (error) {
        return [];
    }
}

function updateTextTargets(nodeList, value) {
    if (!nodeList) {
        return;
    }

    Array.prototype.forEach.call(nodeList, function (node) {
        node.textContent = String(value);
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function initializeCountUp() {
    document.querySelectorAll('[data-count-up]').forEach(function (element) {
        var targetValue = Number(element.getAttribute('data-count-up'));

        if (!Number.isFinite(targetValue)) {
            return;
        }

        var duration = 700;
        var startTime = null;

        var render = function (timestamp) {
            if (startTime === null) {
                startTime = timestamp;
            }

            var progress = Math.min((timestamp - startTime) / duration, 1);
            var currentValue = Math.round(targetValue * progress);
            element.textContent = String(currentValue);

            if (progress < 1) {
                window.requestAnimationFrame(render);
            }
        };

        window.requestAnimationFrame(render);
    });
}