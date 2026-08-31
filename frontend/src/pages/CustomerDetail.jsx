import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api.js';
import { Loading, ErrorState, EmptyState } from '../components/StateViews.jsx';
import StatusBadge from '../components/StatusBadge.jsx';

export default function CustomerDetail() {
  const { id } = useParams();
  const [customer, setCustomer] = useState(null);
  const [error, setError] = useState(null);

  function load() {
    setError(null);
    setCustomer(null);
    api.getCustomer(id).then(setCustomer).catch((e) => setError(e.message));
  }

  useEffect(load, [id]);

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (!customer) return <Loading />;

  return (
    <div>
      <div className="page-header">
        <div>
          <h1>{customer.name}</h1>
          <p className="subtitle">{customer.email} · {customer.document} · {customer.phone}</p>
        </div>
        <Link to={`/customers/${id}/edit`} className="btn">Editar cliente</Link>
      </div>

      <div className="page-header">
        <h2>Suscripciones</h2>
        <Link to={`/subscriptions/new?customer_id=${id}`} className="btn btn-primary">+ Nueva suscripción</Link>
      </div>

      {customer.subscriptions.length === 0 && (
        <EmptyState message="Este cliente todavía no tiene suscripciones." />
      )}

      {customer.subscriptions.length > 0 && (
        <div className="card">
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Precio</th>
                <th>Periodicidad</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {customer.subscriptions.map((s) => (
                <tr key={s.id}>
                  <td><Link to={`/subscriptions/${s.id}`}>{s.name}</Link></td>
                  <td>${Number(s.price).toLocaleString('es-CO')}</td>
                  <td>{s.periodicity}</td>
                  <td><StatusBadge status={s.status} /></td>
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
