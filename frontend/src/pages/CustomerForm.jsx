import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { Loading } from '../components/StateViews.jsx';

const EMPTY = { name: '', email: '', document: '', phone: '' };

export default function CustomerForm() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [form, setForm] = useState(EMPTY);
  const [fields, setFields] = useState({});
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isEdit) return;
    api.getCustomer(id)
      .then((c) => setForm({ name: c.name, email: c.email, document: c.document, phone: c.phone }))
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
      const saved = isEdit ? await api.updateCustomer(id, form) : await api.createCustomer(form);
      navigate(`/customers/${saved.id}`);
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
      <h1>{isEdit ? 'Editar cliente' : 'Nuevo cliente'}</h1>
      <p className="subtitle">Nombre, correo, documento y teléfono del cliente.</p>

      <form className="card" onSubmit={handleSubmit}>
        {error && <p className="field-error">{error}</p>}
        <div className="form-grid">
          <div className="form-field">
            <label>Nombre</label>
            <input value={form.name} onChange={(e) => update('name', e.target.value)} />
            {fields.name && <span className="field-error">{fields.name}</span>}
          </div>
          <div className="form-field">
            <label>Correo</label>
            <input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
            {fields.email && <span className="field-error">{fields.email}</span>}
          </div>
          <div className="form-field">
            <label>Documento</label>
            <input value={form.document} onChange={(e) => update('document', e.target.value)} />
            {fields.document && <span className="field-error">{fields.document}</span>}
          </div>
          <div className="form-field">
            <label>Teléfono</label>
            <input value={form.phone} onChange={(e) => update('phone', e.target.value)} />
            {fields.phone && <span className="field-error">{fields.phone}</span>}
          </div>
        </div>

        <div className="form-actions">
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? 'Guardando...' : 'Guardar cliente'}
          </button>
          <button type="button" className="btn" onClick={() => navigate(-1)}>Cancelar</button>
        </div>
      </form>
    </div>
  );
}
