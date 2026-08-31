import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { api } from '../api.js';
import { Loading } from '../components/StateViews.jsx';

const EMPTY = { customer_id: '', name: '', description: '', price: '', periodicity: 'mensual', status: 'activa' };

export default function SubscriptionForm() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [form, setForm] = useState({ ...EMPTY, customer_id: searchParams.get('customer_id') || '' });
  const [customers, setCustomers] = useState([]);
  const [fields, setFields] = useState({});
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api.listCustomers().then(setCustomers).catch(() => {});
  }, []);

  useEffect(() => {
    if (!isEdit) return;
    api.getSubscription(id)
      .then((s) => setForm({
        customer_id: s.customer_id,
        name: s.name,
        description: s.description || '',
        price: s.price,
        periodicity: s.periodicity,
        status: s.status,
      }))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id, isEdit]);

  function update(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setFields({});
    setSaving(true);
    try {
      const saved = isEdit ? await api.updateSubscription(id, form) : await api.createSubscription(form);
      navigate(`/subscriptions/${saved.id}`);
    } catch (err) {
      setError(err.message);
      setFields(err.fields || {});
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <Loading />;

  return (
    <div>
      <h1>{isEdit ? 'Editar suscripción' : 'Nueva suscripción'}</h1>
      <p className="subtitle">Una suscripción siempre pertenece a un cliente.</p>

      <form className="card" onSubmit={handleSubmit}>
        {error && <p className="field-error">{error}</p>}
        <div className="form-grid">
          <div className="form-field full">
            <label>Cliente</label>
            <select
              value={form.customer_id}
              onChange={(e) => update('customer_id', e.target.value)}
              disabled={isEdit}
            >
              <option value="">Selecciona un cliente</option>
              {customers.map((c) => (
                <option key={c.id} value={c.id}>{c.name} ({c.email})</option>
              ))}
            </select>
            {fields.customer_id && <span className="field-error">{fields.customer_id}</span>}
          </div>

          <div className="form-field">
            <label>Nombre</label>
            <input value={form.name} onChange={(e) => update('name', e.target.value)} />
            {fields.name && <span className="field-error">{fields.name}</span>}
          </div>

          <div className="form-field">
            <label>Precio</label>
            <input type="number" step="0.01" value={form.price} onChange={(e) => update('price', e.target.value)} />
            {fields.price && <span className="field-error">{fields.price}</span>}
          </div>

          <div className="form-field">
            <label>Periodicidad</label>
            <select value={form.periodicity} onChange={(e) => update('periodicity', e.target.value)}>
              <option value="mensual">Mensual</option>
              <option value="anual">Anual</option>
            </select>
            {fields.periodicity && <span className="field-error">{fields.periodicity}</span>}
          </div>

          {!isEdit && (
            <div className="form-field">
              <label>Estado inicial</label>
              <select value={form.status} onChange={(e) => update('status', e.target.value)}>
                <option value="activa">Activa</option>
                <option value="pausada">Pausada</option>
                <option value="cancelada">Cancelada</option>
              </select>
            </div>
          )}

          <div className="form-field full">
            <label>Descripción</label>
            <textarea value={form.description} onChange={(e) => update('description', e.target.value)} />
          </div>
        </div>

        <div className="form-actions">
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? 'Guardando...' : 'Guardar suscripción'}
          </button>
          <button type="button" className="btn" onClick={() => navigate(-1)}>Cancelar</button>
        </div>
      </form>
    </div>
  );
}
