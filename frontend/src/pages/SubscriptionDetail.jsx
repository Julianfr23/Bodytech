import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { Loading, ErrorState, EmptyState } from '../components/StateViews.jsx';
import StatusBadge from '../components/StatusBadge.jsx';

const STATUSES = ['activa', 'pausada', 'cancelada'];

export default function SubscriptionDetail() {
  const { id } = useParams();
  const [subscription, setSubscription] = useState(null);
  const [error, setError] = useState(null);
  const [changingStatus, setChangingStatus] = useState(false);

  function load() {
    setError(null);
    setSubscription(null);
    api.getSubscription(id).then(setSubscription).catch((e) => setError(e.message));
  }

  useEffect(load, [id]);

  async function handleStatusChange(newStatus) {
    setChangingStatus(true);
    try {
      await api.changeSubscriptionStatus(id, newStatus);
      load();
    } catch (e) {
      alert(e.message);
    } finally {
      setChangingStatus(false);
    }
  }

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (!subscription) return <Loading />;

  return (
    <div>
      <div className="page-header">
        <div>
          <h1>{subscription.name}</h1>
          <p className="subtitle">
            ${Number(subscription.price).toLocaleString('es-CO')} · {subscription.periodicity} ·{' '}
            <Link to={`/customers/${subscription.customer_id}`}>Ver cliente</Link>
          </p>
        </div>
        <Link to={`/subscriptions/${id}/edit`} className="btn">Editar</Link>
      </div>

      <div className="card">
        <h2>Estado</h2>
        <div className="status-controls">
          <StatusBadge status={subscription.status} />
          {STATUSES.filter((s) => s !== subscription.status).map((s) => (
            <button key={s} className="btn btn-sm" disabled={changingStatus} onClick={() => handleStatusChange(s)}>
              Marcar como {s}
            </button>
          ))}
        </div>
        {subscription.description && <p className="muted" style={{ marginTop: 12 }}>{subscription.description}</p>}
        <p className="muted">
          Último cobro exitoso: {subscription.last_charge_at || 'nunca se ha cobrado'}
        </p>
      </div>

      <div className="card">
        <h2>Historial de intentos de cobro</h2>
        {subscription.charge_attempts.length === 0 && (
          <EmptyState message="Todavía no se ha generado ningún intento de cobro para esta suscripción." />
        )}
        {subscription.charge_attempts.length > 0 && (
          <table>
            <thead>
              <tr>
                <th>Intento #</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Respuesta pasarela</th>
                <th>Resuelto</th>
              </tr>
            </thead>
            <tbody>
              {subscription.charge_attempts.map((a) => (
                <tr key={a.id}>
                  <td>{a.attempt_number} / 3</td>
                  <td>{a.attempted_at}</td>
                  <td><StatusBadge status={a.status} /></td>
                  <td>{a.gateway_response || '—'}</td>
                  <td>{a.resolved_at || 'en espera'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
