# maqna-cbt — Paket rencana satu prompt Codex

Ini **paket instruksi implementasi**, bukan aplikasi S0–S7 yang sudah dikembangkan. Anda dapat menggunakan `maqna-cbt-plan-only.zip` untuk repo maqna-cbt yang sudah ada, atau `maqna-cbt-with-plan.zip` yang juga berisi boilerplate lama plus folder `plan/`.

1. Ekstrak dan buka root repo `maqna-cbt/`.
2. Edit `plan/control.json` untuk memilih stage dan fitur.
3. Buka Codex dalam mode eksekusi kode, bukan mode hanya perencanaan.
4. Jalankan sekali: `Implementasikan seluruh instruksi plan/ONE_SHOT_CODEX_PROMPT.md dari S0 sampai S7 dengan mematuhi plan/control.json. Mulai mengedit dan menguji kode sekarang, update plan/STATUS.md dan plan/PROGRESS.json setiap tahap; jangan berhenti hanya membuat rencana.`

Baca `plan/README.md` untuk mekanisme kontrol. Setelah eksekusi, status `DONE` hanya sah bila pengujian tercatat.
