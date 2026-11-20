import React, { Suspense, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import Layout from '@/components/Layout';
import LoadingOverlay from '@/components/LoadingOverlay';
import Dashboard from '@/pages/Dashboard';
import Login from '@/pages/Login';
import ProfilePage from '@/pages/Profile';
import ResetPassword from '@/pages/ResetPassword';
import AdminReset from '@/pages/AdminReset';
import AcademicAdmin from '@/pages/AcademicAdmin';
import PeopleAdmin from '@/pages/PeopleAdmin';
import StudentAdmin from '@/pages/StudentAdmin';
import { TeachingAdmin, MembershipsAdmin } from '@/pages/AssignmentsAdmin';
import SchoolSettings from '@/pages/SchoolSettings';
import { QuestionEditor, QuestionList } from '@/pages/Questions';
import { QuestionBankList, QuestionBankManage } from '@/pages/QuestionBanks';
import { ExamEditor, StudentExams } from '@/pages/Exams';
import { ExamList } from '@/pages/ExamSchedules';
import AttemptRunner from '@/pages/AttemptRunner';
import { AnswerReview, EssayReview, ReportDetail, ReportList, StudentResultDetail, StudentResults } from '@/pages/Results';
import AuditEvents from '@/pages/AuditEvents';
import ImportPage from '@/pages/ImportPage';
import { QuestionVersions, QuestionVersionEditor } from '@/pages/QuestionVersions';
import { ProctorDashboard, QuestionAnalytics } from '@/pages/Proctoring';
import ProctorAdmin from '@/pages/ProctorAdmin';
import { HelpPage, UpdatesPage, AboutPage } from '@/pages/InfoPages';
import { api } from '@/lib/api';
import './index.css';

function Guard({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = useState<'loading' | 'yes' | 'no'>('loading');
  const [mustChange, setMustChange] = useState(false);
  const location = useLocation();
  useEffect(() => { setStatus('loading'); api.me().then(profile => { setMustChange(profile.mustChangePassword); setStatus('yes'); }).catch(() => setStatus('no')); }, [location.pathname]);
  if (status === 'loading') return null;
  if (status === 'yes' && mustChange && location.pathname !== '/profile') return <Navigate to="/profile" replace />;
  return status === 'yes' ? <>{children}</> : <Navigate to="/login" replace />;
}

function App() {
  return <BrowserRouter><LoadingOverlay /><Routes>
    <Route path="/login" element={<Login />} />
    <Route path="/password/reset" element={<ResetPassword />} />
    <Route path="/attempts/:id" element={<Guard><AttemptRunner /></Guard>} />
    <Route element={<Guard><Layout /></Guard>}>
      <Route path="/dashboard" element={<Dashboard />} />
      <Route path="/profile" element={<ProfilePage />} />
      <Route path="/help" element={<HelpPage />} />
      <Route path="/updates" element={<UpdatesPage />} />
      <Route path="/about" element={<AboutPage />} />
      <Route path="/password/admin" element={<AdminReset />} />
      <Route path="/school" element={<SchoolSettings />} />
      <Route path="/academic/:resource" element={<AcademicAdmin />} />
      <Route path="/people/students" element={<StudentAdmin />} />
      <Route path="/people/teachers" element={<PeopleAdmin kind="teachers" />} />
      <Route path="/teaching" element={<TeachingAdmin />} />
      <Route path="/memberships" element={<MembershipsAdmin />} />
      <Route path="/questions" element={<QuestionBankList />} />
      <Route path="/questions/tools" element={<QuestionList />} />
      <Route path="/questions/:id" element={<Suspense fallback={<main className="p-8 text-sm">Memuat editor soal…</main>}><QuestionEditor /></Suspense>} />
      <Route path="/question-banks/:id" element={<QuestionBankManage />} />
      <Route path="/question-versions" element={<QuestionVersions />} />
      <Route path="/question-versions/:id" element={<QuestionVersionEditor />} />
      <Route path="/exams" element={<ExamList />} />
      <Route path="/exams/:id" element={<ExamEditor />} />
      <Route path="/my-exams" element={<StudentExams />} />
      <Route path="/my-results" element={<StudentResults />} />
      <Route path="/my-results/:id" element={<StudentResultDetail />} />
      <Route path="/my-results/:id/review" element={<AnswerReview />} />
      <Route path="/reports" element={<ReportList />} />
      <Route path="/monitoring" element={<ProctorDashboard />} />
      <Route path="/proctors" element={<ProctorAdmin />} />
      <Route path="/analytics/questions" element={<QuestionAnalytics />} />
      <Route path="/reports/:id" element={<ReportDetail />} />
      <Route path="/reports/:id/essays" element={<EssayReview />} />
      <Route path="/audit" element={<AuditEvents />} />
      <Route path="/imports/students" element={<ImportPage kind="students" />} />
      <Route path="/imports/teachers" element={<ImportPage kind="teachers" />} />
      <Route path="/imports/subjects" element={<ImportPage kind="subjects" />} />
      <Route path="/imports/questions" element={<ImportPage kind="questions" />} />
    </Route>
    <Route path="*" element={<Navigate to="/dashboard" replace />} />
  </Routes></BrowserRouter>;
}

createRoot(document.getElementById('root')!).render(<React.StrictMode><App /></React.StrictMode>);
if ('serviceWorker' in navigator && import.meta.env.PROD) window.addEventListener('load', () => { void navigator.serviceWorker.register('/sw.js'); });
