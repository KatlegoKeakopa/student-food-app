// ============================================================
// API client — all requests to the PHP backend
// ============================================================

const API_BASE = window.APP_CONFIG?.apiBase || '../../backend/api'; // From frontend/customer/index.html to backend/api

async function apiRequest(endpoint, method = 'GET', body = null, token = null) {
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const opts = { method, headers, credentials: 'include' };
  if (body && method !== 'GET') opts.body = JSON.stringify(body);

  try {
    const res = await fetch(`${API_BASE}/${endpoint}`, opts);
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      return {
        ok: false,
        status: res.status,
        data: {
          success: false,
          message: `API returned ${res.status || 'a non-JSON response'}. Check that Apache is serving backend/.htaccess routes and API_BASE is correct.`,
        },
      };
    }
    const data = await res.json();
    return { ok: res.ok, status: res.status, data };
  } catch (err) {
    const isFile = window.location.protocol === 'file:';
    return {
      ok: false,
      status: 0,
      data: {
        success: false,
        message: isFile
          ? 'The app is open as a file. Use Apache/WAMP/XAMPP, for example http://localhost/student-food-app/frontend/customer/index.html.'
          : 'Could not reach the PHP API. Check that Apache is running, backend/.htaccess is enabled, and APP_CONFIG.apiBase points to the backend API.',
      },
    };
  }
}

// Convenience helpers
const api = {
  signup:           (body)                => apiRequest('auth/signup', 'POST', body),
  login:            (body)                => apiRequest('auth/login',  'POST', body),
  logout:           ()                    => apiRequest('auth/logout', 'POST', {}),
  getOutlets:       (token)               => apiRequest('outlets', 'GET', null, token),
  getOutlet:        (id, token)           => apiRequest(`outlets?id=${id}`, 'GET', null, token),
  getMenu:          (outletId, filters = {}, token) => {
    const params = new URLSearchParams({ outlet_id: outletId });
    if (typeof filters === 'string') {
      if (filters) params.set('category', filters);
    } else {
      ['category', 'q', 'diet', 'sort'].forEach(k => {
        if (filters[k]) params.set(k, filters[k]);
      });
    }
    return apiRequest(`menu?${params.toString()}`, 'GET', null, token);
  },
  placeOrder:       (body, token)         => apiRequest('orders', 'POST', body, token),
  createPaymentIntent: (body, token)      => apiRequest('payments/intents', 'POST', body, token),
  confirmPayment:   (id, token)           => apiRequest(`payments/${id}/confirm`, 'POST', {}, token),
  getOrders:        (token)               => apiRequest('orders', 'GET', null, token),
  getOrder:         (id, token)           => apiRequest(`orders/${id}`, 'GET', null, token),
  getTracking:      (id, token)           => apiRequest(`orders/${id}/tracking`, 'GET', null, token),
  cancelOrder:      (id, token)           => apiRequest(`orders/${id}`, 'DELETE', null, token),
  confirmReceived:  (id, token)           => apiRequest(`orders/${id}/confirm-received`, 'POST', {}, token),
  applyPromo:       (body, token)         => apiRequest('promotions/apply', 'POST', body, token),
  submitRating:     (body, token)         => apiRequest('ratings', 'POST', body, token),
  getNotifications: (token)               => apiRequest('notifications', 'GET', null, token),
  markNotifsRead:   (token)               => apiRequest('notifications', 'PATCH', {}, token),
  getFavorites:     (token)               => apiRequest('favorites', 'GET', null, token),
  addFavorite:      (body, token)         => apiRequest('favorites', 'POST', body, token),
  removeFavorite:   (id, token)           => apiRequest(`favorites?id=${id}`, 'DELETE', null, token),
  getAddresses:     (token)               => apiRequest('addresses', 'GET', null, token),
  addAddress:       (body, token)         => apiRequest('addresses', 'POST', body, token),
  createTicket:     (body, token)         => apiRequest('support', 'POST', body, token),
  getNotificationPrefs: (token)           => apiRequest('notifications/preferences', 'GET', null, token),
  updateNotificationPrefs: (body, token)  => apiRequest('notifications/preferences', 'PATCH', body, token),
  registerDevice:   (body, token)         => apiRequest('devices/register', 'POST', body, token),
  getLegalDocs:     ()                    => apiRequest('legal/documents', 'GET'),
  acceptLegalDocs:  (body, token)         => apiRequest('legal/consents', 'POST', body, token),
  createDataRequest:(body, token)         => apiRequest('legal/data-requests', 'POST', body, token),
  submitVendorApplication: (body)         => apiRequest('vendor-applications', 'POST', body),
  submitDriverApplication: (body)         => apiRequest('driver-applications', 'POST', body),
};
