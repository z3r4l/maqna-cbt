# Blockers dan batas verifikasi

| Waktu WIB | Item | Status | Bukti dan tindakan aman |
|---|---|---|---|
| 2026-11-15 13:26 | Baseline XLSX smoke | RESOLVED | Kegagalan awal 503 akibat PHP ZIP tidak aktif. Fallback PharData untuk impor/ekspor XLSX diimplementasikan; `run_all_smokes.py` 14/14 dan `run_s8_smokes.py` 12/12 lulus. |
| 2026-11-15 13:26 | Git diff/branch | UNAVAILABLE | Workspace bukan repository Git (`git status` exit 1), sehingga verifikasi diff/branch tidak mungkin. Audit file dilakukan langsung; jangan membuat repo baru atau menimpa perubahan yang ada. |

