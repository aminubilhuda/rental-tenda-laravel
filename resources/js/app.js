const formatRupiah = (amount) => `Rp ${new Intl.NumberFormat('id-ID').format(amount)}`;
const parseLocalDate = (value) => new Date(`${value}T00:00:00`);
const escapeHtml = (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');

const clock = document.querySelector('#clock');
if (clock) {
    const updateClock = () => { clock.textContent = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }).format(new Date()) + ' WIB'; };
    updateClock(); window.setInterval(updateClock, 30000);
}

document.querySelector('.login-form-wrap form')?.addEventListener('submit', (event) => {
    if (!event.currentTarget.reportValidity()) { return; }
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Memproses…';
});

document.querySelector('#menu-toggle')?.addEventListener('click', () => document.querySelector('#sidebar')?.classList.toggle('open'));

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const isDelete = form.querySelector('input[name="_method"][value="DELETE"]');
    const destructiveAction = form.dataset.confirmAction;
    const selectedAction = destructiveAction ? form.querySelector('[name="action"], [name="type"]')?.value : null;
    const shouldConfirmAction = destructiveAction && selectedAction === destructiveAction;
    if (!isDelete && !form.dataset.confirm && !shouldConfirmAction) return;
    const message = form.dataset.confirm || (isDelete
        ? 'Yakin menghapus data ini? Tindakan ini akan dicatat dan mungkin tidak dapat dibatalkan.'
        : 'Tindakan ini mengubah atau mengurangi data. Lanjutkan?');
    if (!window.confirm(message)) event.preventDefault();
});

document.querySelectorAll('details.return-details, details.edit-details').forEach((details) => details.addEventListener('toggle', () => {
    if (!details.open) return;
    document.querySelectorAll('details.return-details[open], details.edit-details[open]').forEach((otherDetails) => {
        if (otherDetails !== details) otherDetails.open = false;
    });
    details.querySelector('[data-close-modal], [data-close-edit]')?.focus();
}));

document.addEventListener('click', (event) => {
    const closeButton = event.target.closest('[data-close-modal], [data-close-edit]');
    if (closeButton) {
        const details = closeButton.closest('details.return-details, details.edit-details');
        details.open = false;
        details.querySelector('summary')?.focus();
        return;
    }
    const openDetails = event.target.closest('details.return-details[open], details.edit-details[open]');
    if (openDetails && event.target === openDetails) {
        openDetails.open = false;
        openDetails.querySelector('summary')?.focus();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const openDetails = document.querySelector('details.return-details[open], details.edit-details[open]');
    if (!openDetails) return;
    openDetails.open = false;
    openDetails.querySelector('summary')?.focus();
});

const photoPreviewUrls = new WeakMap();
document.querySelectorAll('input[name="photo"], input[name="business_logo"]').forEach((input) => {
    const previewSelector = '.item-photo-preview, .business-logo-preview';
    const initialPreview = input.closest('form')?.querySelector(previewSelector);
    const emptyPreview = input.closest('form')?.querySelector('.business-logo-empty');
    const fileNameDisplay = input.closest('form')?.querySelector('.business-logo-file-name');
    if (initialPreview?.getAttribute('src')) initialPreview.dataset.originalSrc = initialPreview.src;
    input.addEventListener('change', () => {
        const preview = input.closest('form')?.querySelector(previewSelector);
        if (!preview) return;
        const previousUrl = photoPreviewUrls.get(input);
        if (previousUrl) URL.revokeObjectURL(previousUrl);
        const file = input.files?.[0];
        if (!file) {
            if (fileNameDisplay) fileNameDisplay.textContent = initialPreview?.dataset.originalSrc ? 'Klik untuk mengganti logo' : 'Belum ada file dipilih';
            if (preview.dataset.originalSrc) {
                preview.src = preview.dataset.originalSrc;
                preview.hidden = false;
                if (emptyPreview) emptyPreview.hidden = true;
            } else {
                preview.hidden = true;
                if (emptyPreview) emptyPreview.hidden = false;
            }
            return;
        }
        const previewUrl = URL.createObjectURL(file);
        photoPreviewUrls.set(input, previewUrl);
        preview.src = previewUrl;
        preview.hidden = false;
        if (fileNameDisplay) fileNameDisplay.textContent = file.name;
        if (emptyPreview) emptyPreview.hidden = true;
    });
});

const catalogSearch = document.querySelector('#catalog-search');
const categoryFilter = document.querySelector('#category-filter');
const filterProducts = () => document.querySelectorAll('.product-card').forEach((card) => {
    const matchesName = card.dataset.name.includes((catalogSearch?.value ?? '').toLowerCase());
    const matchesCategory = !categoryFilter?.value || card.dataset.category === categoryFilter.value;
    card.hidden = !(matchesName && matchesCategory);
});
catalogSearch?.addEventListener('input', filterProducts);
categoryFilter?.addEventListener('change', filterProducts);

const cartKey = 'camprent-cart-v1';
const checkoutTokenKey = 'camprent-checkout-token-v1';
if (window.CAMPRENT_CHECKOUT_SUCCESS) sessionStorage.removeItem(checkoutTokenKey);
let checkoutToken = sessionStorage.getItem(checkoutTokenKey);
if (!checkoutToken) {
    checkoutToken = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
    sessionStorage.setItem(checkoutTokenKey, checkoutToken);
}
const checkoutTokenInput = document.querySelector('#checkout-token');
if (checkoutTokenInput) checkoutTokenInput.value = checkoutToken;
let cart = {};
try { cart = JSON.parse(localStorage.getItem(cartKey) ?? '{}'); } catch { cart = {}; }
const cartLines = document.querySelector('#cart-lines');
const cartCount = document.querySelector('#cart-count');
const cartTotal = document.querySelector('#cart-total');
const itemCatalog = new Map((window.CAMPRENT_ITEMS ?? []).map((item) => [String(item.id), item]));

function renderCart() {
    if (!cartLines) return;
    Object.keys(cart).forEach((id) => { if (!itemCatalog.has(id) || itemCatalog.get(id).stock < 1) delete cart[id]; else cart[id] = Math.min(cart[id], itemCatalog.get(id).stock); });
    localStorage.setItem(cartKey, JSON.stringify(cart));
    const ids = Object.keys(cart);
    cartCount.textContent = String(ids.length);
    if (!ids.length) {
        cartLines.innerHTML = '<div class="cart-empty"><span>⌑</span><strong>Keranjang masih kosong</strong><small>Pilih alat untuk memulai transaksi.</small></div>';
        cartTotal.textContent = formatRupiah(0);
        return;
    }
    const duration = Math.max(1, Math.round((parseLocalDate(document.querySelector('[name="due_date"]')?.value) - parseLocalDate(document.querySelector('[name="rent_date"]')?.value)) / 86400000));
    cartLines.innerHTML = ids.map((id) => {
        const item = itemCatalog.get(id);
        return `<div class="cart-line"><span><strong>${escapeHtml(item.name)}</strong><small>${formatRupiah(item.price)} × ${duration} hari · ${formatRupiah(item.price * cart[id] * duration)}</small></span><span class="qty-control"><button type="button" data-qty="${id}" data-step="-1">−</button><b>${cart[id]}</b><button type="button" data-qty="${id}" data-step="1">+</button></span></div>`;
    }).join('');
    const subtotal = ids.reduce((sum, id) => sum + itemCatalog.get(id).price * cart[id] * duration, 0);
    const discount = Number(document.querySelector('#discount')?.value ?? 0);
    cartTotal.textContent = formatRupiah(Math.max(0, subtotal - discount));
}

document.querySelectorAll('.add-to-cart').forEach((button) => button.addEventListener('click', () => {
    if (!button.dataset.stock || Number(button.dataset.stock) < 1) return;
    const id = button.dataset.id;
    cart[id] = Math.min((cart[id] ?? 0) + 1, Number(button.dataset.stock));
    renderCart();
}));
cartLines?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-qty]');
    if (!button) return;
    const id = button.dataset.qty;
    cart[id] = Math.min(Number(itemCatalog.get(id)?.stock ?? 0), Number(cart[id] ?? 0) + Number(button.dataset.step));
    if (cart[id] < 1) delete cart[id];
    renderCart();
});
document.querySelector('#clear-cart')?.addEventListener('click', () => { cart = {}; renderCart(); });
document.querySelector('#discount')?.addEventListener('input', renderCart);
function updateDuration() {
    const rentDateInput = document.querySelector('[name="rent_date"]');
    const dueDateInput = document.querySelector('[name="due_date"]');
    if (!rentDateInput?.value || !dueDateInput?.value) return;
    dueDateInput.min = rentDateInput.value;
    if (dueDateInput.value < rentDateInput.value) {
        const nextDay = parseLocalDate(rentDateInput.value);
        nextDay.setDate(nextDay.getDate() + 1);
        dueDateInput.value = `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')}`;
    }
    const start = parseLocalDate(rentDateInput.value);
    const end = parseLocalDate(dueDateInput.value);
    const days = Math.max(1, Math.round((end - start) / 86400000));
    const durationLabel = document.querySelector('#duration');
    if (durationLabel) durationLabel.textContent = `${days} hari`;
    renderCart();
}
document.querySelectorAll('[name="rent_date"], [name="due_date"]').forEach((input) => input.addEventListener('change', updateDuration));
updateDuration();
renderCart();

document.querySelector('#checkout-form')?.addEventListener('submit', (event) => {
    const form = event.currentTarget;
    const ids = Object.keys(cart);
    if (!ids.length) { event.preventDefault(); window.alert('Tambahkan minimal satu alat ke keranjang.'); return; }
    const oldInputs = form.querySelectorAll('[data-cart-input]'); oldInputs.forEach((input) => input.remove());
    ids.forEach((id, index) => {
        [['item_id', id], ['qty', cart[id]]].forEach(([key, value]) => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = `items[${index}][${key}]`; input.value = value; input.dataset.cartInput = '1'; form.append(input);
        });
    });
    const dueDate = new Date(form.querySelector('[name="due_date"]').value);
    const rentDate = new Date(form.querySelector('[name="rent_date"]').value);
    if (dueDate < rentDate) { event.preventDefault(); window.alert('Tanggal kembali tidak boleh sebelum tanggal sewa.'); return; }
    const button = form.querySelector('#checkout-button'); button.disabled = true; button.innerHTML = 'Memproses…';
    localStorage.removeItem(cartKey);
});

const customerName = document.querySelector('#customer-name');
const suggestionBox = document.querySelector('#customer-suggestions');
let searchTimeout;
customerName?.addEventListener('input', () => {
    document.querySelector('#customer-id').value = '';
    const warning = document.querySelector('#customer-warning');
    if (warning) { warning.hidden = true; warning.textContent = ''; }
    clearTimeout(searchTimeout);
    const query = customerName.value.trim();
    if (query.length < 2) { suggestionBox.replaceChildren(); return; }
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`/api/customers/search?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
            const customers = await response.json();
            suggestionBox.replaceChildren();
            customers.forEach((customer) => {
                const suggestion = document.createElement('div');
                suggestion.className = 'suggestion';
                suggestion.dataset.id = customer.id;
                suggestion.dataset.name = customer.name;
                suggestion.dataset.phone = customer.phone;
                suggestion.dataset.address = customer.address ?? '';
                suggestion.dataset.blacklisted = String(Boolean(customer.is_blacklisted));
                suggestion.dataset.reason = customer.blacklist_reason ?? '';
                suggestion.append(document.createTextNode(`${customer.name}${customer.is_blacklisted ? ' · BLACKLIST' : ''}`));
                const phone = document.createElement('small'); phone.textContent = customer.phone; suggestion.append(phone);
                suggestionBox.append(suggestion);
            });
        } catch { suggestionBox.replaceChildren(); }
    }, 300);
});
suggestionBox?.addEventListener('click', (event) => {
    const suggestion = event.target.closest('.suggestion'); if (!suggestion) return;
    customerName.value = suggestion.dataset.name; document.querySelector('#customer-id').value = suggestion.dataset.id;
    document.querySelector('#customer-phone').value = suggestion.dataset.phone;
    document.querySelector('[name="address"]').value = suggestion.dataset.address;
    const warning = document.querySelector('#customer-warning');
    if (warning && suggestion.dataset.blacklisted === 'true') {
        warning.textContent = `Pelanggan ini masuk daftar hitam.${suggestion.dataset.reason ? ` Alasan: ${suggestion.dataset.reason}.` : ''}${window.CAMPRENT_BLOCK_BLACKLIST ? ' Transaksi akan diblokir oleh pengaturan usaha.' : ''}`;
        warning.hidden = false;
    } else if (warning) { warning.hidden = true; warning.textContent = ''; }
    suggestionBox.replaceChildren();
});
document.addEventListener('click', (event) => { if (!event.target.closest('.field-label')) suggestionBox?.replaceChildren(); });
