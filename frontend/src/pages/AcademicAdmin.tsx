import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Field = { key: string; label: string; kind?: 'date' | 'number' | 'boolean' | 'reference'; source?: string };
type Config = { title: string; fields: Field[] };
const configs: Record<string, Config> = {
  'academic-years': { title: 'Tahun ajaran', fields: [{ key: 'label', label: 'Label (contoh 2026/2027)' }, { key: 'start_on', label: 'Mulai', kind: 'date' }, { key: 'end_on', label: 'Selesai', kind: 'date' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  semesters: { title: 'Semester', fields: [{ key: 'academic_year_id', label: 'Tahun ajaran', kind: 'reference', source: 'academic-years' }, { key: 'name', label: 'Nama (Ganjil/Genap)' }, { key: 'start_on', label: 'Mulai', kind: 'date' }, { key: 'end_on', label: 'Selesai', kind: 'date' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  'grade-levels': { title: 'Tingkat', fields: [{ key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' }, { key: 'sort_order', label: 'Urutan', kind: 'number' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  'class-groups': { title: 'Rombel', fields: [{ key: 'academic_year_id', label: 'Tahun ajaran', kind: 'reference', source: 'academic-years' }, { key: 'grade_level_id', label: 'Tingkat', kind: 'reference', source: 'grade-levels' }, { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  subjects: { title: 'Mata pelajaran', fields: [{ key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  'question-categories': { title: 'Kategori soal', fields: [{ key: 'subject_id', label: 'Mata pelajaran', kind: 'reference', source: 'subjects' }, { key: 'name', label: 'Nama' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  'question-topics': { title: 'Topik soal', fields: [{ key: 'category_id', label: 'Kategori', kind: 'reference', source: 'question-categories' }, { key: 'name', label: 'Nama' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  'exam-types': { title: 'Jenis ujian', fields: [{ key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
  'exam-rooms': { title: 'Ruang ujian', fields: [{ key: 'academic_year_id', label: 'Tahun ajaran', kind: 'reference', source: 'academic-years' }, { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' }, { key: 'capacity', label: 'Kapasitas', kind: 'number' }, { key: 'active', label: 'Aktif', kind: 'boolean' }] },
};

function initial(fields: Field[], resource: string) {
  return Object.fromEntries(fields.map(field => [field.key, field.kind === 'boolean' ? !['academic-years', 'semesters'].includes(resource) : ''])) as Record<string, string | boolean>;
}

export default function AcademicAdmin() {
  const { resource = '' } = useParams();
  const config = configs[resource];
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [references, setReferences] = useState<Record<string, Record<string, unknown>[]>>({});
  const [form, setForm] = useState<Record<string, string | boolean>>({});
  const [editing, setEditing] = useState<number | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchDraft, setSearchDraft] = useState('');
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    if (!config) return;
    const result = await api.catalog(resource, page, search);
    setRows(result.rows); setTotal(result.total);
  }, [config, resource, page, search]);

  useEffect(() => {
    if (!config) return;
    setForm(initial(config.fields, resource)); setEditing(null); setPage(1); setSearch('');
    const sources = [...new Set(config.fields.map(field => field.source).filter((value): value is string => Boolean(value)))];
    Promise.all(sources.map(async source => [source, (await api.catalog(source)).rows] as const))
      .then(entries => setReferences(Object.fromEntries(entries))).catch(reason => setError(reason.message));
  }, [resource, config]);
  useEffect(() => { refresh().catch(reason => setError(reason.message)); }, [refresh]);

  if (!config) return <p role="alert">Halaman akademik tidak ditemukan.</p>;
  function edit(row: Record<string, unknown>) {
    setEditing(Number(row.id));
    setForm(Object.fromEntries(config.fields.map(field => [field.key, field.kind === 'boolean' ? Boolean(Number(row[field.key])) : String(row[field.key] ?? '')])));
    setNotice(''); window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  async function save(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('');
    const payload: Record<string, unknown> = {};
    for (const field of config.fields) {
      const value = form[field.key];
      payload[field.key] = field.kind === 'number' || field.kind === 'reference' ? Number(value) : value;
    }
    try {
      if (editing === null) await api.createCatalog(resource, payload);
      else await api.updateCatalog(resource, editing, payload);
      setNotice(editing === null ? 'Data ditambahkan.' : 'Data diperbarui.');
      setEditing(null); setForm(initial(config.fields, resource)); await refresh();
    } catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }

  return <div className="space-y-5">
    <div><h1 className="text-2xl font-bold">{config.title}</h1><p className="mt-1 text-sm text-[#536765]">Kelola data sekolah tanpa menghapus riwayat yang sudah dipakai.</p></div>
    {resource === 'subjects' && <div className="flex flex-wrap gap-2"><a className="inline-flex min-h-11 items-center rounded-lg border border-[#B6C9C5] px-4 text-sm font-semibold text-[#155451]" href="/api/v1/exports/subjects">Ekspor CSV</a><a className="inline-flex min-h-11 items-center rounded-lg border border-[#B6C9C5] px-4 text-sm font-semibold text-[#155451]" href="/imports/subjects">Impor mapel</a></div>}
    {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</p>}
    {notice && <p role="status" className="text-sm text-[#155451]">{notice}</p>}
    <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
      <Card className="order-2 min-w-0 p-5 xl:order-1">
        <form onSubmit={event => { event.preventDefault(); setPage(1); setSearch(searchDraft); }} className="flex gap-2">
          <label className="sr-only" htmlFor="catalog-search">Cari {config.title}</label>
          <Input id="catalog-search" value={searchDraft} onChange={event => setSearchDraft(event.target.value)} placeholder="Cari data" />
          <Button variant="outline">Cari</Button>
        </form>
        <div className="mt-4 divide-y divide-[#E1E7E5]">
          {rows.length === 0 && <p className="py-5 text-sm text-[#536765]">Belum ada data pada halaman ini.</p>}
          {rows.map(row => <div key={String(row.id)} className="flex items-start justify-between gap-3 py-3">
            <div className="min-w-0"><p className="break-words font-medium">{String(row.name ?? row.label ?? row.code ?? row.id)}</p><p className="mt-1 text-xs text-[#536765]">{row.code ? String(row.code) + ' · ' : ''}{Number(row.active) === 1 ? 'Aktif' : 'Tidak aktif'} · ID {String(row.id)}</p></div>
            <Button type="button" variant="outline" onClick={() => edit(row)}>Ubah</Button>
          </div>)}
        </div>
        <div className="mt-4 flex items-center justify-between text-sm"><span>{total} data</span><div className="flex gap-2"><Button type="button" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button type="button" variant="outline" disabled={page * 100 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div>
      </Card>
      <Card className="order-1 p-5 xl:order-2"><h2 className="text-lg font-semibold">{editing === null ? 'Tambah data' : 'Ubah data'}</h2>
        <form onSubmit={save} className="mt-4 space-y-4">
          {config.fields.map(field => <label key={field.key} className="block text-sm font-medium">
            {field.kind === 'boolean' ? <span className="flex min-h-11 items-center gap-3"><input type="checkbox" checked={Boolean(form[field.key])} onChange={event => setForm({ ...form, [field.key]: event.target.checked })} />{field.label}</span> : <>{field.label}
              {field.kind === 'reference' ? <select required className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={String(form[field.key] ?? '')} onChange={event => setForm({ ...form, [field.key]: event.target.value })}><option value="">Pilih {field.label.toLowerCase()}</option>{(references[field.source ?? ''] ?? []).map(option => <option key={String(option.id)} value={String(option.id)}>{String(option.name ?? option.label ?? option.code)}</option>)}</select> : <Input required type={field.kind === 'date' ? 'date' : field.kind === 'number' ? 'number' : 'text'} className="mt-1" value={String(form[field.key] ?? '')} onChange={event => setForm({ ...form, [field.key]: event.target.value })} />}</>}
          </label>)}
          <div className="flex flex-wrap gap-2"><Button disabled={busy}>Simpan</Button>{editing !== null && <Button type="button" variant="outline" onClick={() => { setEditing(null); setForm(initial(config.fields, resource)); }}>Batal</Button>}</div>
        </form>
      </Card>
    </div>
  </div>;
}
