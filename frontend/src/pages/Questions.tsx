import { lazy, Suspense, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import MathText from '@/components/MathText';
import BarWave from '@/components/BarWave';

const RichQuestionEditor = lazy(() => import('@/components/RichQuestionEditor').then(module => ({ default: module.RichQuestionEditor })));

type Row = Record<string, unknown>;
type Masters = { subjects: Row[]; categories: Row[]; topics: Row[] };
type QuestionType = 'mcq' | 'essay' | 'multi_mcq' | 'true_false' | 'matching';
type Option = { body: string; isCorrect: boolean; matchBody?: string; matchChoiceIndex?: number | null };
const emptyOptions: Option[] = [{ body: '', isCorrect: true }, { body: '', isCorrect: false }];
function text(row: Row, key: string) { return String(row[key] ?? ''); }

export function QuestionList() {
  const [rows, setRows] = useState<Row[]>([]);
  const [masters, setMasters] = useState<Masters>({ subjects: [], categories: [], topics: [] });
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('');
  const [error, setError] = useState('');
  const [jsonFile, setJsonFile] = useState<File | null>(null);
  const [jsonPreview, setJsonPreview] = useState<Awaited<ReturnType<typeof api.previewQuestionJson>> | null>(null);
  const [keyFile, setKeyFile] = useState<File | null>(null);
  const [keyPreview, setKeyPreview] = useState<Awaited<ReturnType<typeof api.previewQuestionKeys>> | null>(null);
  const [packageFile, setPackageFile] = useState<File | null>(null);
  const [packageMode, setPackageMode] = useState<'append' | 'replace'>('append');
  const [packagePreview, setPackagePreview] = useState<(Awaited<ReturnType<typeof api.previewQuestionPackage>> & { kind: 'zip' | 'docx' }) | null>(null);
  const [importBusy, setImportBusy] = useState(false);
  async function refresh() { const result = await api.questions(page, search, filter); setRows(result.rows); setTotal(result.total); }
  useEffect(() => { api.questionMasters().then(setMasters).catch(reason => setError(reason.message)); }, []);
  useEffect(() => { refresh().catch(reason => setError(reason.message)); }, [page, search, filter]);
  async function archive(id: number) {
    if (!window.confirm('Arsipkan soal ini? Soal tidak dihapus permanen.')) return;
    try { await api.archiveQuestion(id); await refresh(); } catch (reason) { setError((reason as Error).message); }
  }
  async function previewJson() {
    if (!jsonFile) return;
    setImportBusy(true); setError(''); setJsonPreview(null);
    try {
      if (jsonFile.size > 2 * 1024 * 1024) throw new Error('JSON maksimal 2 MB.');
      const parsed = JSON.parse(await jsonFile.text());
      setJsonPreview(await api.previewQuestionJson(parsed));
    } catch (reason) { setError((reason as Error).message); }
    finally { setImportBusy(false); }
  }
  async function commitJson() {
    if (!jsonPreview || !window.confirm('Impor ' + jsonPreview.ready + ' soal sebagai draf?')) return;
    setImportBusy(true); setError('');
    try { await api.commitQuestionJson(jsonPreview.id); setJsonPreview(null); setJsonFile(null); await refresh(); }
    catch (reason) { setError((reason as Error).message); }
    finally { setImportBusy(false); }
  }
  async function previewKeys() {
    if (!keyFile) return;
    setImportBusy(true); setError(''); setKeyPreview(null);
    try { setKeyPreview(await api.previewQuestionKeys(keyFile)); }
    catch (reason) { setError((reason as Error).message); }
    finally { setImportBusy(false); }
  }
  async function commitKeys() {
    if (!keyPreview || !window.confirm('Terapkan perubahan kunci pada ' + keyPreview.count + ' soal draf?')) return;
    setImportBusy(true); setError('');
    try { await api.commitQuestionKeys(keyPreview.id); setKeyPreview(null); setKeyFile(null); await refresh(); }
    catch (reason) { setError((reason as Error).message); }
    finally { setImportBusy(false); }
  }
  async function previewPackage() {
    if (!packageFile) return;
    const kind = packageFile.name.toLowerCase().endsWith('.docx') ? 'docx' : 'zip';
    setImportBusy(true); setError(''); setPackagePreview(null);
    try { setPackagePreview({ ...await api.previewQuestionPackage(kind, packageFile, packageMode), kind }); }
    catch (reason) { setError((reason as Error).message); }
    finally { setImportBusy(false); }
  }
  async function commitPackage() {
    if (!packagePreview || !window.confirm('Impor ' + packagePreview.count + ' baris dari ' + packagePreview.kind.toUpperCase() + '?')) return;
    setImportBusy(true); setError('');
    try { await api.commitQuestionPackage(packagePreview.kind, packagePreview.id); setPackagePreview(null); setPackageFile(null); await refresh(); }
    catch (reason) { setError((reason as Error).message); }
    finally { setImportBusy(false); }
  }
  return <div className="space-y-5"><div className="flex flex-wrap items-end justify-between gap-4"><div><h1 className="text-2xl font-bold">Bank soal</h1><p className="mt-1 text-sm text-[#536765]">Soal untuk mata pelajaran Anda.</p></div><Link to="/questions/new" className="rounded-lg bg-[#155451] px-4 py-3 text-sm font-semibold text-white">Tulis soal</Link></div>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
    <Card className="space-y-3 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">Arsip JSON soal</h2><p className="text-sm text-[#536765]">Impor diawali pratinjau. Soal baru tersimpan sebagai draf.</p></div><a className="rounded-lg border border-[#B6C9C5] px-3 py-2 text-sm font-semibold" href="/api/v1/questions/export/json">Unduh JSON</a></div><div className="flex flex-wrap items-end gap-3"><label className="text-sm">Berkas JSON<Input type="file" accept=".json,application/json" className="mt-1" onChange={event => { setJsonFile(event.target.files?.[0] ?? null); setJsonPreview(null); }} /></label><Button type="button" variant="outline" disabled={!jsonFile || importBusy} onClick={() => void previewJson()}>Periksa berkas</Button></div>{jsonPreview && <div className="space-y-3 rounded-lg border p-3"><p className="text-sm font-semibold">{jsonPreview.ready} siap Â· {jsonPreview.skipped} dilewati</p><div className="max-h-56 overflow-auto text-sm">{jsonPreview.rows.map(row => <p key={row.rowNumber} className="border-b py-1">{row.rowNumber}. {row.subjectCode} Â· {row.type} Â· {row.status} Â· {row.body}</p>)}</div><Button type="button" disabled={importBusy || jsonPreview.ready === 0} onClick={() => void commitJson()}>Impor sebagai draf</Button></div>}</Card>
    <Card className="space-y-3 p-4"><h2 className="font-semibold">Naskah dan kunci soal</h2><p className="text-sm text-[#536765]">Unduh soal terbit. Kunci dibuat sebagai dokumen terpisah dan hanya tersedia bagi pengelola soal.</p><div className="flex flex-wrap gap-2">{(['pdf', 'docx'] as const).flatMap(format => ['questions', 'keys'].map(variant => <a key={format + variant} className="rounded-lg border border-[#B6C9C5] px-3 py-2 text-sm font-semibold" href={'/api/v1/questions/print/' + format + '?variant=' + variant + (filter ? '&subjectId=' + filter : '')}>{variant === 'keys' ? 'Kunci' : 'Naskah'} {format.toUpperCase()}</a>))}</div></Card>
    <Card className="space-y-3 p-4"><h2 className="font-semibold">Impor kunci soal</h2><p className="text-sm text-[#536765]">Untuk soal draf yang belum masuk snapshot ujian. Periksa perubahan sebelum menyimpan.</p><a className="text-sm font-semibold text-[#155451] underline" href="/api/v1/questions/import/keys/template">Unduh template CSV</a><div className="flex flex-wrap items-end gap-3"><label className="text-sm">Berkas CSV atau XLSX<Input type="file" accept=".csv,.xlsx" className="mt-1" onChange={event => { setKeyFile(event.target.files?.[0] ?? null); setKeyPreview(null); }} /></label><Button type="button" variant="outline" disabled={!keyFile || importBusy} onClick={() => void previewKeys()}>Periksa kunci</Button></div>{keyPreview && <div className="space-y-3 rounded-lg border p-3"><p className="text-sm font-semibold">{keyPreview.count} soal siap diperbarui</p><div className="max-h-56 overflow-auto text-sm">{keyPreview.rows.map(row => <p key={row.rowNumber} className="border-b py-1">#{row.questionId} Â· {row.type} Â· {row.currentKey} â†’ {row.newKey}</p>)}</div><Button type="button" disabled={importBusy} onClick={() => void commitKeys()}>Terapkan kunci</Button></div>}</Card>
    <Card className="space-y-3 p-4"><h2 className="font-semibold">Paket soal dan Word</h2><p className="text-sm text-[#536765]">ZIP menyertakan gambar. DOCX memakai baris QUESTION sampai END; gambar yang disisipkan pada blok soal akan ikut diimpor.</p><div className="flex flex-wrap gap-3 text-sm"><a className="font-semibold text-[#155451] underline" href="/api/v1/questions/package.zip">Unduh paket ZIP</a><a className="font-semibold text-[#155451] underline" href="/api/v1/questions/import/docx/template">Unduh template Word</a></div><div className="flex flex-wrap items-end gap-3"><label className="text-sm">Berkas ZIP atau DOCX<Input type="file" accept=".zip,.docx" className="mt-1" onChange={event => { setPackageFile(event.target.files?.[0] ?? null); setPackagePreview(null); }} /></label><label className="text-sm">Mode ZIP<select className="mt-1 block min-h-11 rounded-md border bg-white px-3" value={packageMode} onChange={event => setPackageMode(event.target.value as 'append' | 'replace')}><option value="append">Tambahkan, lewati duplikat</option><option value="replace">Ganti draf sendiri</option></select></label><Button type="button" variant="outline" disabled={!packageFile || importBusy} onClick={() => void previewPackage()}>Periksa paket</Button></div>{packagePreview && <div className="space-y-3 rounded-lg border p-3"><p className="text-sm font-semibold">{packagePreview.count} baris diperiksa</p><div className="max-h-56 overflow-auto text-sm">{packagePreview.rows.map(row => <p key={row.rowNumber} className="border-b py-1">{row.rowNumber}. {row.subjectCode} Â· {row.type} Â· {row.status} Â· {row.body}</p>)}</div><Button type="button" disabled={importBusy} onClick={() => void commitPackage()}>Impor paket</Button></div>}</Card>
    <Card className="p-4"><div className="grid gap-3 md:grid-cols-[1fr_220px]"><label className="text-sm">Cari soal<Input className="mt-1" value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} placeholder="Kata pada soal" /></label><label className="text-sm">Mata pelajaran<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={filter} onChange={event => { setFilter(event.target.value); setPage(1); }}><option value="">Semua</option>{masters.subjects.map(subject => <option key={Number(subject.id)} value={Number(subject.id)}>{text(subject, 'name')}</option>)}</select></label></div></Card>
    <div className="grid gap-3">{rows.length === 0 && <Card className="p-6 text-sm text-[#536765]">Belum ada soal pada filter ini.</Card>}{rows.map(row => <Card key={Number(row.id)} className="p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0 flex-1"><p className="text-xs font-semibold uppercase tracking-wide text-[#536765]">{text(row, 'subject_name')} Â· {text(row, 'status')}</p><div className="mt-2 font-medium"><MathText text={text(row, 'body')} /></div><p className="mt-2 text-xs text-[#536765]">Bobot {text(row, 'weight')}</p></div><div className="flex gap-2"><Link className="rounded-lg border border-[#B6C9C5] px-3 py-2 text-sm" to={'/questions/' + row.id}>Lihat</Link><Button type="button" variant="outline" onClick={() => void archive(Number(row.id))}>Arsipkan</Button></div></div></Card>)}</div>
    <div className="flex items-center justify-between text-sm"><span>{total} soal</span><div className="flex gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button variant="outline" disabled={page * 20 >= total} onClick={() => setPage(page + 1)}>Berikutnya</Button></div></div>
  </div>;
}

export function QuestionEditor({ bankId: bankIdProp, subjectId: subjectIdProp, questionId, onSaved, onClose }: { bankId?: number; subjectId?: number; questionId?: number | null; onSaved?: () => void; onClose?: () => void } = {}) {
  const navigate = useNavigate();
  const { id: idParam } = useParams();
  const id = questionId !== undefined ? questionId : (idParam && idParam !== 'new' ? Number(idParam) : null);
  const [masters, setMasters] = useState<Masters>({ subjects: [], categories: [], topics: [] });
  const [subjectId, setSubjectId] = useState(subjectIdProp ? String(subjectIdProp) : '');
  const [topicId, setTopicId] = useState('');
  const [body, setBody] = useState('');
  const [explanation, setExplanation] = useState('');
  const [rubric, setRubric] = useState('');
  const [questionType, setQuestionType] = useState<QuestionType>('mcq');
  const [weight, setWeight] = useState('1');
  const [options, setOptions] = useState<Option[]>(() => bankIdProp ? Array.from({ length: 4 }, (_, index) => ({ body: '', isCorrect: index === 0 })) : emptyOptions.map(option => ({ ...option })));
  const [matchingChoices, setMatchingChoices] = useState<string[]>(['', '']);
  const [status, setStatus] = useState('draft');
  const [assets, setAssets] = useState<Row[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [loaded, setLoaded] = useState(id === null);
  const [versionRows, setVersionRows] = useState<{ id: number; version_no: number; status: string }[]>([]);
  useEffect(() => { api.questionMasters().then(setMasters).catch(reason => setError(reason.message)); }, []);
  useEffect(() => {
    if (!id) return;
    api.question(id).then(row => {
      setSubjectId(text(row, 'subject_id')); setTopicId(text(row, 'topic_id')); setBody(text(row, 'body'));
      setExplanation(text(row, 'explanation')); setRubric(text(row, 'rubric')); setQuestionType(text(row, 'question_type') as QuestionType); setWeight(text(row, 'weight')); setStatus(text(row, 'status'));
      const loadedChoices = (row.matching_choices as Row[] | undefined) ?? [];
      setMatchingChoices(loadedChoices.map(choice => text(choice, 'body')));
      setOptions(((row.options as Row[]) ?? []).map(option => ({ body: text(option, 'body'), isCorrect: Number(option.is_correct) === 1, matchBody: text(option, 'match_body'), matchChoiceIndex: loadedChoices.findIndex(choice => Number(choice.id) === Number(option.match_choice_id)) })));
      setAssets((row.assets as Row[]) ?? []);
      setLoaded(true);
    }).catch(reason => setError(reason.message));
    api.questionVersions(id).then(setVersionRows).catch(() => undefined);
  }, [id]);
  const categoryIds = masters.categories.filter(category => text(category, 'subject_id') === subjectId).map(category => Number(category.id));
  const topics = masters.topics.filter(topic => categoryIds.includes(Number(topic.category_id)));
  function setOption(index: number, value: Partial<Option>) { setOptions(current => current.map((option, position) => position === index ? { ...option, ...value } : option)); }
  async function save(event: React.FormEvent, publish: boolean) {
    event.preventDefault(); setBusy(true); setError('');
    try {
      const payload = { bankId: bankIdProp ?? undefined, subjectId: Number(subjectId), topicId: topicId ? Number(topicId) : null, body, explanation, rubric, questionType, weight: Number(weight), matchingChoices: questionType === 'matching' ? matchingChoices.map(body => ({ body })) : [], options: questionType === 'essay' ? [] : options.map(option => ({ body: option.body, isCorrect: option.isCorrect, ...(questionType === 'matching' ? { matchChoiceIndex: option.matchChoiceIndex } : {}) })), status: publish ? 'published' : 'draft' };
      const row = id ? await api.updateQuestion(id, payload) : await api.createQuestion(payload);
      if (file && !publish) await api.uploadQuestionAsset(Number(row.id), file);
      if (onSaved) { onSaved(); return; }
      navigate('/questions/' + row.id, { replace: true });
      setStatus(text(row, 'status')); setAssets((row.assets as Row[]) ?? []);
      setFile(null);
    } catch (reason) { setError((reason as Error).message); } finally { setBusy(false); }
  }
  const locked = status === 'published';
  async function createRevision() {
    if (!id) return;
    setBusy(true); setError('');
    try { const revision = await api.createQuestionVersion(id); navigate('/question-versions/' + revision.id); }
    catch (reason) { setError((reason as Error).message); }
    finally { setBusy(false); }
  }
  return <div className="space-y-5"><div>{onClose ? <button type="button" className="text-sm text-[#155451] underline" onClick={onClose}>â† Kelola soal</button> : <Link className="text-sm text-[#155451] underline" to="/questions">â† Bank soal</Link>}<h1 className="mt-3 text-2xl font-bold">{id ? 'Soal dan pratinjau' : 'Tulis soal'}</h1><p className="mt-1 text-sm text-[#536765]">Draf boleh belum lengkap. Terbitkan setelah jawaban benar ditetapkan.</p>{locked && <div className="mt-3 flex flex-wrap items-center gap-3"><Button type="button" disabled={busy} onClick={() => void createRevision()}>Buat revisi</Button>{versionRows.map(version => <Link key={version.id} className="text-sm text-[#155451] underline" to={'/question-versions/' + version.id}>Versi {version.version_no} ({version.status})</Link>)}</div>}</div>
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
    <div className={"grid items-start gap-5 " + (onClose ? "" : "xl:grid-cols-[minmax(0,1fr)_360px]")}>
      <Card className="min-w-0 p-5"><form className="min-w-0 space-y-5" onSubmit={event => void save(event, true)}><fieldset disabled={busy || locked || !loaded} className="min-w-0 max-w-full space-y-4"><div className="grid gap-4 md:grid-cols-2"><label className="block text-sm">Mata pelajaran<select required disabled={Boolean(bankIdProp)} value={subjectId} onChange={event => { setSubjectId(event.target.value); setTopicId(''); }} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3"><option value="">Pilih mata pelajaran</option>{masters.subjects.map(subject => <option key={Number(subject.id)} value={Number(subject.id)}>{text(subject, 'name')}</option>)}</select></label><label className="block text-sm">Topik (opsional)<select value={topicId} onChange={event => setTopicId(event.target.value)} className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3"><option value="">Tanpa topik</option>{topics.map(topic => <option key={Number(topic.id)} value={Number(topic.id)}>{text(topic, 'name')}</option>)}</select></label></div>
        <label className="block text-sm">Jenis soal<select className="mt-1 block min-h-11 w-full rounded-md border border-[#B6C9C5] bg-white px-3" value={questionType} onChange={event => setQuestionType(event.target.value as QuestionType)}><option value="mcq">Pilihan ganda</option><option value="multi_mcq">Pilihan jamak</option><option value="true_false">Benar atau salah per pernyataan</option><option value="matching">Menjodohkan</option><option value="essay">Esai</option></select></label>
        <div><label className="block text-sm" id="question-body-label">Pertanyaan</label><Suspense fallback={<div className="flex min-h-48 items-center justify-center" role="status" aria-label="Memuat editor"><BarWave /></div>}><RichQuestionEditor key={id ?? 'new'} value={body} onChange={setBody} disabled={busy || locked || !loaded} /></Suspense><p className="mt-1 text-xs text-[#536765]">Gunakan $...$ untuk rumus. Gambar soal dapat diunggah pada draf.</p></div>
        {questionType === 'essay' ? <label className="block text-sm">Rubrik penilaian<textarea required value={rubric} onChange={event => setRubric(event.target.value)} rows={4} className="mt-1 block w-full rounded-md border border-[#B6C9C5] bg-white p-3" /></label> : questionType === 'matching' ? <div className="space-y-5 rounded-xl border bg-[#F8FAFB] p-4">
          <section><div className="flex items-center justify-between gap-2"><h2 className="font-semibold text-[#155451]">Pilihan jawaban / target (kolom kanan)</h2><Button type="button" variant="outline" disabled={matchingChoices.length >= 12} onClick={() => setMatchingChoices(current => [...current, ''])}>+ Tambah pilihan</Button></div><p className="mt-1 text-xs text-[#536765]">Pilihan ini bisa memuat pengecoh. Setiap pilihan hanya dapat dipakai sekali.</p><div className="mt-3 space-y-3">{matchingChoices.map((choice, index) => <div key={index} className="flex items-start gap-2"><span className="mt-4 text-sm font-semibold">{String.fromCharCode(65 + index)}</span><div className="min-w-0 flex-1"><Suspense fallback={<div className="min-h-24" />}><RichQuestionEditor value={choice} onChange={value => setMatchingChoices(current => current.map((item, at) => at === index ? value : item))} disabled={busy || locked || !loaded} ariaLabel={'Pilihan pasangan ' + (index + 1)} compact /></Suspense></div><Button type="button" variant="outline" disabled={matchingChoices.length <= 2} aria-label={'Hapus pilihan ' + (index + 1)} onClick={() => { setMatchingChoices(current => current.filter((_, at) => at !== index)); setOptions(current => current.map(option => ({ ...option, matchChoiceIndex: option.matchChoiceIndex === index ? null : option.matchChoiceIndex !== undefined && option.matchChoiceIndex !== null && option.matchChoiceIndex > index ? option.matchChoiceIndex - 1 : option.matchChoiceIndex }))); }}>Hapus</Button></div>)}</div></section>
          <section><div className="flex items-center justify-between gap-2"><h2 className="font-semibold text-[#155451]">Pernyataan dan kunci pasangan (kolom kiri)</h2><Button type="button" variant="outline" disabled={options.length >= 12} onClick={() => setOptions(current => [...current, { body: '', isCorrect: false, matchChoiceIndex: null }])}>+ Tambah baris</Button></div><div className="mt-3 overflow-x-auto"><table className="w-full min-w-[560px] border-collapse text-sm"><thead><tr className="bg-white text-left"><th className="border p-2">No</th><th className="border p-2">Pernyataan</th><th className="border p-2">Kunci pasangan</th><th className="border p-2">Aksi</th></tr></thead><tbody>{options.map((option, index) => <tr key={index}><td className="border p-2">{index + 1}</td><td className="border p-2"><RichQuestionEditor value={option.body} onChange={value => setOption(index, { body: value })} disabled={busy || locked || !loaded} ariaLabel={'Pernyataan ' + (index + 1)} compact /></td><td className="border p-2"><select className="min-h-10 w-full rounded-md border bg-white px-2" aria-label={'Kunci pasangan ' + (index + 1)} value={option.matchChoiceIndex ?? ''} onChange={event => setOption(index, { matchChoiceIndex: event.target.value === '' ? null : Number(event.target.value) })}><option value="">Pilih target</option>{matchingChoices.map((_, target) => <option key={target} value={target}>{String.fromCharCode(65 + target)}. Pilihan {target + 1}</option>)}</select></td><td className="border p-2"><Button type="button" variant="outline" disabled={options.length <= 2} aria-label={'Hapus pernyataan ' + (index + 1)} onClick={() => setOptions(current => current.filter((_, at) => at !== index))}>Hapus</Button></td></tr>)}</tbody></table></div></section>
        </div> : <div><div className="flex flex-wrap items-center justify-between gap-2"><h2 className="font-semibold">{questionType === 'true_false' ? 'Daftar pernyataan dan kunci nilai' : 'Pilihan jawaban'}</h2><Button type="button" variant="outline" disabled={options.length >= 12} onClick={() => setOptions(current => [...current, { body: '', isCorrect: false }])}>+ Tambah {questionType === 'true_false' ? 'pernyataan' : 'pilihan'}</Button></div>{questionType === 'true_false' ? <div className="mt-3 overflow-x-auto"><table className="w-full min-w-[480px] border-collapse text-sm"><thead><tr className="bg-[#F6F8F9] text-left"><th className="border p-2">No</th><th className="border p-2">Pernyataan</th><th className="border p-2">Kunci</th><th className="border p-2">Aksi</th></tr></thead><tbody>{options.map((option, index) => <tr key={index}><td className="border p-2">{index + 1}</td><td className="border p-2"><RichQuestionEditor value={option.body} onChange={value => setOption(index, { body: value })} disabled={busy || locked || !loaded} ariaLabel={'Pernyataan ' + (index + 1)} compact /></td><td className="border p-2"><select className="min-h-10 rounded-md border bg-white px-2" aria-label={'Kunci pernyataan ' + (index + 1)} value={option.isCorrect ? 'true' : 'false'} onChange={event => setOption(index, { isCorrect: event.target.value === 'true' })}><option value="true">Benar</option><option value="false">Salah</option></select></td><td className="border p-2"><Button type="button" variant="outline" disabled={options.length <= 2} onClick={() => setOptions(current => current.filter((_, at) => at !== index))}>Hapus</Button></td></tr>)}</tbody></table></div> : <div className="mt-3 space-y-3">{options.map((option, index) => <div key={index} className="flex flex-wrap items-center gap-2"><label className="flex items-center gap-2 text-sm"><input type={questionType === 'mcq' ? 'radio' : 'checkbox'} name={questionType === 'mcq' ? 'correct' : undefined} aria-label={'Jawaban benar pilihan ' + (index + 1)} checked={option.isCorrect} onChange={event => questionType === 'mcq' ? setOptions(current => current.map((item, at) => ({ ...item, isCorrect: at === index }))) : setOption(index, { isCorrect: event.target.checked })} />{String.fromCharCode(65 + index)}</label><div className="min-w-0 flex-1"><RichQuestionEditor value={option.body} onChange={value => setOption(index, { body: value })} disabled={busy || locked || !loaded} ariaLabel={'Pilihan ' + (index + 1)} compact /></div><Button type="button" variant="outline" disabled={options.length <= 2} onClick={() => setOptions(current => current.filter((_, at) => at !== index))}>Hapus</Button></div>)}</div>}</div>}
        <div className="grid gap-4 md:grid-cols-[130px_1fr]"><label className="block text-sm">Bobot<Input required type="number" min="0.01" max="1000" step="0.01" className="mt-1" value={weight} onChange={event => setWeight(event.target.value)} /></label><label className="block text-sm">Pembahasan (untuk guru)<textarea value={explanation} onChange={event => setExplanation(event.target.value)} rows={3} className="mt-1 block w-full rounded-md border border-[#B6C9C5] bg-white p-3" /></label></div>
        {id && <label className="block text-sm">Gambar soal (PNG, JPEG, WebP, maks. 5 MB)<Input type="file" accept="image/png,image/jpeg,image/webp" className="mt-1" onChange={event => setFile(event.target.files?.[0] ?? null)} /></label>}
      </fieldset><div className="flex flex-wrap justify-end gap-2 border-t pt-4">{onClose && <Button type="button" variant="outline" onClick={onClose}>Batal</Button>}<Button type="button" variant="outline" disabled={busy || locked || !loaded} onClick={event => void save(event as unknown as React.FormEvent, false)}>Simpan draf</Button><Button disabled={busy || locked || !loaded}>Tambah Soal</Button></div></form></Card>
      {!onClose && <Card className="min-w-0 p-5"><h2 className="text-lg font-semibold">Pratinjau</h2><div className="mt-4 break-words"><MathText text={body || 'Pertanyaan akan tampil di sini.'} /></div>{questionType !== 'essay' && <ol className="mt-4 list-[upper-alpha] space-y-2 pl-5 text-sm">{options.map((option, index) => <li key={index} className="break-words"><MathText text={option.body || 'Pilihan kosong'} />{questionType === 'matching' && <> â€” <MathText text={option.matchBody || 'Pasangan kosong'} /></>}</li>)}</ol>}{questionType === 'matching' && <div className="mt-4 rounded-lg bg-[#F8FAFB] p-3"><h3 className="font-semibold">Pilihan kanan</h3><ol className="mt-2 list-[upper-alpha] space-y-2 pl-5 text-sm">{matchingChoices.map((choice, index) => <li key={index}><MathText text={choice || 'Pilihan kosong'} /></li>)}</ol></div>}{assets.length > 0 && <div className="mt-5 space-y-3">{assets.map(asset => <img key={Number(asset.id)} src={'/api/v1/question-assets/' + asset.id} alt={text(asset, 'original_name')} className="max-h-64 max-w-full rounded-lg border object-contain" />)}</div>}<p className="mt-5 text-xs text-[#536765]">Pembahasan dan kunci hanya tersedia di ruang guru.</p></Card>}
    </div>
  </div>;
}


