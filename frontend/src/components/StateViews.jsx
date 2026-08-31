export function Loading({ label = 'Cargando...' }) {
  return <div className="state-box">{label}</div>;
}

export function ErrorState({ message, onRetry }) {
  return (
    <div className="state-box error">
      <p>{message}</p>
      {onRetry && (
        <button className="btn btn-sm" onClick={onRetry}>
          Reintentar
        </button>
      )}
    </div>
  );
}

export function EmptyState({ message }) {
  return <div className="state-box">{message}</div>;
}
