/* =====================================================================
   Pet House — Core PHP vanilla JS helpers
   Used for AJAX calls, toasts, form validation, cart, etc.
   ===================================================================== */

(function () {
    'use strict';

    // CSRF token is embedded in a meta tag for AJAX use
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';

    /**
     * Wrapper around fetch() that auto-adds CSRF header + JSON content-type
     * and returns parsed JSON.
     */
    window.api = async function (url, options = {}) {
        options.headers = options.headers || {};
        if (CSRF) options.headers['X-CSRF-Token'] = CSRF;
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        const res = await fetch(url, options);
        let data;
        try { data = await res.json(); } catch { data = {}; }
        return { ok: res.ok, status: res.status, data };
    };

    /**
     * Show a toast notification (Bootstrap 5).
     * type: success | danger | warning | info
     */
    window.showToast = function (message, type = 'success') {
        const container = document.querySelector('.toast-container')
            || (() => {
                const c = document.createElement('div');
                c.className = 'toast-container position-fixed top-0 end-0 p-3';
                c.style.zIndex = '1090';
                document.body.appendChild(c);
                return c;
            })();
        const cls = {
            success: 'text-bg-success',
            danger:  'text-bg-danger',
            warning: 'text-bg-warning',
            info:    'text-bg-info',
        }[type] || 'text-bg-primary';
        const el = document.createElement('div');
        el.className = `toast align-items-center ${cls} border-0`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `<div class="d-flex"><div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        el.querySelector('.toast-body').textContent = message;
        container.appendChild(el);
        const t = new bootstrap.Toast(el, { delay: 3500 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    };

    /** Confirm dialog wrapper (promise). */
    window.confirmAction = function (message) {
        return Promise.resolve(window.confirm(message));
    };

    /* ─── AJAX cart add (delegated) ─────────────────────────── */
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-cart-add]');
        if (!btn) return;
        e.preventDefault();
        const pid = btn.getAttribute('data-cart-add');
        btn.disabled = true;
        const r = await api(APP_URL + '/ajax/add_to_cart.php', {
            method: 'POST',
            body: { product_id: pid, quantity: 1 },
        });
        btn.disabled = false;
        if (r.ok && r.data.success) {
            showToast('Added to cart!', 'success');
            const badge = document.querySelector('.fa-shopping-cart + .badge, a[href*="cart"] .badge');
            if (badge && r.data.cart_count !== undefined) {
                badge.textContent = r.data.cart_count;
                badge.style.display = r.data.cart_count > 0 ? '' : 'none';
            }
        } else {
            showToast(r.data.message || 'Could not add to cart', 'danger');
            if (r.status === 401) setTimeout(() => location.href = APP_URL + '/login.php', 900);
        }
    });

    /* ─── AJAX wishlist toggle ──────────────────────────────── */
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-wishlist-toggle]');
        if (!btn) return;
        e.preventDefault();
        const pid = btn.getAttribute('data-wishlist-toggle');
        const r = await api(APP_URL + '/ajax/wishlist.php', {
            method: 'POST',
            body: { product_id: pid },
        });
        if (r.ok && r.data.success) {
            const icon = btn.querySelector('i') || btn;
            if (r.data.action === 'added') {
                icon.classList.remove('far'); icon.classList.add('fas', 'text-danger');
                showToast('Added to wishlist', 'success');
            } else {
                icon.classList.remove('fas', 'text-danger'); icon.classList.add('far');
                showToast('Removed from wishlist', 'info');
            }
        } else {
            showToast(r.data.message || 'Action failed', 'danger');
            if (r.status === 401) setTimeout(() => location.href = APP_URL + '/login.php', 900);
        }
    });

    /* ─── Generic confirm-and-submit (for delete buttons) ───── */
    document.addEventListener('submit', async function (e) {
        const form = e.target.closest('[data-confirm-submit]');
        if (!form) return;
        const msg = form.getAttribute('data-confirm-submit') || 'Are you sure?';
        if (!window.confirm(msg)) { e.preventDefault(); return; }
        // let it submit normally
    });

    /* ─── Auto-dismiss alerts ───────────────────────────────── */
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-persistent)').forEach(a => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        });
    }, 5000);
})();
