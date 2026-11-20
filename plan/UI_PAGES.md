# Frontend routes / shadcn UI / responsive behavior

Keep app identity **maqna-cbt** everywhere; no old brand in UI. Use shadcn/ui `Button`, `Input`, `Label`, `Card`, `Badge`, `Table`, `Dialog`, `AlertDialog`, `Select`, `Sheet`, `Tabs`, `Skeleton`, `Progress`, `DropdownMenu`, `Sonner/Toast`, `Form` where needed. Color tokens as `references/design/MAQNA_CBT_DESIGN_SYSTEM.md`.

| Route | Page | Users | Stage |
|---|---|---|---|
| `/login`, `/forgot-password`, `/reset-password` | Auth screens | all/guest | S0 |
| `/dashboard`, `/account` | Role-specific dashboard and profile | all | S0–S1 |
| `/academic/years`, `/academic/semesters`, `/academic/classes` | Academic masters | admin | S1 |
| `/academic/subjects`, `/teachers`, `/students`, `/teaching-assignments` | School data | admin | S1 |
| `/questions`, `/questions/new`, `/questions/:id/edit` | Question bank/editor | teacher/admin scoped | S2 |
| `/exams`, `/exams/new`, `/exams/:id/edit`, `/exams/:id/participants` | Exam wizard and roster | teacher/admin scoped | S3 |
| `/student/exams`, `/student/exams/:id` | Student exam list and instructions | student | S3 |
| `/student/attempts/:id` | Exam runner | student | S4 |
| `/results`, `/results/exams/:id`, `/student/history` | Grading, reports, manual release, history | scoped teacher/admin/student | S5 |
| `/imports`, `/essays`, `/audit-logs`, `/exports` | Enhancement screens | authorized roles | S6 |
| `/monitoring`, `/question-reviews`, `/packages`, `/analytics/questions` | Advanced | authorized roles | S7 |

## Desktop and mobile rules
- App shell: fixed/collapsible left sidebar desktop ≥1024, topbar and drawer navigation on smaller widths.
- Exam runner: top timer and progress; wide content column for question and options; right question palette on desktop; `Sheet`/Drawer for question palette on mobile. Main action buttons reachable without horizontal scrolling at 320px.
- Preserve focus when modals open, dialog accessible, explicit labels and visible errors, high contrast for answer states (not color-only).
- Avoid heavy charts/tables without responsive mode; responsive tables horizontally scroll inside local area or become row cards.
- Loading skeleton, zero state, network error state and role-specific 401/403 not-found pages must be considered.
- Alert before unsaved work; do not disable route on mobile, use stable timer and retry indicator.
- Admin dashboards: stats for active students, upcoming exams, exam completion, grades; charts are optional visualizations, data must be real/safely computed.

## Shared UI patterns
- `DataTable` with sorting/filtering/pagination and server cursor/offset according to API.
- Confirmation dialogs for exam publish, submit, grade release, reset attempt, deactivate user.
- Forms with client validation mirroring backend, but backend is source of truth.
- Each stage must include at least one mobile view test (320px/390px) and a desktop test (≥1280px), and keyboard-flow smoke check.
