export default function BarWave({ small = false }: { small?: boolean }) {
  return <div className={`bar-wave${small ? ' bar-wave--small' : ''}`} aria-hidden="true">
    {Array.from({ length: 5 }, (_, index) => <span key={index} data-wave-bar />)}
  </div>;
}
