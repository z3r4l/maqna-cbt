import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

type Row = Record<string, unknown>;

export default function AuditEvents() {
  const [rows, setRows] = useState<Row[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');
  useEffect(() => { api.auditEvents(page).then(data => { setRows(data.rows); setTotal(data.total); setError(''); }).catch(reason => setError((reason as Error).message)); }, [page]);
  return <section className="space-y-5"><div><h1 className="text-3xl font-bold">Aktivitas</h1><p className="mt-2 text-[#536765]">Jejak perubahan penting di sekolah. Guru melihat aktivitas miliknya.</p></div>{error && <p role="alert" className="text-red-700">{error}</p>}<div className="grid gap-3">{rows.map(row => <Card key={Number(row.id)}><CardContent className="flex flex-wrap items-start justify-between gap-3 p-4 text-sm"><div><strong>{String(row.action)}</strong><p className="mt-1 text-[#536765]">{String(row.username)} · {String(row.target_type)} #{String(row.target_id)}</p></div><time className="text-[#536765]">{String(row.created_at)}</time></CardContent></Card>)}</div>{rows.length === 0 && !error && <p>Belum ada aktivitas yang dapat dilihat.</p>}<div className="flex items-center justify-between text-sm"><span>{total} peristiwa</span><div className="flex gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" disabled={page * 50 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div></section>;
}
