import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { Loading, ErrorState, EmptyState } from '../components/StateViews.jsx';
import StatusBadge from '../components/StatusBadge.jsx';

export default function SubscriptionList() {
  const [status, setStatus] = useState('');
  const [subscriptions, setSubscriptions] = useState(null);
  const [error, setError] = useState(null);

  function load() {
    setError(null);
    setSubscriptions(null);
    api.listSubscriptions(status || null).then(setSubscriptions).catch((e) => setError(e.message));
  }

  useEffect(load, [status]);

  return (
    <div>
      <div className="page-header">
        <div>
          <h1>Suscripciones</h1>
          <p className="subtitle">Todas las suscripciones registradas, con filtro por estado.</p>
        </div>
        <Link to="/subscriptions/new" className="btn btn-primary">+ Nueva suscripción</Link>
      </div>

      <div className="filters">
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Todos los estados</option>
          <option value="activa">Activa</option>
          <option value="pausada">Pausada</option>
          <option value="cancelada">Cancelada</option>
        </select>
      </div>

      {error && <ErrorState message={error} onRetry={load} />}
      {!error && subscriptions === null && <Loading />}
      {!error && subscriptions && subscriptions.length === 0 && (
        <EmptyState message="No hay suscripciones con ese filtro." />
      )}

      {!error && subscriptions && subscriptions.length > 0 && (
        <div className="card">
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Precio</th>
                <th>Periodicidad</th>
                <th>Estado</th>
                <th>Último cobro</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {subscriptions.map((s) => (
                <tr key={s.id}>
                  <td><Link to={`/subscriptions/${s.id}`}>{s.name}</Link></td>
                  <td>${Number(s.price).toLocaleString('es-CO')}</td>
                  <td>{s.periodicity}</td>
                  <td><StatusBadge status={s.status} /></td>
                  <td>{s.last_charge_at || '—'}</td>
                  <td><Link className="btn btn-sm" to={`/subscriptions/${s.id}`}>Ver</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
