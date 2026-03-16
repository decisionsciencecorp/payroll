# Payroll — PRD / Implementation Plan Gap Analysis

**Sources:** [docs/PRD.md](PRD.md) (authoritative), [.cursor/plans/Payroll PRD Implementation Plan-efe4272b.plan.md](/root/.cursor/plans/Payroll%20PRD%20Implementation%20Plan-efe4272b.plan.md).

---

## Implemented and aligned

- **Project layout:** `public/` docroot, `public/includes/` (config, database, functions, auth, csrf), `db/`, `logs/`, no `.htaccess`.
- **Database:** All tables per plan (api_keys, api_rate_limits, employees with W-2 address + i9_completed_at, payroll_history, tax_config, company_settings with employer fields, admin_users). Head of Household in filing_status.
- **Admin UI:** login, dashboard (counts), api-keys, change-password, **users** (add/list/delete, last-admin protected), employees, payroll, tax-config, logo, **company-settings** (employer name/EIN/address), w2.
- **Auth:** Session login, CSRF, session regeneration (non-CLI), first-login redirect to change password, addAdminUser / getAllAdminUsers / deleteAdminUser.
- **APIs:** Tax (upload/list/get/delete), employee CRUD (SSN masked in list), run-payroll (optional employee_ids), list/get payroll, upload-logo, pdf-stub (PDF), generate-w2. Error codes 400/401/404/405/409/429.
- **Company settings:** EIN validated (9 digits); employer name/EIN required for W-2; employee address required for inclusion in W-2 (skip if missing).
- **Docs:** API-QUICK-REFERENCE including generate-w2; CONFIGURATION, ADMIN, TESTING, etc.

---

## Gaps / missed requirements

### 1. W-2 output format (PRD and Plan)

| Source | Requirement |
|--------|-------------|
| PRD §5.2 | `generate-w2.php` — "returns **ZIP of PDFs** or **single PDF**" |
| Plan §10 | "Returns: **ZIP of PDF W-2s** (one per employee) or a **single PDF** with all W-2s (e.g. 4-up). Response headers: **Content-Type application/pdf or application/zip**" |

**Current:** Both the API (`generate-w2.php`) and admin (`w2.php`) return **HTML** (`Content-Type: text/html`, `filename="W2-YYYY.html"`) with a note to print/save as PDF. No server-generated PDF and no ZIP.

**Needed to match plan:** Generate W-2s using the same PDF library as pay stubs (e.g. TCPDF), then either:
- Return a **ZIP** of one PDF per employee, or  
- Return a **single PDF** with one W-2 per page (or 4-up).  
Apply the same behavior to the admin W-2 page (download ZIP or single PDF).

---

### 2. I-9 “within 3 business days” (PRD §4.1 / Plan §2.3)

- PRD: "I-9 completed at | Date (**required for compliance; within 3 business days of hire**)".
- Plan: "i9_completed_at DATE NULL (**compliance; within 3 business days of hire**)."

**Current:** We store and accept `i9_completed_at`; no validation or warning that it should be within 3 business days of hire.

**Possible additions:**  
- On create/update employee: validate (or warn) that `i9_completed_at` is within 3 business days of `hire_date`.  
- Optional: admin list or report of employees with missing or out-of-window I-9 date.

---

### 3. API key in URL (Plan §9.2 / security)

- Plan §9.2: "**No API key in URL** for PDF (or require API key for consistency … keep API key required)."
- Security audit: Prefer header-only; keys in query can end up in logs/referrers.

**Current:** `getApiKey()` still accepts `api_key` in query and POST body. Docs say to prefer `X-API-Key`; query is not disabled.

**Options:**  
- For pdf-stub (and optionally generate-w2): accept **only** `X-API-Key` (reject `api_key` in query/body), or  
- Keep current behavior but document and optionally log when key is passed via query.

---

### 4. W-4 upload and I-9 generation / review (compliance)

**Current:**  
- **W-4:** We store only **W-4 data** on the employee row (filing_status, step4a/4b/4c). There is **no upload** of the actual W-4 form (signed PDF/image) and **no place to review** stored W-4 documents.  
- **I-9:** We store only **I-9 completed date** (`i9_completed_at`). There is **no I-9 form generation**, **no upload/storage** of completed I-9 documents, and **no place to review** stored I-9s.

**Needed:**

| Capability | Description |
|------------|-------------|
| **Upload W-4** | Per employee: upload and store the signed W-4 (PDF or image). One current W-4 per employee (replace on re-upload), plus optional version history if desired. |
| **Generate I-9** | Ability to generate a fillable/printable I-9 form (e.g. PDF) pre-filled with employee name, SSN, hire date, etc., for completion and signature. |
| **Store I-9** | After completion: upload and store the signed/completed I-9 (PDF or image) per employee. |
| **Review W-4s and I-9s** | Admin UI to: (1) list employees with “has W-4” / “has I-9” / “I-9 date” status, (2) view or download stored W-4 and I-9 documents, (3) from the employee form or a dedicated compliance page, upload W-4, generate I-9, upload completed I-9. |

**Implementation outline:**

- **Storage:** Store files under **`public/uploads/`** to conform to host requirements ([vinny-website GITHUB_REPO_SETUP_INSTRUCTIONS](https://github.com/actuallyrizzn/vinny-website/blob/main/docs/GITHUB_REPO_SETUP_INSTRUCTIONS.md)): user uploads must live at `public/uploads/` so they persist across deployments. Use subpaths e.g. `public/uploads/employees/{employee_id}/w4.pdf`, `.../i9.pdf`. **Do not** expose direct URLs: serve files only via authenticated admin/API scripts (same pattern as logo-file.php) so uploads are not directly browsable.  
- **Schema:** Option A — add `w4_file_path` and `i9_file_path` (and optionally `w4_uploaded_at`, `i9_uploaded_at`) to `employees`. Option B — new table `employee_documents (id, employee_id, document_type ENUM('w4','i9'), file_path, uploaded_at)` to support multiple versions.  
- **Admin UI:**  
  - **Employee form:** “Upload W-4” (file input), “Generate I-9” (button → download generated form), “Upload completed I-9” (file input). Show “View W-4” / “View I-9” if a file exists.  
  - **Compliance / review page:** New admin page (e.g. `compliance.php` or “Forms” under Employees) listing all employees with columns: W-4 on file (yes/no or date), I-9 on file (yes/no or date), I-9 completed date; filters and links to view/download documents.  
- **API (optional):** Endpoints to upload W-4/I-9 and get document list or download (all behind API key or admin session) if needed for integrations.

---

### 5. EIN display format (Plan §2.6)

- Plan: "Validate employer_ein on save: 9 digits, **format XX-XXXXXXX**; reject invalid."

**Current:** We validate 9 digits (any separator) and store digits only. We don’t enforce or display XX-XXXXXXX.

**Optional:** Format EIN as XX-XXXXXXX when displaying (e.g. company-settings, W-2, docs). No change to validation required if 9-digit check is kept.

---

## Summary

| Item | Severity | PRD/Plan | Current |
|------|----------|----------|---------|
| W-2 as PDF/ZIP | **High** | ZIP of PDFs or single PDF | HTML only |
| **W-4 upload** | **High** | — | No upload; no stored W-4 documents |
| **I-9 generation** | **High** | — | No I-9 form generation |
| **I-9 / W-4 storage & review** | **High** | — | No stored I-9 docs; no UI to review W-4s or I-9s |
| I-9 within 3 business days | Medium | Compliance note | Stored only; no validation/warning |
| API key not in URL | Medium | Plan + security | Query still accepted |
| EIN XX-XXXXXXX | Low | Plan | 9 digits validated; display not formatted |

The main functional gaps are: **W-2 as PDF/ZIP**; **W-4 upload and storage**; **I-9 generation and storage**; and **admin UI to review stored W-4s and I-9s**. The rest are compliance/UX or security refinements.
