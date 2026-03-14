export function MetricCard({ title, value, sub }: { title: string; value: string | number; sub?: string }) {
  return (
    <article className="metric-card">
      <header>{title}</header>
      <strong>{value}</strong>
      {sub ? <p>{sub}</p> : null}
    </article>
  );
}
