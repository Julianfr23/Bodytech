import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { Loading, ErrorState, EmptyState } from '../components/StateViews.jsx';

export default function CustomerList() {
  const [customers, setCustomers] = useState(null);
  const [error, setError] = useState(null);

  function load() {
    setError(null);
    setCustomers(null);
    api.listCustomers().then(setCustomers).catch((e) => setError(e.message));
  }

  useEffect(load, []);

  async function handleDelete(id) {
    if (!confirm('¿Eliminar este cliente? También se eliminarán sus suscripciones.')) return;
    try {
      await api.deleteCustomer(id);
      load();
    } catch (e) {
      alert(e.message);
    }
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <h1>Clientes</h1>
          <p className="subtitle">Administra los clientes del motor de suscripciones.</p>
        </div>
        <Link to="/customers/new" className="btn btn-primary">+ Nuevo cliente</Link>
      </div>

      {error && <ErrorState message={error} onRetry={load} />}
      {!error && customers === null && <Loading />}
      {!error && customers && customers.length === 0 && (
        <EmptyState message="Todavía no hay clientes. Crea el primero con el botón de arriba." />
      )}

      {!error && customers && customers.length > 0 && (
        <div className="card">
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Documento</th>
                <th>Teléfono</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {customers.map((c) => (
                <tr key={c.id}>
                  <td><Link to={`/customers/${c.id}`}>{c.name}</Link></td>
                  <td>{c.email}</td>
                  <td>{c.document}</td>
                  <td>{c.phone}</td>
                  <td className="row-actions">
                    <Link className="btn btn-sm" to={`/customers/${c.id}/edit`}>Editar</Link>
                    <button className="btn btn-sm btn-danger" onClick={() => handleDelete(c.id)}>Eliminar</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
