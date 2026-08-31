const BASE = '/api';

async function request(path, options = {}) {
  const res = await fetch(`${BASE}${path}`, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });

  const isJson = res.headers.get('content-type')?.includes('application/json');
  const body = isJson ? await res.json() : null;

  if (!res.ok) {
    const message = body?.error || `Error ${res.status}`;
    const error = new Error(message);
    error.fields = body?.fields || {};
    throw error;
  }

  return body;
}

export const api = {
  // Clientes
  listCustomers: () => request('/customers'),
  getCustomer: (id) => request(`/customers/${id}`),
  createCustomer: (data) => request('/customers', { method: 'POST', body: JSON.stringify(data) }),
  updateCustomer: (id, data) => request(`/customers/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteCustomer: (id) => request(`/customers/${id}`, { method: 'DELETE' }),

  // Suscripciones
  listSubscriptions: (status) => request(`/subscriptions${status ? `?status=${status}` : ''}`),
  getSubscription: (id) => request(`/subscriptions/${id}`),
  createSubscription: (data) => request('/subscriptions', { method: 'POST', body: JSON.stringify(data) }),
  updateSubscription: (id, data) => request(`/subscriptions/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  changeSubscriptionStatus: (id, status) =>
    request(`/subscriptions/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }),
  deleteSubscription: (id) => request(`/subscriptions/${id}`, { method: 'DELETE' }),

  // Motor de cobro
  runChargeEngine: (force) => request('/charge-engine/run', { method: 'POST', body: JSON.stringify(force ? { force } : {}) }),

  // Reloj simulado
  getClock: () => request('/clock'),
  advanceClock: (seconds) => request('/clock/advance', { method: 'POST', body: JSON.stringify({ seconds }) }),
  resetClock: () => request('/clock/reset', { method: 'POST' }),
};
