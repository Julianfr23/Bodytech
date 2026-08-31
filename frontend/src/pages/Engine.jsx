import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { EmptyState } from '../components/StateViews.jsx';

export default function Engine() {
  const [clock, setClock] = useState(null);
  const [result, setResult] = useState(null);
  const [running, setRunning] = useState(false);
  const [force, setForce] = useState('');
  const [error, setError] = useState(null);

  function loadClock() {
    api.getClock().then(setClock).catch((e) => setError(e.message));
  }

  useEffect(loadClock, []);

  async function handleRun() {
    setRunning(true);
    setError(null);
    try {
      const res = await api.runChargeEngine(force || null);
      setResult(res);
    } catch (e) {
      setError(e.message);
    } finally {
      setRunning(false);
    }
  }

  async function handleAdvance(seconds) {
    try {
      const res = await api.advanceClock(seconds);
      setClock(res);
    } catch (e) {
      setError(e.message);
    }
  }

  async function handleReset() {
    const res = await api.resetClock();
    setClock(res);
  }

  return (
    <div>
      <h1>Motor de cobro</h1>
      <p className="subtitle">
        Ejecuta el ciclo de cobro manualmente. Puedes adelantar el reloj simulado para
        probar los reintentos (24h de espera) y el paso de un mes o un año sin esperar de verdad.
      </p>

      {error && <p className="field-error">{error}</p>}

      <div className="card">
        <h2>Reloj simulado</h2>
        {clock && (
          <div className="clock-box">
            <span>Hora actual de la app: <strong>{clock.now}</strong></span>
            <button className="btn btn-sm" onClick={() => handleAdvance(24 * 3600)}>+24 horas</button>
            <button className="btn btn-sm" onClick={() => handleAdvance(30 * 24 * 3600)}>+1 mes</button>
            <button className="btn btn-sm" onClick={() => handleAdvance(365 * 24 * 3600)}>+1 año</button>
            <button className="btn btn-sm" onClick={handleReset}>Reiniciar reloj</button>
          </div>
        )}
      </div>

      <div className="card">
        <h2>Ejecutar el motor</h2>
        <div className="form-field" style={{ maxWidth: 280, marginBottom: 14 }}>
          <label>Forzar resultado de la pasarela (opcional)</label>
          <select value={force} onChange={(e) => setForce(e.target.value)}>
            <option value="">Aleatorio (60/30/10)</option>
            <option value="aprobado">Forzar aprobado</option>
            <option value="rechazado">Forzar rechazado</option>
            <option value="timeout">Forzar timeout</option>
          </select>
        </div>
        <button className="btn btn-primary" onClick={handleRun} disabled={running}>
          {running ? 'Ejecutando...' : 'Correr motor de cobro'}
        </button>
      </div>

      {result && (
        <div className="card">
          <h2>Resultado de la última corrida</h2>
          <p className="muted">
            Ejecutado a las {result.ran_at} · {result.subscriptions_evaluated} suscripciones activas evaluadas ·{' '}
            {result.attempts_generated} intentos generados
          </p>
          {result.attempts.length === 0 && (
            <EmptyState message="Ninguna suscripción tenía un cobro pendiente en este momento." />
          )}
          {result.attempts.map((a) => (
            <div className="result-line" key={a.attempt_id}>
              Intento #{a.attempt_id} · suscripción{' '}
              <Link to={`/subscriptions/${a.subscription_id}`}>{a.subscription_id}</Link> · intento {a.attempt_number}/3
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
