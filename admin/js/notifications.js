/**
 * Notifications Management Module
 */
(function () {
    'use strict';

    var EVENT_LABELS = {
        'task.created': 'Task Created',
        'task.assigned': 'Task Assigned to Worker',
        'task.status_changed': 'Task Status Updated',
        'task.due_soon': 'Task Due Soon Reminder',
        'service_request.created': 'Service Request Submitted',
        'reservation.confirmed': 'Worker Reservation Confirmed',
        'reservation.cancelled': 'Worker Reservation Cancelled',
        'absence.requested': 'Time Off / Absence Requested',
        'absence.decided': 'Time Off Request Decided',
        'invoice.issued': 'Invoice Issued',
        'invoice.overdue': 'Invoice Overdue Notice',
        'document.delivery': 'Document Sent / Delivered',
        'phone.message': 'Voicemail / Message Taken',
        'voice.pin_locked': 'Voice Security PIN Locked',
        'expense.registered': 'Field Expense Registered'
    };

    var EVENT_ICONS = {
        'task': { icon: 'fa-tasks', bg: 'rgba(54, 162, 235, 0.15)', color: '#36a2eb' },
        'service_request': { icon: 'fa-wrench', bg: 'rgba(255, 159, 64, 0.15)', color: '#ff9f40' },
        'reservation': { icon: 'fa-calendar-check-o', bg: 'rgba(75, 192, 192, 0.15)', color: '#4bc0c0' },
        'absence': { icon: 'fa-calendar-times-o', bg: 'rgba(153, 102, 255, 0.15)', color: '#9966ff' },
        'invoice': { icon: 'fa-file-text-o', bg: 'rgba(46, 204, 113, 0.15)', color: '#2ecc71' },
        'expense': { icon: 'fa-usd', bg: 'rgba(239, 35, 60, 0.15)', color: '#ef233c' },
        'phone': { icon: 'fa-phone', bg: 'rgba(241, 196, 15, 0.15)', color: '#f1c40f' },
        'voice': { icon: 'fa-lock', bg: 'rgba(231, 76, 60, 0.15)', color: '#e74c3c' },
        'document': { icon: 'fa-envelope-o', bg: 'rgba(52, 152, 219, 0.15)', color: '#3498db' }
    };

    var state = {
        page: 1,
        perPage: 15,
        unreadOnly: false,
        eventType: ''
    };

    function getCsrfToken() {
        return (window.pgs && window.pgs.csrfToken) ||
            (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
    }

    function getEventIcon(eventType) {
        var prefix = eventType.split('.')[0];
        return EVENT_ICONS[prefix] || { icon: 'fa-bell', bg: 'rgba(255,255,255,0.1)', color: '#fff' };
    }

    function onTabShown(link, callback) {
        if (window.jQuery) {
            window.jQuery(link).on('shown.bs.tab', callback);
        } else {
            link.addEventListener('shown.bs.tab', callback);
        }
    }

    // --- TAB 1: INBOX ---
    function loadInbox() {
        var tbody = document.getElementById('inboxListBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading notifications...</td></tr>';

        var params = new URLSearchParams({
            op: 'ntf_list',
            page: state.page,
            per_page: state.perPage
        });
        if (state.unreadOnly) params.append('unread_only', '1');
        if (state.eventType) params.append('event_type', state.eventType);

        fetch('admin/ajax/rqst.php?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">' + ((res.error && res.error.message) || res.error || 'Error loading notifications') + '</td></tr>';
                    return;
                }

                var data = res.data;
                renderInbox(data);
                updateBadge(data.unread_count);
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Network error loading notifications.</td></tr>';
            });
    }

    function renderInbox(data) {
        var tbody = document.getElementById('inboxListBody');
        var items = data.items || [];

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="fa fa-inbox fa-3x mb-3 d-block text-secondary"></i>No notifications found.</td></tr>';
            renderPagination(0, 1, 1);
            return;
        }

        var html = '';
        items.forEach(function (item) {
            var isUnread = !item.read_at;
            var unreadCls = isUnread ? 'ntf-unread-row' : '';
            var iconMeta = getEventIcon(item.event_type || '');
            var linkHtml = item.link_url
                ? '<a href="' + item.link_url + '" class="btn btn-xs btn-outline-info ml-2"><i class="fa fa-external-link"></i> View</a>'
                : '';

            html += '<tr class="' + unreadCls + '">';
            html += '<td><div class="ntf-item-icon" style="background:' + iconMeta.bg + '; color:' + iconMeta.color + ';"><i class="fa ' + iconMeta.icon + '"></i></div></td>';
            html += '<td>' +
                '<div class="font-weight-bold text-white mb-1" style="font-size:14px;">' + (item.title || '') + '</div>' +
                '<div class="text-muted small">' + (item.body || '') + '</div>' +
                '</td>';
            html += '<td class="text-muted small"><i class="fa fa-clock-o mr-1"></i>' + (item.created_at || '').substring(0, 16) + '</td>';
            html += '<td class="text-right">' +
                (isUnread ? '<button type="button" class="btn btn-xs btn-outline-secondary btn-mark-read" data-id="' + item.id + '" title="Mark as read"><i class="fa fa-check"></i></button>' : '') +
                linkHtml +
                '</td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;

        // Wire mark read buttons
        tbody.querySelectorAll('.btn-mark-read').forEach(function (btn) {
            btn.addEventListener('click', function () {
                markAsRead(btn.getAttribute('data-id'));
            });
        });

        renderPagination(data.total, data.page, data.pages);
    }

    function renderPagination(total, page, pages) {
        var info = document.getElementById('inboxPaginationInfo');
        var nav = document.getElementById('inboxPaginationNav');

        info.textContent = 'Page ' + page + ' of ' + (pages || 1) + ' (' + total + ' total)';

        if (pages <= 1) {
            nav.innerHTML = '';
            return;
        }

        var html = '';
        html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page - 1) + '">‹</a></li>';
        for (var p = 1; p <= pages; p++) {
            if (p === 1 || p === pages || (p >= page - 2 && p <= page + 2)) {
                html += '<li class="page-item ' + (p === page ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
            } else if (p === page - 3 || p === page + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        html += '<li class="page-item ' + (page >= pages ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page + 1) + '">›</a></li>';

        nav.innerHTML = html;
        nav.querySelectorAll('a[data-page]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var p = parseInt(a.getAttribute('data-page'), 10);
                if (p && p !== state.page) {
                    state.page = p;
                    loadInbox();
                }
            });
        });
    }

    function markAsRead(id) {
        var csrf = getCsrfToken();
        var body = new URLSearchParams({ op: 'ntf_mark_read', id: id });
        if (csrf) body.append('csrf_token', csrf);

        fetch('admin/ajax/rqst.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    loadInbox();
                }
            });
    }

    function markAllAsRead() {
        var csrf = getCsrfToken();
        var body = new URLSearchParams({ op: 'ntf_mark_all_read' });
        if (csrf) body.append('csrf_token', csrf);

        fetch('admin/ajax/rqst.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    loadInbox();
                    if (window.pgs && window.pgs.toast) {
                        window.pgs.toast('All notifications marked as read', 'success');
                    }
                }
            });
    }

    function updateBadge(count) {
        var badge = document.getElementById('inboxUnreadBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    // --- TAB 2: PREFERENCES ---
    function loadPreferences() {
        var tbody = document.getElementById('prefsTableBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading preferences...</td></tr>';

        fetch('admin/ajax/rqst.php?op=ntf_prefs_get')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error loading preferences.</td></tr>';
                    return;
                }
                renderPreferences(res.data.preferences || {});
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Network error loading preferences.</td></tr>';
            });
    }

    function renderPreferences(prefs) {
        var tbody = document.getElementById('prefsTableBody');
        var html = '';

        Object.keys(EVENT_LABELS).forEach(function (evtKey) {
            var label = EVENT_LABELS[evtKey];
            var chData = prefs[evtKey] || { inapp: true, email: false, whatsapp: false };
            var iconMeta = getEventIcon(evtKey);

            html += '<tr>';
            html += '<td>' +
                '<span class="mr-2" style="color:' + iconMeta.color + '"><i class="fa ' + iconMeta.icon + '"></i></span>' +
                '<strong>' + label + '</strong>' +
                '<div class="text-muted small font-italic">' + evtKey + '</div>' +
                '</td>';
            html += '<td class="text-center">' +
                '<div class="custom-control custom-switch d-inline-block">' +
                '<input type="checkbox" class="custom-control-input pref-toggle" id="pref_' + evtKey.replace('.', '_') + '_inapp" data-event="' + evtKey + '" data-channel="inapp" ' + (chData.inapp ? 'checked' : '') + '>' +
                '<label class="custom-control-label" for="pref_' + evtKey.replace('.', '_') + '_inapp"></label>' +
                '</div></td>';
            html += '<td class="text-center">' +
                '<div class="custom-control custom-switch d-inline-block">' +
                '<input type="checkbox" class="custom-control-input pref-toggle" id="pref_' + evtKey.replace('.', '_') + '_email" data-event="' + evtKey + '" data-channel="email" ' + (chData.email ? 'checked' : '') + '>' +
                '<label class="custom-control-label" for="pref_' + evtKey.replace('.', '_') + '_email"></label>' +
                '</div></td>';
            html += '<td class="text-center">' +
                '<div class="custom-control custom-switch d-inline-block">' +
                '<input type="checkbox" class="custom-control-input pref-toggle" id="pref_' + evtKey.replace('.', '_') + '_wa" data-event="' + evtKey + '" data-channel="whatsapp" ' + (chData.whatsapp ? 'checked' : '') + '>' +
                '<label class="custom-control-label" for="pref_' + evtKey.replace('.', '_') + '_wa"></label>' +
                '</div></td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    function savePreferences() {
        var toggles = document.querySelectorAll('.pref-toggle');
        var payload = {};

        toggles.forEach(function (toggle) {
            var evt = toggle.getAttribute('data-event');
            var ch = toggle.getAttribute('data-channel');
            if (!payload[evt]) payload[evt] = {};
            payload[evt][ch] = toggle.checked ? 1 : 0;
        });

        var csrf = getCsrfToken();
        var body = new URLSearchParams({
            op: 'ntf_prefs_save',
            preferences: JSON.stringify(payload)
        });
        if (csrf) body.append('csrf_token', csrf);

        var btn = document.getElementById('btnSavePrefs');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving...';

        fetch('admin/ajax/rqst.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save mr-1"></i> Save Preferences';
                if (res.ok) {
                    if (window.pgs && window.pgs.toast) {
                        window.pgs.toast('Notification preferences saved successfully', 'success');
                    } else {
                        PGS.notify('Preferences saved successfully.');
                    }
                } else {
                    PGS.notify((res.error && res.error.message) || res.error || 'Error saving preferences');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save mr-1"></i> Save Preferences';
                PGS.notify('Network error saving preferences.');
            });
    }

    // --- TAB 3: DEFAULTS ---
    function loadDefaults() {
        fetch('admin/ajax/rqst.php?op=ntf_defaults_get')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok && res.data.defaults) {
                    renderDefaultsMatrix('SuperAdministrador', 'defaultsSuperBody', res.data.defaults.SuperAdministrador || {});
                    renderDefaultsMatrix('Administrador', 'defaultsAdminBody', res.data.defaults.Administrador || {});
                    renderDefaultsMatrix('Staff', 'defaultsStaffBody', res.data.defaults.Staff || {});
                }
            });
    }

    function renderDefaultsMatrix(userType, containerId, eventsMap) {
        var tbody = document.getElementById(containerId);
        if (!tbody) return;

        var html = '';
        Object.keys(EVENT_LABELS).forEach(function (evtKey) {
            var label = EVENT_LABELS[evtKey];
            var chData = eventsMap[evtKey] || { inapp: false, email: false, whatsapp: false };
            var idPrefix = 'def_' + userType + '_' + evtKey.replace('.', '_');

            html += '<tr>';
            html += '<td><strong>' + label + '</strong><div class="text-muted small">' + evtKey + '</div></td>';
            html += '<td class="text-center">' +
                '<div class="custom-control custom-switch d-inline-block">' +
                '<input type="checkbox" class="custom-control-input def-toggle" id="' + idPrefix + '_inapp" data-type="' + userType + '" data-event="' + evtKey + '" data-channel="inapp" ' + (chData.inapp ? 'checked' : '') + '>' +
                '<label class="custom-control-label" for="' + idPrefix + '_inapp"></label>' +
                '</div></td>';
            html += '<td class="text-center">' +
                '<div class="custom-control custom-switch d-inline-block">' +
                '<input type="checkbox" class="custom-control-input def-toggle" id="' + idPrefix + '_email" data-type="' + userType + '" data-event="' + evtKey + '" data-channel="email" ' + (chData.email ? 'checked' : '') + '>' +
                '<label class="custom-control-label" for="' + idPrefix + '_email"></label>' +
                '</div></td>';
            html += '<td class="text-center">' +
                '<div class="custom-control custom-switch d-inline-block">' +
                '<input type="checkbox" class="custom-control-input def-toggle" id="' + idPrefix + '_wa" data-type="' + userType + '" data-event="' + evtKey + '" data-channel="whatsapp" ' + (chData.whatsapp ? 'checked' : '') + '>' +
                '<label class="custom-control-label" for="' + idPrefix + '_wa"></label>' +
                '</div></td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    function saveDefaults() {
        var toggles = document.querySelectorAll('.def-toggle');
        var payload = {
            SuperAdministrador: {},
            Administrador: {},
            Staff: {}
        };

        toggles.forEach(function (toggle) {
            var ut = toggle.getAttribute('data-type');
            var evt = toggle.getAttribute('data-event');
            var ch = toggle.getAttribute('data-channel');
            if (!payload[ut]) payload[ut] = {};
            if (!payload[ut][evt]) payload[ut][evt] = {};
            payload[ut][evt][ch] = toggle.checked ? 1 : 0;
        });

        var csrf = getCsrfToken();
        var body = new URLSearchParams({
            op: 'ntf_defaults_save',
            defaults: JSON.stringify(payload)
        });
        if (csrf) body.append('csrf_token', csrf);

        var btn = document.getElementById('btnSaveDefaults');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving Defaults...';

        fetch('admin/ajax/rqst.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save mr-1"></i> Save Global Defaults';
                if (res.ok) {
                    if (window.pgs && window.pgs.toast) {
                        window.pgs.toast('Global notification defaults saved successfully', 'success');
                    } else {
                        PGS.notify('Global notification defaults saved successfully.');
                    }
                } else {
                    PGS.notify((res.error && res.error.message) || res.error || 'Error saving defaults');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save mr-1"></i> Save Global Defaults';
                PGS.notify('Network error saving defaults.');
            });
    }

    // --- INITIALIZATION ---
    document.addEventListener('DOMContentLoaded', function () {
        loadInbox();

        // Filters
        var filterStatus = document.getElementById('filterStatus');
        if (filterStatus) {
            filterStatus.addEventListener('change', function () {
                state.unreadOnly = this.value === '1';
                state.page = 1;
                loadInbox();
            });
        }

        var filterEventType = document.getElementById('filterEventType');
        if (filterEventType) {
            filterEventType.addEventListener('change', function () {
                state.eventType = this.value;
                state.page = 1;
                loadInbox();
            });
        }

        var btnRefresh = document.getElementById('btnRefreshInbox');
        if (btnRefresh) {
            btnRefresh.addEventListener('click', function () {
                loadInbox();
            });
        }

        var btnMarkAll = document.getElementById('btnMarkAllRead');
        if (btnMarkAll) {
            btnMarkAll.addEventListener('click', function () {
                markAllAsRead();
            });
        }

        // Tabs lazy loading
        var tabPrefsLink = document.getElementById('tab-prefs-link');
        if (tabPrefsLink) {
            onTabShown(tabPrefsLink, loadPreferences);
        }

        var tabDefaultsLink = document.getElementById('tab-defaults-link');
        if (tabDefaultsLink) {
            onTabShown(tabDefaultsLink, loadDefaults);
        }

        // Save buttons
        var btnSavePrefs = document.getElementById('btnSavePrefs');
        if (btnSavePrefs) {
            btnSavePrefs.addEventListener('click', savePreferences);
        }

        var btnSaveDefaults = document.getElementById('btnSaveDefaults');
        if (btnSaveDefaults) {
            btnSaveDefaults.addEventListener('click', saveDefaults);
        }
    });
})();
