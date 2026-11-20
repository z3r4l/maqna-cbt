import { trackLoading } from './loading';

export type Profile = { id: number; username: string; email: string | null; name: string; schoolId: number; roles: string[]; permissions: string[]; mustChangePassword: boolean };
type Envelope<T> = { data: T; meta?: { page?: number; perPage?: number; total?: number; counts?: Record<string, number>; serverNow?: string }; error?: { code: string; message: string } };
let csrf: { header: string; token: string } | null = null;
let mutationQueue: Promise<unknown> = Promise.resolve();

async function getCsrf() {
  if (csrf) return csrf;
  const response = await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' });
  if (!response.ok) throw new Error('Sambungan keamanan tidak tersedia.');
  const body = (await response.json()) as Envelope<{ header: string; token: string }>;
  csrf = body.data;
  return csrf;
}

async function sendEnvelope<T>(path: string, init: RequestInit = {}, csrfRetried = false): Promise<Envelope<T>> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body && !(init.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  const mutating = Boolean(init.method && init.method !== 'GET');
  if (mutating) {
    const token = await getCsrf();
    headers.set(token.header, token.token);
  }
  let response: Response;
  try {
    response = await fetch(`/api/v1${path}`, { ...init, credentials: 'same-origin', headers });
  } catch (reason) {
    if (mutating) csrf = null;
    throw reason;
  }
  const body = (await response.json().catch(() => null)) as Envelope<T> | null;
  if (mutating) csrf = null;
  if (!response.ok) {
    if (response.status === 403 && body?.error?.code === 'CSRF_INVALID' && !csrfRetried) {
      return sendEnvelope<T>(path, init, true);
    }
    throw new Error(body?.error?.message ?? `Permintaan gagal (${response.status}).`);
  }
  if (!body || !('data' in body)) throw new Error('Respons server tidak valid.');
  return body;
}

function requestEnvelope<T>(path: string, init: RequestInit = {}, silent = false): Promise<Envelope<T>> {
  let pending: Promise<Envelope<T>>;
  if (!init.method || init.method === 'GET') pending = sendEnvelope<T>(path, init);
  else {
    pending = mutationQueue.catch(() => undefined).then(() => sendEnvelope<T>(path, init));
    mutationQueue = pending;
  }
  if (silent || /^\/student\/attempts\/\d+\/(items\/\d+|heartbeat)$/.test(path)) return pending;
  return trackLoading(pending, !init.method || init.method === 'GET' ? 'Memuat data' : 'Memproses permintaan');
}

async function request<T>(path: string, init: RequestInit = {}, silent = false): Promise<T> {
  return (await requestEnvelope<T>(path, init, silent)).data;
}

export type Page<T> = { rows: T[]; page: number; total: number; perPage: number };
export type AttemptItem = { id: number; order: number; body: string; categoryName: string | null; type: 'mcq' | 'essay' | 'multi_mcq' | 'true_false' | 'matching'; options: { id: number; body: string; matchBody?: string | null }[]; choices: { id: number; body: string }[]; assets: { id: number; original_name: string }[]; selectedOptionId: number | null; essayText: string | null; answer: number[] | Record<string, boolean | number> | null; marked: boolean; revision: number };
export type Attempt = { id: number; examId: number; examTitle: string; subjectName: string; packageCode: string; status: string; startedAt: string; deadlineAt: string; submittedAt: string | null; serverNow: string; revision: number; items: AttemptItem[] };
export type QuestionVersion = { id: number; question_id: number; version_no: number; status: string; current_body?: string; review_note?: string | null; snapshot: { body: string; type: AttemptItem['type']; rubric: string; explanation: string; weight: number; matchingChoices?: { body: string }[]; options: { body: string; isCorrect: boolean; matchBody?: string | null; matchChoiceIndex?: number | null }[] } };
async function paged<T>(path: string): Promise<Page<T>> {
  const body = await requestEnvelope<T[]>(path);
  return { rows: body.data, page: body.meta?.page ?? 1, perPage: body.meta?.perPage ?? 20, total: body.meta?.total ?? body.data.length };
}

export const api = {
  me: () => request<Profile>('/auth/me'),
  updateProfile: (displayName: string) => request<Profile>('/auth/profile', { method: 'PATCH', body: JSON.stringify({ displayName }) }),
  login: (identity: string, password: string) => request<Profile>('/auth/login', { method: 'POST', body: JSON.stringify({ identity, password }) }),
  logout: () => request<{ loggedOut: boolean }>('/auth/logout', { method: 'POST' }),
  changePassword: (currentPassword: string, newPassword: string) => request<{ changed: boolean }>('/auth/password/change', { method: 'POST', body: JSON.stringify({ currentPassword, newPassword }) }),
  resetPassword: (token: string, newPassword: string) => request<{ changed: boolean }>('/auth/password/reset', { method: 'POST', body: JSON.stringify({ token, newPassword }) }),
  adminReset: (username: string) => request<{ temporaryPassword: string; mustChangePassword: boolean }>('/auth/password/admin-reset', { method: 'POST', body: JSON.stringify({ username }) }),
  issueResetToken: (username: string) => request<{ token: string; expiresInSeconds: number }>('/auth/password/issue-token', { method: 'POST', body: JSON.stringify({ username }) }),
  school: () => request<Record<string, unknown>>('/school'),
  updateSchool: (value: { name?: string; timezone?: string }) => request<Record<string, unknown>>('/school', { method: 'PUT', body: JSON.stringify(value) }),
  uploadSchoolLogo: (file: File) => { const form = new FormData(); form.append('logo', file); return request<{ updatedAt: string }>('/school/logo', { method: 'POST', body: form }); },
  sessionPolicies: () => request<{ role: string; maxSessions: number }[]>('/school/session-policies'),
  saveSessionPolicy: (role: string, maxSessions: number) => request<{ role: string; maxSessions: number }>('/school/session-policies/' + role, { method: 'PUT', body: JSON.stringify({ maxSessions }) }),
  userPermissions: (userId: number) => request<{ permission: string; allowed: boolean }[]>('/users/' + userId + '/permissions'),
  saveUserPermission: (userId: number, permission: string, allowed: boolean) => request<{ permission: string; allowed: boolean }>('/users/' + userId + '/permissions', { method: 'PUT', body: JSON.stringify({ permission, allowed }) }),
  catalog: (resource: string, page = 1, search = '') => paged<Record<string, unknown>>('/' + resource + '?page=' + page + '&perPage=100&search=' + encodeURIComponent(search)),
  createCatalog: (resource: string, value: Record<string, unknown>) => request<Record<string, unknown>>('/' + resource, { method: 'POST', body: JSON.stringify(value) }),
  updateCatalog: (resource: string, id: number, value: Record<string, unknown>) => request<Record<string, unknown>>('/' + resource + '/' + id, { method: 'PATCH', body: JSON.stringify(value) }),
  people: (kind: 'students' | 'teachers', page = 1, search = '', perPage = 20, loginStatus = 'all') => paged<Record<string, unknown>>('/' + kind + '?page=' + page + '&perPage=' + perPage + '&search=' + encodeURIComponent(search) + '&loginStatus=' + encodeURIComponent(loginStatus)),
  createPerson: (kind: 'students' | 'teachers', value: Record<string, unknown>) => request<{ person: Record<string, unknown>; temporaryPassword: string }>('/' + kind, { method: 'POST', body: JSON.stringify(value) }),
  updatePerson: (kind: 'students' | 'teachers', id: number, value: Record<string, unknown>) => request<Record<string, unknown>>('/' + kind + '/' + id, { method: 'PATCH', body: JSON.stringify(value) }),
  teachingAssignments: (page = 1) => paged<Record<string, unknown>>('/teaching-assignments?page=' + page),
  createTeachingAssignment: (value: Record<string, unknown>) => request<Record<string, unknown>>('/teaching-assignments', { method: 'POST', body: JSON.stringify(value) }),
  setTeachingAssignmentActive: (id: number, active: boolean) => request<Record<string, unknown>>('/teaching-assignments/' + id, { method: 'PATCH', body: JSON.stringify({ active }) }),
  memberships: (studentId: number) => paged<Record<string, unknown>>('/student-memberships?studentId=' + studentId + '&perPage=100'),
  moveStudent: (studentId: number, classGroupId: number) => request<Record<string, unknown>>('/student-memberships', { method: 'POST', body: JSON.stringify({ studentId, classGroupId }) }),
  myTeaching: () => paged<Record<string, unknown>>('/teacher/assignments?perPage=100'),
  myClass: () => request<Record<string, unknown> | null>('/student/class'),
  questionMasters: () => request<{ subjects: Record<string, unknown>[]; categories: Record<string, unknown>[]; topics: Record<string, unknown>[] }>('/question-masters'),
  questionBankMasters: () => request<{ subjects: Record<string, unknown>[]; classes: Record<string, unknown>[]; examTypes: Record<string, unknown>[] }>('/question-bank-masters'),
  questionBanks: (page = 1, search = '', subjectId = '', classGroupId = '', perPage = 20) => paged<Record<string, unknown>>('/question-banks?page=' + page + '&perPage=' + perPage + '&search=' + encodeURIComponent(search) + (subjectId ? '&subjectId=' + encodeURIComponent(subjectId) : '') + (classGroupId ? '&classGroupId=' + encodeURIComponent(classGroupId) : '')),
  questionBank: (id: number) => request<Record<string, unknown>>('/question-banks/' + id),
  createQuestionBank: (value: Record<string, unknown>) => request<Record<string, unknown>>('/question-banks', { method: 'POST', body: JSON.stringify(value) }),
  updateQuestionBank: (id: number, value: Record<string, unknown>) => request<Record<string, unknown>>('/question-banks/' + id, { method: 'PATCH', body: JSON.stringify(value) }),
  archiveQuestionBank: (id: number) => request<{ archived: boolean }>('/question-banks/' + id, { method: 'DELETE' }),
  questions: (page = 1, search = '', subjectId = '', perPage = 20, bankId = '') => paged<Record<string, unknown>>('/questions?page=' + page + '&perPage=' + perPage + '&search=' + encodeURIComponent(search) + (subjectId ? '&subjectId=' + encodeURIComponent(subjectId) : '') + (bankId ? '&bankId=' + encodeURIComponent(bankId) : '')),
  previewQuestionJson: (value: unknown) => request<{ id: number; ready: number; skipped: number; rows: { rowNumber: number; subjectCode: string; type: string; body: string; status: string }[] }>('/questions/import/json/preview', { method: 'POST', body: JSON.stringify(value) }),
  commitQuestionJson: (id: number) => request<{ id: number; status: string; created: number }>('/questions/import/json/' + id + '/commit', { method: 'POST' }),
  previewQuestionKeys: (file: File) => { const form = new FormData(); form.append('file', file); return request<{ id: number; count: number; rows: { rowNumber: number; questionId: number; type: string; currentKey: string; newKey: string }[] }>('/questions/import/keys/preview', { method: 'POST', body: form }); },
  commitQuestionKeys: (id: number) => request<{ id: number; status: string; updated: number }>('/questions/import/keys/' + id + '/commit', { method: 'POST' }),
  previewQuestionPackage: (kind: 'zip' | 'docx', file: File, mode = 'append') => { const form = new FormData(); form.append('file', file); form.append('mode', mode); return request<{ id: number; count: number; rows: { rowNumber: number; subjectCode: string; type: string; body: string; status: string }[] }>('/questions/import/' + kind + '/preview', { method: 'POST', body: form }); },
  commitQuestionPackage: (kind: 'zip' | 'docx', id: number) => request<{ id: number; status: string; created: number; replaced: number; skipped: number }>('/questions/import/' + kind + '/' + id + '/commit', { method: 'POST' }),
  question: (id: number) => request<Record<string, unknown>>('/questions/' + id),
  createQuestion: (value: Record<string, unknown>) => request<Record<string, unknown>>('/questions', { method: 'POST', body: JSON.stringify(value) }),
  updateQuestion: (id: number, value: Record<string, unknown>) => request<Record<string, unknown>>('/questions/' + id, { method: 'PATCH', body: JSON.stringify(value) }),
  archiveQuestion: (id: number) => request<{ archived: boolean }>('/questions/' + id, { method: 'DELETE' }),
  uploadQuestionAsset: (id: number, file: File) => { const form = new FormData(); form.append('file', file); return request<Record<string, unknown>>('/questions/' + id + '/assets', { method: 'POST', body: form }); },
  uploadQuestionMedia: (file: File) => { const form = new FormData(); form.append('file', file); return request<{ token: string; src: string }>('/questions/media', { method: 'POST', body: form }); },
  questionVersions: (questionId?: number) => request<QuestionVersion[]>('/question-versions' + (questionId ? '?questionId=' + questionId : '')),
  questionVersion: (id: number) => request<QuestionVersion>('/question-versions/' + id),
  createQuestionVersion: (id: number) => request<QuestionVersion>('/questions/' + id + '/revisions', { method: 'POST' }),
  editQuestionVersion: (id: number, value: QuestionVersion['snapshot']) => request<QuestionVersion>('/question-versions/' + id, { method: 'PATCH', body: JSON.stringify(value) }),
  submitQuestionVersion: (id: number) => request<QuestionVersion>('/question-versions/' + id + '/submit', { method: 'POST' }),
  decideQuestionVersion: (id: number, approve: boolean, note: string) => request<QuestionVersion>('/question-versions/' + id + '/decision', { method: 'POST', body: JSON.stringify({ approve, note }) }),
  archiveQuestionVersion: (id: number) => request<QuestionVersion>('/question-versions/' + id + '/archive', { method: 'POST' }),
  examMasters: () => request<{ subjects: Record<string, unknown>[]; semesters: Record<string, unknown>[]; classes: Record<string, unknown>[]; examTypes: Record<string, unknown>[] }>('/exam-masters'),
  examTokens: (page = 1, status = 'all', subjectId = '', classGroupId = '') => paged<Record<string, unknown>>('/dashboard/exam-tokens?page=' + page + '&status=' + encodeURIComponent(status) + '&subjectId=' + encodeURIComponent(subjectId) + '&classGroupId=' + encodeURIComponent(classGroupId)),
  previewBulkExamStatus: (ids: number[], target: 'paused' | 'published') => request<{ id: number; target: string; rows: Record<string, unknown>[]; blocked: { id: number; reason: string }[] }>('/exams/bulk-status/preview', { method: 'POST', body: JSON.stringify({ ids, target }) }),
  commitBulkExamStatus: (id: number, target: 'paused' | 'published') => request<{ updated: number; target: string }>('/exams/bulk-status/previews/' + id + '/commit', { method: 'POST', body: JSON.stringify({ target, confirmed: true }) }),
  examMinutes: (id: number) => request<Record<string, unknown> | null>('/exams/' + id + '/minutes'),
  saveExamMinutes: (id: number, notes: string, signerOne: string, signerTwo: string) => request<Record<string, unknown>>('/exams/' + id + '/minutes', { method: 'PUT', body: JSON.stringify({ notes, signerOne, signerTwo }) }),
  rotateExamToken: (id: number) => request<{ accessCode: string }>('/exams/' + id + '/access-code/rotate', { method: 'POST', body: JSON.stringify({ confirmed: true }) }),
  currentExamToken: (id: number) => request<{ accessCode: string; expiresAt: string; serverNow: string }>('/exams/' + id + '/access-code/current'),
  setExamTokenPolicy: (id: number, mode: 'none' | 'static' | 'dynamic') => request<{ mode: string; accessCode?: string; expiresAt?: string; serverNow?: string }>('/exams/' + id + '/access-code/policy', { method: 'PATCH', body: JSON.stringify({ mode, confirmed: true }) }),
  exams: async (page = 1, search = '', status = 'all', subjectId = '', classGroupId = '', perPage = 10) => {
    const query = '/exams?page=' + page + '&perPage=' + perPage + '&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status)
      + (subjectId ? '&subjectId=' + encodeURIComponent(subjectId) : '') + (classGroupId ? '&classGroupId=' + encodeURIComponent(classGroupId) : '');
    const body = await requestEnvelope<Record<string, unknown>[]>(query);
    return { rows: body.data, page: body.meta?.page ?? 1, perPage: body.meta?.perPage ?? perPage, total: body.meta?.total ?? body.data.length, counts: body.meta?.counts ?? {}, serverNow: body.meta?.serverNow ?? '' };
  },
  createExamFromBank: (value: Record<string, unknown>) => request<Record<string, unknown>>('/exams/from-bank', { method: 'POST', body: JSON.stringify(value) }),
  updateExamFromBank: (id: number, value: Record<string, unknown>) => request<Record<string, unknown>>('/exams/' + id + '/from-bank', { method: 'PATCH', body: JSON.stringify(value) }),
  updateExamSchedule: (id: number, value: { startAt: string; endAt: string; durationMinutes: number }) => request<Record<string, unknown>>('/exams/' + id + '/schedule', { method: 'PATCH', body: JSON.stringify(value) }),
  archiveExam: (id: number) => request<{ archived: boolean }>('/exams/' + id, { method: 'DELETE' }),
  exam: (id: number) => request<Record<string, unknown>>('/exams/' + id),
  createExam: (value: Record<string, unknown>) => request<Record<string, unknown>>('/exams', { method: 'POST', body: JSON.stringify(value) }),
  updateExam: (id: number, value: Record<string, unknown>) => request<Record<string, unknown>>('/exams/' + id, { method: 'PATCH', body: JSON.stringify(value) }),
  examRoster: (id: number) => request<Record<string, unknown>[]>('/exams/' + id + '/roster'),
  examQuestionPreview: (id: number) => request<{ title: string; eligibleQuestionCount: number; displayQuestionCount: number; shuffleQuestions: boolean; shuffleOptions: boolean; items: { id: number; order: number; body: string; type: AttemptItem['type']; options: { id: number; body: string }[]; choices: { id: number; body: string }[]; assets: { id: number; original_name: string }[] }[] }>('/exams/' + id + '/preview'),
  examAssignments: (id: number) => request<Record<string, unknown>[]>('/exams/' + id + '/assignments'),
  assignExamParticipant: (examId: number, studentId: number, examRoomId: number | null, participantNumber: string | null) => request<Record<string, unknown>>('/exams/' + examId + '/assignments/' + studentId, { method: 'PATCH', body: JSON.stringify({ examRoomId, participantNumber }) }),
  studentSessions: (studentId: number) => request<Record<string, unknown>[]>('/students/' + studentId + '/sessions'),
  revokeStudentSession: (studentId: number, sessionId: number, reason: string) => request<{ revoked: boolean }>('/students/' + studentId + '/sessions/' + sessionId + '/revoke', { method: 'POST', body: JSON.stringify({ confirmed: true, reason }) }),
  previewBulkPeople: (kind: 'students' | 'teachers', ids: number[]) => request<{ id: number; count: number; rows: { id: number; name: string }[]; blocked: { id: number; reason: string }[] }>('/people/bulk/' + kind + '/preview', { method: 'POST', body: JSON.stringify({ ids }) }),
  commitBulkPeople: (kind: 'students' | 'teachers', previewId: number, reason: string, count: number) => request<{ deactivated: number }>('/people/bulk/' + kind + '/' + previewId + '/commit', { method: 'POST', body: JSON.stringify({ reason, confirmed: true, confirmationText: 'NONAKTIFKAN ' + count }) }),
  publishExam: (id: number) => request<Record<string, unknown>>('/exams/' + id + '/publish', { method: 'POST' }),
  studentExams: () => request<Record<string, unknown>[]>('/student/exams'),
  startAttempt: async (examId: number, accessCode = '') => {
    const key = 'maqna-device-exam-' + examId;
    const token = localStorage.getItem(key) ?? crypto.randomUUID();
    localStorage.setItem(key, token);
    const attempt = await request<Attempt>('/student/exams/' + examId + '/start', { method: 'POST', headers: { 'X-Attempt-Device': token }, body: JSON.stringify({ accessCode }) });
    localStorage.setItem('maqna-device-attempt-' + attempt.id, token);
    return attempt;
  },
  attempt: (id: number) => request<Attempt>('/student/attempts/' + id, { headers: { 'X-Attempt-Device': localStorage.getItem('maqna-device-attempt-' + id) ?? '' } }),
  saveAttemptAnswer: (attemptId: number, itemId: number, value: { expectedRevision: number; selectedOptionId: number | null; essayText?: string | null; answer?: number[] | Record<string, boolean | number> | null; marked: boolean; mutationId: string }) =>
    request<{ itemId: number; revision: number; attemptRevision: number; selectedOptionId: number | null; marked: boolean; savedAt: string }>('/student/attempts/' + attemptId + '/items/' + itemId,
      { method: 'PATCH', headers: { 'X-Attempt-Device': localStorage.getItem('maqna-device-attempt-' + attemptId) ?? '' }, body: JSON.stringify(value) }),
  submitAttempt: (id: number) => request<{ id: number; status: string; submittedAt: string; unansweredCount: number }>('/student/attempts/' + id + '/submit',
    { method: 'POST', headers: { 'X-Attempt-Device': localStorage.getItem('maqna-device-attempt-' + id) ?? '' } }),
  heartbeat: (id: number, event?: string) => request<{ serverNow: string; deadlineAt: string; eventRecorded: boolean }>('/student/attempts/' + id + '/heartbeat',
    { method: 'POST', headers: { 'X-Attempt-Device': localStorage.getItem('maqna-device-attempt-' + id) ?? '' }, body: JSON.stringify(event ? { event } : {}) }),
  proctor: (id: number, silent = false, loginStatus = 'all') => request<{ serverNow: string; rows: Record<string, unknown>[] }>('/exams/' + id + '/proctor?loginStatus=' + encodeURIComponent(loginStatus), {}, silent),
  proctorExams: () => request<Record<string, unknown>[]>('/proctor/exams'),
  proctors: () => request<Record<string, unknown>[]>('/proctors'),
  createProctor: (value: { username: string; email: string; displayName: string }) => request<{ userId: number; temporaryPassword: string }>('/proctors', { method: 'POST', body: JSON.stringify(value) }),
  assignProctor: (examId: number, userId: number) => request<{ assigned: boolean }>('/exams/' + examId + '/proctors', { method: 'POST', body: JSON.stringify({ userId }) }),
  proctorRevokeSession: (examId: number, studentId: number, sessionId: number, reason: string) => request<{ revoked: boolean }>('/exams/' + examId + '/proctor/students/' + studentId + '/sessions/' + sessionId + '/revoke', { method: 'POST', body: JSON.stringify({ confirmed: true, reason }) }),
  questionAnalytics: (id: number) => request<{ sampleSize: number; minSample: number; suppressed: boolean; items: { itemId: number; order: number; body: string; sampleSize: number; difficulty: number; discrimination: number; unanswered: number; distractors: { optionId: number; body: string; count: number; correct: boolean }[] }[] }>('/exams/' + id + '/question-analytics'),
  answerMatrix: (id: number) => request<{ examId: number; title: string; questions: { id: number; order: number }[]; rows: { studentId: number; name: string; className: string; attemptId: number; score: number; answers: string[] }[] }>('/exams/' + id + '/answer-matrix'),
  resultHistory: () => request<Record<string, unknown>[]>('/student/results'),
  studentResult: (id: number) => request<Record<string, unknown>>('/student/results/' + id),
  answerReview: (id: number) => request<Record<string, unknown>[]>('/student/results/' + id + '/review'),
  reports: (filters: Record<string, string> = {}) => paged<Record<string, unknown>>('/reports/exams?' + new URLSearchParams(Object.entries(filters).filter(([, value]) => value)).toString()),
  report: (id: number, classGroupId = '') => request<Record<string, unknown>>('/reports/exams/' + id + (classGroupId ? '?classGroupId=' + encodeURIComponent(classGroupId) : '')),
  releaseResults: (id: number) => request<Record<string, unknown>>('/exams/' + id + '/release', { method: 'POST' }),
  essays: (id: number) => request<Record<string, unknown>[]>('/exams/' + id + '/essays'),
  scoreEssay: (examId: number, itemId: number, points: number, feedback: string) => request<Record<string, unknown>>('/exams/' + examId + '/essays/' + itemId, { method: 'PATCH', body: JSON.stringify({ points, feedback }) }),
  auditEvents: (page = 1) => paged<Record<string, unknown>>('/audit-events?page=' + page),
  previewImport: (kind: 'students' | 'teachers' | 'subjects' | 'questions', mode: string, file: File) => { const form = new FormData(); form.append('mode', mode); form.append('file', file); return request<{ id: number; kind: string; mode: string; counts: Record<string, number>; rows: Record<string, unknown>[] }>('/imports/' + kind + '/preview', { method: 'POST', body: form }); },
  commitImport: (kind: 'students' | 'teachers' | 'subjects' | 'questions', id: number) => request<{ id: number; status: string; created: number; skipped: number; credentials: { rowNumber: number; username: string; temporaryPassword: string }[] }>('/imports/' + kind + '/' + id + '/commit', { method: 'POST' }),
};
