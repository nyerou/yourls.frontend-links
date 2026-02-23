/**
 * Frontend Links - Admin Panel JavaScript
 * =========================================
 *
 * Handles all admin CRUD operations, modals, panels, and UI updates.
 * Externalized from templates/admin.php for YOURLS 1.10 CSP compliance
 * (Content Security Policy blocks inline <script> and onclick handlers).
 *
 * Architecture:
 *   - Config (ajaxUrl, nonce, i18n strings) is loaded from a
 *     <script type="application/json" id="fl-config"> block in admin.php.
 *   - All user interactions are handled via event delegation on document:
 *       • Click events:  data-action="..." attributes
 *       • Form submits:  data-fl-submit="..." attributes
 *       • Change events: input[name="icon_type"] radio buttons
 *   - AJAX calls go to ajax.php (dedicated endpoint, not admin template).
 *   - DOM is updated in-place after successful AJAX responses.
 *
 * Event delegation means dynamically inserted rows (from AJAX responses)
 * automatically get event handling without re-binding.
 *
 * @see templates/admin.php   HTML template (data attributes + JSON config)
 * @see ajax.php              Server-side AJAX handler
 * @see assets/css/admin.css  Admin styles (toast, modals, panels)
 *
 * @package FrontendLinks
 */
(function () {
    'use strict';

    // ─── Load config from JSON block ────────────────────────────
    var configEl = document.getElementById('fl-config');
    if (!configEl) return;
    var FL = JSON.parse(configEl.textContent);

    // ─── Toast ──────────────────────────────────────────────────
    function flToast(msg, isError) {
        var el = document.getElementById('fl-toast');
        el.textContent = msg;
        el.className = 'fl-toast ' + (isError ? 'error' : 'success') + ' show';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.classList.remove('show'); }, 3500);
    }

    // ─── Escape HTML ────────────────────────────────────────────
    function flEsc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ─── Panels toggle ──────────────────────────────────────────
    function flTogglePanel(panelId, header) {
        var panel = document.getElementById(panelId);
        var arrow = header.querySelector('.fl-arrow');
        if (panel.style.display === 'none') {
            panel.style.display = '';
            arrow.classList.add('open');
        } else {
            panel.style.display = 'none';
            arrow.classList.remove('open');
        }
    }

    // ─── Modals ─────────────────────────────────────────────────
    function flOpenModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function flCloseModals() {
        document.querySelectorAll('.fl-modal-overlay').forEach(function (m) {
            m.style.display = 'none';
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') flCloseModals();
    });

    document.querySelectorAll('.fl-modal-overlay').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === this) flCloseModals();
        });
    });

    // ─── Icon type toggle ───────────────────────────────────────
    function flToggleIconType() {
        var checked = document.querySelector('input[name="icon_type"]:checked');
        if (!checked) return;
        var isSvg = checked.value === 'svg';
        document.getElementById('flIconSvgRow').style.display = isSvg ? '' : 'none';
        document.getElementById('flIconFileRow').style.display = isSvg ? 'none' : '';
    }

    // ─── AJAX helper ────────────────────────────────────────────
    function flAjax(formData, callback) {
        fetch(FL.ajaxUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                flToast(data.message, !data.success);
                if (callback) callback(data);
            })
            .catch(function () {
                flToast(FL.i18n.connectionError, true);
            });
    }

    // Generic AJAX submit for option forms
    function flSubmitAjax(form) {
        flAjax(new FormData(form), null);
    }

    // ─── LINKS: Add ─────────────────────────────────────────────
    function flSubmitAddLink(form) {
        flAjax(new FormData(form), function (resp) {
            if (!resp.success) return;
            var link = resp.data;
            var sectionDiv = document.getElementById('fl-link-section-' + link.section_id);
            if (!sectionDiv) {
                flCloseModals();
                location.reload();
                return;
            }

            var empty = sectionDiv.querySelector('.fl-empty-msg');
            if (empty) {
                empty.remove();
                var table = document.createElement('table');
                table.className = 'tblUrl fl-links-table';
                table.innerHTML = '<thead><tr><th>' + FL.i18n.thIcon + '</th><th>' + FL.i18n.thLabel + '</th><th>' + FL.i18n.thUrl + '</th><th>' + FL.i18n.thOrder + '</th><th>' + FL.i18n.thActive + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
                sectionDiv.appendChild(table);
            }

            var tbody = sectionDiv.querySelector('.fl-links-table tbody');
            if (tbody) {
                tbody.insertAdjacentHTML('beforeend', flBuildLinkRow(link));
            }

            flUpdateSectionCount(link.section_id, 1);
            flCloseModals();
            form.reset();
        });
    }

    // ─── LINKS: Edit ────────────────────────────────────────────
    function flEditLink(link) {
        document.getElementById('fl_edit_link_id').value = link.id;
        document.getElementById('fl_edit_link_label').value = link.label;
        document.getElementById('fl_edit_link_url').value = link.url;
        document.getElementById('fl_edit_link_section').value = link.section_id;
        document.getElementById('fl_edit_link_icon').value = link.icon;
        document.getElementById('fl_edit_link_order').value = link.sort_order;
        document.getElementById('fl_edit_link_active').checked = link.is_active == 1;
        flOpenModal('flEditLinkModal');
    }

    function flSubmitEditLink(form) {
        var oldSectionId = null;
        var linkId = document.getElementById('fl_edit_link_id').value;
        var oldRow = document.querySelector('tr[data-id="' + linkId + '"]');
        if (oldRow) {
            var oldSection = oldRow.closest('.fl-link-section');
            if (oldSection) oldSectionId = oldSection.dataset.sectionId;
        }

        flAjax(new FormData(form), function (resp) {
            if (!resp.success) return;
            var link = resp.data;

            if (oldRow) {
                oldRow.remove();
                if (oldSectionId && oldSectionId != link.section_id) {
                    flUpdateSectionCount(oldSectionId, -1);
                    flCheckEmptySection(oldSectionId);
                }
            }

            var sectionDiv = document.getElementById('fl-link-section-' + link.section_id);
            if (sectionDiv) {
                var empty = sectionDiv.querySelector('.fl-empty-msg');
                if (empty) {
                    empty.remove();
                    var table = document.createElement('table');
                    table.className = 'tblUrl fl-links-table';
                    table.innerHTML = '<thead><tr><th>' + FL.i18n.thIcon + '</th><th>' + FL.i18n.thLabel + '</th><th>' + FL.i18n.thUrl + '</th><th>' + FL.i18n.thOrder + '</th><th>' + FL.i18n.thActive + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
                    sectionDiv.appendChild(table);
                }
                var tbody = sectionDiv.querySelector('.fl-links-table tbody');
                var existingRow = tbody ? tbody.querySelector('tr[data-id="' + link.id + '"]') : null;
                if (existingRow) {
                    existingRow.outerHTML = flBuildLinkRow(link);
                } else if (tbody) {
                    tbody.insertAdjacentHTML('beforeend', flBuildLinkRow(link));
                    if (oldSectionId != link.section_id) {
                        flUpdateSectionCount(link.section_id, 1);
                    }
                }
            }

            flCloseModals();
        });
    }

    // ─── LINKS: Delete ──────────────────────────────────────────
    function flDeleteLink(id, sectionId) {
        if (!confirm(FL.i18n.confirmDeleteLink)) return;
        var fd = new FormData();
        fd.append('fl_action', 'delete_link');
        fd.append('nonce', FL.nonce);
        fd.append('link_id', id);
        fd.append('section_id', sectionId);

        flAjax(fd, function (resp) {
            if (!resp.success) return;
            var row = document.querySelector('tr[data-id="' + id + '"]');
            if (row) {
                row.classList.add('fl-fade-out');
                setTimeout(function () {
                    row.remove();
                    flUpdateSectionCount(sectionId, -1);
                    flCheckEmptySection(sectionId);
                }, 300);
            }
        });
    }

    // ─── LINKS: Helpers ─────────────────────────────────────────
    function flBuildLinkRow(link) {
        var linkJson = JSON.stringify(link).replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;');
        return '<tr data-id="' + link.id + '" data-link="' + linkJson + '">' +
            '<td style="text-align:center;">' + link.icon_html + '</td>' +
            '<td><strong class="fl-link-label">' + flEsc(link.label) + '</strong></td>' +
            '<td class="fl-link-url"><a href="' + flEsc(link.url) + '" target="_blank" style="font-size:12px;">' + flEsc(link.url) + '</a></td>' +
            '<td class="fl-link-order">' + link.sort_order + '</td>' +
            '<td class="fl-link-active">' + (link.is_active == 1 ? FL.i18n.yes : '<em>' + FL.i18n.no + '</em>') + '</td>' +
            '<td class="fl-actions">' +
            '<a href="#" data-action="edit-link">' + FL.i18n.edit + '</a>' +
            '&nbsp;|&nbsp;' +
            '<a href="#" data-action="delete-link" data-section-id="' + link.section_id + '" style="color:#a00;">' + FL.i18n.delete_ + '</a>' +
            '</td></tr>';
    }

    function flUpdateSectionCount(sectionId, delta) {
        var row = document.querySelector('#fl-sections-table tr[data-id="' + sectionId + '"]');
        if (!row) return;
        var cell = row.querySelector('.fl-section-count');
        if (cell) cell.textContent = Math.max(0, parseInt(cell.textContent || '0') + delta);
    }

    function flCheckEmptySection(sectionId) {
        var sectionDiv = document.getElementById('fl-link-section-' + sectionId);
        if (!sectionDiv) return;
        var tbody = sectionDiv.querySelector('.fl-links-table tbody');
        if (tbody && tbody.children.length === 0) {
            var table = sectionDiv.querySelector('.fl-links-table');
            if (table) table.remove();
            var p = document.createElement('p');
            p.className = 'fl-empty-msg';
            p.innerHTML = '<em>' + FL.i18n.noLinksInSection + '</em>';
            sectionDiv.appendChild(p);
        }
    }

    // ─── SECTIONS: Add ──────────────────────────────────────────
    function flSubmitAddSection(form) {
        flAjax(new FormData(form), function (resp) {
            if (!resp.success) return;
            var s = resp.data;

            var tbody = document.querySelector('#fl-sections-table tbody');
            var noSections = document.getElementById('fl-no-sections');
            if (!tbody) {
                if (noSections) noSections.remove();
                var wrapper = document.querySelector('h2');
                var table = document.createElement('table');
                table.className = 'tblUrl';
                table.id = 'fl-sections-table';
                table.innerHTML = '<thead><tr><th>' + FL.i18n.thTitle + '</th><th>' + FL.i18n.thLinks + '</th><th>' + FL.i18n.thOrder + '</th><th>' + FL.i18n.thActive + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
                wrapper.insertAdjacentElement('afterend', table);
                tbody = table.querySelector('tbody');
            }
            tbody.insertAdjacentHTML('beforeend', flBuildSectionRow(s));

            var selects = document.querySelectorAll('select[name="section_id"]');
            selects.forEach(function (sel) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.title;
                sel.appendChild(opt);
            });

            var newDiv = document.createElement('div');
            newDiv.id = 'fl-link-section-' + s.id;
            newDiv.className = 'fl-link-section';
            newDiv.dataset.sectionId = s.id;
            newDiv.innerHTML = '<h3 class="fl-section-heading">' + flEsc(s.title) + '</h3><p class="fl-empty-msg"><em>' + FL.i18n.noLinksInSection + '</em></p>';
            var lastSection = document.querySelector('.fl-link-section:last-of-type');
            if (lastSection) {
                lastSection.insertAdjacentElement('afterend', newDiv);
            } else {
                var linksHeading = document.querySelectorAll('h2')[1];
                if (linksHeading) {
                    var addBtn = linksHeading.nextElementSibling;
                    if (addBtn) {
                        addBtn.insertAdjacentElement('afterend', newDiv);
                    } else {
                        linksHeading.insertAdjacentElement('afterend', newDiv);
                    }
                }
            }

            flCloseModals();
            form.reset();
        });
    }

    // ─── SECTIONS: Edit ─────────────────────────────────────────
    function flEditSection(row) {
        document.getElementById('fl_edit_section_id').value = row.dataset.id;
        document.getElementById('fl_edit_section_title').value = row.dataset.title;
        document.getElementById('fl_edit_section_order').value = row.dataset.order;
        document.getElementById('fl_edit_section_active').checked = row.dataset.active == '1';
        flOpenModal('flEditSectionModal');
    }

    function flSubmitEditSection(form) {
        flAjax(new FormData(form), function (resp) {
            if (!resp.success) return;
            var s = resp.data;

            var row = document.querySelector('#fl-sections-table tr[data-id="' + s.id + '"]');
            if (row) {
                row.querySelector('.fl-section-title').textContent = s.title;
                row.querySelector('.fl-section-order').textContent = s.sort_order;
                row.querySelector('.fl-section-active').innerHTML = s.is_active == 1 ? FL.i18n.yes : '<em>' + FL.i18n.no + '</em>';
                // Update data attributes for next edit
                row.dataset.title = s.title;
                row.dataset.order = s.sort_order;
                row.dataset.active = s.is_active;
            }

            var heading = document.querySelector('#fl-link-section-' + s.id + ' .fl-section-heading');
            if (heading) heading.textContent = s.title;

            document.querySelectorAll('select[name="section_id"] option[value="' + s.id + '"]').forEach(function (opt) {
                opt.textContent = s.title;
            });

            flCloseModals();
        });
    }

    // ─── SECTIONS: Delete ───────────────────────────────────────
    function flDeleteSection(id) {
        if (!confirm(FL.i18n.confirmDeleteSection)) return;
        var fd = new FormData();
        fd.append('fl_action', 'delete_section');
        fd.append('nonce', FL.nonce);
        fd.append('section_id', id);

        flAjax(fd, function (resp) {
            if (!resp.success) return;

            var row = document.querySelector('#fl-sections-table tr[data-id="' + id + '"]');
            if (row) {
                row.classList.add('fl-fade-out');
                setTimeout(function () { row.remove(); }, 300);
            }

            var sectionDiv = document.getElementById('fl-link-section-' + id);
            if (sectionDiv) sectionDiv.remove();

            document.querySelectorAll('select[name="section_id"] option[value="' + id + '"]').forEach(function (opt) {
                opt.remove();
            });
        });
    }

    function flBuildSectionRow(s) {
        return '<tr data-id="' + s.id + '" data-title="' + flEsc(s.title) + '" data-order="' + s.sort_order + '" data-active="' + s.is_active + '">' +
            '<td><strong class="fl-section-title">' + flEsc(s.title) + '</strong></td>' +
            '<td class="fl-section-count">0</td>' +
            '<td class="fl-section-order">' + s.sort_order + '</td>' +
            '<td class="fl-section-active">' + (s.is_active == 1 ? FL.i18n.yes : '<em>' + FL.i18n.no + '</em>') + '</td>' +
            '<td class="fl-actions">' +
            '<a href="#" data-action="edit-section">' + FL.i18n.edit + '</a>' +
            '&nbsp;|&nbsp;' +
            '<a href="#" data-action="delete-section" style="color:#a00;">' + FL.i18n.delete_ + '</a>' +
            '</td></tr>';
    }

    // ─── ICONS: Delete ──────────────────────────────────────────
    function flDeleteIcon(id) {
        if (!confirm(FL.i18n.confirmDeleteIcon)) return;
        var fd = new FormData();
        fd.append('fl_action', 'delete_custom_icon');
        fd.append('nonce', FL.nonce);
        fd.append('icon_id', id);

        flAjax(fd, function (resp) {
            if (!resp.success) return;
            var row = document.querySelector('#fl-custom-icons-table tr[data-id="' + id + '"]');
            if (row) {
                row.classList.add('fl-fade-out');
                setTimeout(function () {
                    row.remove();
                    var tbody = document.querySelector('#fl-custom-icons-table tbody');
                    if (tbody && tbody.children.length === 0) {
                        var table = document.getElementById('fl-custom-icons-table');
                        if (table) table.remove();
                        var p = document.createElement('p');
                        p.id = 'fl-no-custom-icons';
                        p.innerHTML = '<em>' + FL.i18n.noCustomIcons + '</em>';
                        var addBtn = document.querySelector('#fl-panel-icons > p:last-child');
                        if (addBtn) addBtn.insertAdjacentElement('beforebegin', p);
                    }
                }, 300);
            }
        });
    }

    // ─── PROFILE: Submit ────────────────────────────────────────
    function flSubmitProfile(form) {
        var fd = new FormData(form);
        flAjax(fd, function (resp) {
            if (!resp.success) return;
            var data = resp.data;
            if (data.avatar) {
                var img = document.getElementById('fl-avatar-current-img');
                img.src = data.avatar;
                document.getElementById('fl-avatar-current').style.display = '';
                document.getElementById('fl-btn-delete-avatar').style.display = '';
                var urlInput = form.querySelector('input[name="profile_avatar"]');
                if (urlInput) urlInput.value = data.avatar;
            }
            var fileInput = form.querySelector('input[name="avatar_file"]');
            if (fileInput) fileInput.value = '';
        });
    }

    // ─── AVATAR: Delete ─────────────────────────────────────────
    function flDeleteAvatar() {
        if (!confirm(FL.i18n.confirmDeleteAvatar)) return;
        var fd = new FormData();
        fd.append('fl_action', 'delete_avatar');
        fd.append('nonce', FL.nonce);

        flAjax(fd, function (resp) {
            if (!resp.success) return;
            document.getElementById('fl-avatar-current').style.display = 'none';
            document.getElementById('fl-avatar-current-img').src = '';
            document.getElementById('fl-btn-delete-avatar').style.display = 'none';
            var urlInput = document.querySelector('#fl-form-profile input[name="profile_avatar"]');
            if (urlInput) urlInput.value = '';
            if (resp.data && resp.data.previous_url) {
                document.getElementById('fl-avatar-previous-img').src = resp.data.previous_url;
                document.getElementById('fl-avatar-previous').style.display = '';
                document.getElementById('fl-btn-restore-avatar').style.display = '';
            } else {
                document.getElementById('fl-avatar-previous').style.display = 'none';
                document.getElementById('fl-btn-restore-avatar').style.display = 'none';
            }
        });
    }

    // ─── AVATAR: Restore ────────────────────────────────────────
    function flRestoreAvatar() {
        if (!confirm(FL.i18n.confirmRestoreAvatar)) return;
        var fd = new FormData();
        fd.append('fl_action', 'restore_avatar');
        fd.append('nonce', FL.nonce);

        flAjax(fd, function (resp) {
            if (!resp.success) return;
            document.getElementById('fl-avatar-current-img').src = resp.data.avatar;
            document.getElementById('fl-avatar-current').style.display = '';
            document.getElementById('fl-btn-delete-avatar').style.display = '';
            var urlInput = document.querySelector('#fl-form-profile input[name="profile_avatar"]');
            if (urlInput) urlInput.value = resp.data.avatar;
            document.getElementById('fl-avatar-previous').style.display = 'none';
            document.getElementById('fl-btn-restore-avatar').style.display = 'none';
        });
    }

    // ─── ICONS: Add custom ──────────────────────────────────────
    function flSubmitAddIcon(form) {
        var fd = new FormData(form);
        flAjax(fd, function (resp) {
            if (!resp.success) return;
            var icon = resp.data;

            var noIcons = document.getElementById('fl-no-custom-icons');
            if (noIcons) noIcons.remove();

            var tbody = document.querySelector('#fl-custom-icons-table tbody');
            if (!tbody) {
                var table = document.createElement('table');
                table.className = 'tblUrl';
                table.id = 'fl-custom-icons-table';
                table.innerHTML = '<thead><tr><th>' + FL.i18n.thPreview + '</th><th>' + FL.i18n.thName + '</th><th>' + FL.i18n.thType + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
                var addBtn = document.querySelector('#fl-panel-icons > p:last-child');
                if (addBtn) addBtn.insertAdjacentElement('beforebegin', table);
                tbody = table.querySelector('tbody');
            }

            tbody.insertAdjacentHTML('beforeend',
                '<tr data-id="' + icon.id + '">' +
                '<td style="text-align:center;">' + icon.icon_html + '</td>' +
                '<td><strong>' + flEsc(icon.name) + '</strong></td>' +
                '<td>' + (icon.type === 'svg' ? 'SVG' : FL.i18n.image) + '</td>' +
                '<td class="fl-actions"><a href="#" data-action="delete-icon" style="color:#a00;">' + FL.i18n.delete_ + '</a></td>' +
                '</tr>'
            );

            var selects = document.querySelectorAll('select[name="icon"]');
            selects.forEach(function (sel) {
                var opt = document.createElement('option');
                opt.value = icon.name;
                opt.textContent = icon.name + ' \u2726';
                sel.appendChild(opt);
            });

            flCloseModals();
            form.reset();
        });
    }

    // ─── Event delegation (handles static + dynamic elements) ───
    document.addEventListener('click', function (e) {
        var target = e.target.closest('[data-action]');
        if (!target) return;

        var action = target.dataset.action;
        e.preventDefault();

        switch (action) {
            case 'open-modal':
                flOpenModal(target.dataset.modal);
                break;

            case 'close-modals':
                flCloseModals();
                break;

            case 'toggle-panel':
                flTogglePanel(target.dataset.panel, target);
                break;

            case 'edit-section':
                var sRow = target.closest('tr');
                if (sRow) flEditSection(sRow);
                break;

            case 'delete-section':
                var dsRow = target.closest('tr');
                if (dsRow) flDeleteSection(dsRow.dataset.id);
                break;

            case 'edit-link':
                var lRow = target.closest('tr');
                if (lRow && lRow.dataset.link) {
                    flEditLink(JSON.parse(lRow.dataset.link));
                }
                break;

            case 'delete-link':
                var dlRow = target.closest('tr');
                if (dlRow) {
                    var secId = target.dataset.sectionId || dlRow.closest('.fl-link-section').dataset.sectionId;
                    flDeleteLink(dlRow.dataset.id, secId);
                }
                break;

            case 'delete-icon':
                var iRow = target.closest('tr');
                if (iRow) flDeleteIcon(iRow.dataset.id);
                break;

            case 'delete-avatar':
                flDeleteAvatar();
                break;

            case 'restore-avatar':
                flRestoreAvatar();
                break;

            case 'regenerate-robots-txt':
                var fd = new FormData();
                fd.append('fl_action', 'regenerate_robots_txt');
                fd.append('nonce', target.dataset.nonce);
                flAjax(fd, null);
                break;
        }
    });

    // ─── Form submissions via event delegation ──────────────────
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var formAction = form.dataset.flSubmit;
        if (!formAction) return;

        e.preventDefault();

        switch (formAction) {
            case 'install':
                if (confirm(FL.i18n.confirmInstall || 'Install?')) {
                    form.submit(); // real POST submit for install
                }
                break;
            case 'add-section':
                flSubmitAddSection(form);
                break;
            case 'edit-section':
                flSubmitEditSection(form);
                break;
            case 'add-link':
                flSubmitAddLink(form);
                break;
            case 'edit-link':
                flSubmitEditLink(form);
                break;
            case 'add-icon':
                flSubmitAddIcon(form);
                break;
            case 'profile':
                flSubmitProfile(form);
                break;
            case 'ajax':
                flSubmitAjax(form);
                break;
        }
    });

    // ─── Icon type radio change ─────────────────────────────────
    document.addEventListener('change', function (e) {
        if (e.target.name === 'icon_type') {
            flToggleIconType();
        }
    });

})();
