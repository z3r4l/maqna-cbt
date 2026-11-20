# Decision register — business rules LOCKED

**Owner:** project owner. Codex may add proposals to `BLOCKERS.md` but must not silently override locked decisions.

| ID | Decision | Status |
|---|---|---|
| D-001 | Nama produk **maqna-cbt**; jangan gunakan identitas merek Testora pada UI. | LOCKED |
| D-002 | Target **sekolah/madrasah**; satu sekolah pada v1, `school_id` di domain entities untuk future multi-school. | LOCKED |
| D-003 | Student, teacher, admin login **username ATAU email + password**; email siswa optional, username wajib/unik case-insensitive. | LOCKED |
| D-004 | Gunakan **CodeIgniter Shield session auth**, HTTPS HttpOnly cookie + CSRF; React dan API same-origin (Vite proxy dev). | LOCKED TECH CHOICE |
| D-005 | Role: superadmin, school_admin, teacher, proctor, student. | LOCKED |
| D-006 | Guru hanya dapat publish ujian jika punya assignment aktif pada mapel + **semua** rombel target dan memiliki hak kelola ujian. Admin sekolah boleh publish di sekolahnya. | LOCKED |
| D-007 | Hasil **dirilis manual** oleh guru yang berwenang/Admin. Jangan sediakan bypass 'immediate results' di UI siswa, bahkan jika FRS lama menyebut mode lain. | LOCKED |
| D-008 | Sebelum rilis: siswa boleh melihat status selesai/menunggu nilai, **tanpa skor, pass/fail, kunci, atau pembahasan**. | LOCKED |
| D-009 | Jika ada essay belum dinilai atau attempt masih aktif: blok rilis keseluruhan batch ujian; tidak ada partial release pada v1. | DEFAULT ACCEPTED |
| D-010 | Tahun ajaran/semester/rombel/subject/teaching_assignment adalah acuan akses guru dan assignment siswa. Satu rombel utama per tahun ajaran. | DEFAULT ACCEPTED |
| D-011 | Deadline attempt = min(started_at + duration_minutes, exam.end_at); timestamp UTC, tampilan timezone sekolah. | DEFAULT ACCEPTED |
| D-012 | Siswa dapat resume attempt aktif; waktu tidak pause saat putus koneksi. Pengerjaan satu perangkat aktif per attempt; rekoneksi sah tidak membuat attempt baru. | DEFAULT ACCEPTED |
| D-013 | Randomisasi terjadi saat start attempt, urutan tetap selama resume; snapshot ujian pada publish. | DEFAULT ACCEPTED |
| D-014 | Default MCQ satu opsi benar; benar = bobot, salah/kosong = 0; passing grade per ujian; score 0–100. | DEFAULT ACCEPTED |
| D-015 | Multiple attempts: official exam default 1 attempt/first; practice can allow multiple/highest. Final result rule must be explicit at exam creation. | DEFAULT ACCEPTED |
| D-016 | Reset password siswa tanpa email via alur bantuan admin yang memaksa ganti password setelah login; never expose initial password in logs. | DEFAULT ACCEPTED |
| D-017 | S0–S7 enabled in `control.json` by default; owner can limit enabled stages/features. Do not infer feature disabling from stage priority alone. | LOCKED |

**Time zone:** school setting default Asia/Jakarta for UI, UTC database; backend must not assume timezone local server. Authentication / release / schedule events use absolute timestamps.

**Conflicts:** this register overrides older boilerplate (which contains a demo bearer-token auth) and prior FRS options (which allowed immediate result). Preserve auditability when migrating.
