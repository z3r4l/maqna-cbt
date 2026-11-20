# Cara pasang (tidak overwrite plan lama)

Ekstrak **maqna-cbt-ryuexam-extension.zip** di root repo maqna-cbt. Struktur: `plan/ONE_SHOT_RYUEXAM_EXTENSION_PROMPT.md` + folder `plan/extensions/ryuexam/`.

```bash
# opsi opsional validasi isi paket setelah ekstrak
python3 plan/extensions/ryuexam/scripts/validate_extension.py
```

Periksa `plan/extensions/ryuexam/CONTROL.json`, lalu prompt Codex sekali:

> Jalankan `plan/ONE_SHOT_RYUEXAM_EXTENSION_PROMPT.md` dari root repository. Audit S0–S7 existing dahulu; terapkan hanya gap yang aktif melalui S8–S11. Jangan reset data atau status lama.

Paket ini tidak menyertakan source code aplikasi karena repository maqna-cbt versi terakhir belum diberikan. Pekerjaan aktual dilakukan Codex di workspace milik pengguna.
