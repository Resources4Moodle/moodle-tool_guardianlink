# GuardianLink for Moodle 5.2+

GuardianLink is a Moodle **admin-tool plugin** (`tool_guardianlink`, installed under
`admin/tool/guardianlink`) for delegated parent, guardian, carer, residential-care, hostel,
tutor, mentor, and other authorised-adult involvement in a learner's educational life.

It is built around separate adult identity, scoped permission grants, expiry, audit,
teacher-contact masking, relationship roles that are not binary, and optional ERP/SIS
integration. It targets **Moodle 5.2 and later only**.

## Status

**Release candidate — `1.0.0-rc1` (`MATURITY_RC`).** The feature set is complete and covered
by automated PHPUnit tests, and it installs and runs on Moodle 5.2. It is **not yet certified
stable**: a formal security review, accessibility review, and data-protection (DPIA) sign-off
are still required before you rely on it in production with real children's data. Treat this
release as "ready to pilot under supervision", not "deploy and forget".

## Main design rule

GuardianLink does **not** hand parents or tutors Moodle's broad `moodle/user:loginas`
capability. By default an adult acts as **themselves**, with a controlled, scoped, audited view
of the learner's progress. This preserves auditability, child protection, teacher trust, and the
privacy of other learners.

(An optional, experimental "assisted access" feature can drive Moodle's *log in as* in a fenced,
audited way — see the blunt warning below. It is **off** and **out of MVP scope**.)

---

## First 15 minutes (quickstart)

Do this on a **test site** first, with test users, before touching real data.

1. **Install.** Copy this folder to `admin/tool/guardianlink` in your Moodle 5.2+ site, then
   visit **Site administration → Notifications** and complete the upgrade.
2. **Find it.** Everything lives under **Site administration → Plugins → Admin tools →
   GuardianLink**. (It is an *admin tool*, not a *local plugin* and not under "Users".)
3. **Seed role types.** Open **GuardianLink → Role types** and load the default relationship
   taxonomy (legal parent, carer, hostel warden, tutor, …). Nothing else works until at least
   one relationship type and one access profile exist.
4. **Leave the risky switches OFF.** In **GuardianLink → Settings**, confirm *assisted access*,
   the *experimental-risk acknowledgement*, *auto-assign role*, and *health/care records* are
   all unticked. They are off by default — keep them that way for the pilot.
5. **Create one relationship.** Open **GuardianLink → Relationships**, link one test adult to one
   test learner, choose an access profile, and scope it to one course (by course **short name**
   or **ID number** — see "Course references" below).
6. **Check it from the adult's side.** Log in as that adult and open
   `admin/tool/guardianlink/index.php`. You should see only that one learner and only the
   permitted course. Confirm grades/teacher contact appear (or are masked) exactly as you
   scoped them.
7. **Revoke and re-check.** Back as admin, revoke or restrict the relationship, then refresh the
   adult's view. Access must disappear immediately. If it does not, stop and investigate before
   going further.

If steps 6 and 7 behave as expected, your scoping and revocation are wired correctly.

---

## The pages, and who they are for

GuardianLink has two distinct kinds of page. They look similar because both live under
`admin/tool/guardianlink/`, but they are **not** the same audience:

| Page | Audience | What it is |
|------|----------|------------|
| `index.php`, `admin/*.php`, role types, organisations, health, audit, settings | **Site staff** (need a `tool/guardianlink:*` capability) | Governance and configuration |
| `my/admin.php`, `my/tutors.php`, `my/digest.php`, `my/assist.php` | **The authorised adult themselves** | A *personal* control centre, **not** site administration |

> **Important:** the `my/*` pages sit under the `admin/tool/` URL purely because that is where
> the plugin is installed. They are personal pages for the linked adult — being able to open one
> does **not** mean the adult has any site-administration rights.

### Per-page checklists

- **Relationships** — set the relationship type, the access profile, and the **scope** (which
  courses/categories, and which permissions within them). Set an **end time** unless you have a
  reason not to. Re-uploading or re-syncing in *replace* mode revokes scopes you remove.
- **Role types** — only enable the relationship types your institution actually uses. Each type
  carries defaults (legal vs non-legal, default confidentiality); review them before enabling.
- **Organisations** — register hostels/homes/agencies before linking residential-care adults.
- **Health/care** — leave disabled unless you have agreed lawful basis, retention, visibility,
  and safeguarding governance. See the warning below.
- **Bulk upload** — dry-run on a handful of rows first; unknown courses and learners are skipped,
  not invented. See the warning below.
- **Settings** — the four experimental/high-risk switches (assisted, the assisted acknowledgement,
  auto-assign role, health records) stay off for the MVP.

---

## Read these warnings before enabling anything risky

- **Assisted access (log in as) — EXPERIMENTAL, off by default, out of MVP.** This feature lets a
  vetted adult work *through a course as the learner*, fenced to one course, with the real adult
  recorded and assessed activities blocked. Because it **impersonates a learner's session** it is
  high-risk and has **not** completed a security/safeguarding review. It stays completely inert
  unless **both** the organisation master switch **and** a separate experimental-risk
  acknowledgement are enabled — a single accidental toggle can never turn it on. Do not enable it
  in production until your DPO and safeguarding lead have signed off.
- **Auto-assign role — EXPERIMENTAL, off by default.** When on, an active grant also assigns a
  Moodle role in the learner's context, granting access through **core** capabilities that sit
  *outside* GuardianLink's own scope checks. A misconfigured role can over-share. Leave it off
  for the MVP.
- **Health/care records — off by default.** GuardianLink stores **minimal** care summaries, not a
  clinical record system. Health data is special-category personal data: enable it only after you
  have a documented lawful basis, retention rule, visibility policy, and safeguarding process.
  Visibility is filtered per relationship and per scope — verify it shows the right summaries to
  the right adults on your site before trusting it.
- **Proof / evidence documents.** Keep evidence (custody orders, ID, care placements) in your
  ERP/records system. Sync only **references or status codes** into Moodle — do not upload
  sensitive evidence files into GuardianLink.
- **Bulk upload & ERP sync.** Bulk operations create or revoke access at scale. Always pilot on a
  small set, keep the CSV header row exact, and prefer course **short names / ID numbers** over
  numeric ids so a column mix-up cannot silently grant the wrong course.

---

## Course references (CSV, forms, and API)

Wherever you list courses (the relationship form's *Course IDs* field, the bulk-upload
`courseids` column, or category scopes), each entry may be a **course short name**, a **course
ID number**, or a **numeric Moodle course id**. Short name and ID number are preferred because
they are stable and readable across sites; numeric ids are easy to mistype. Entries that do not
match an existing course are **skipped**, not created.

---

## Installation

1. Copy this directory to `admin/tool/guardianlink` in your Moodle root.
2. Log in as a site administrator.
3. Visit **Site administration → Notifications** and complete the upgrade.
4. Review **Site administration → Plugins → Admin tools → GuardianLink**.
5. Seed default relationship role types from **GuardianLink → Role types**.
6. Configure health/care records only after your institution has agreed the lawful basis,
   retention policy, visibility rules, and safeguarding governance process.
7. For ERP/SIS sync, create a **dedicated web-service user** and assign only the GuardianLink
   integration capability (`tool/guardianlink:sync`).

---

## Troubleshooting

- **"I can't find it."** It is under **Plugins → Admin tools → GuardianLink**, not under Local
  plugins or Users. The component is `tool_guardianlink`.
- **An adult sees nothing.** Check the relationship is `active` and `verified` (not revoked,
  disputed, or restricted), that it has at least one scope covering a course the learner is
  enrolled in, and that the scope's time window (start/end) is currently open.
- **An adult still has access after I revoked it.** Revocation is immediate; refresh the adult's
  page. If access persists, confirm you revoked the right relationship and that *auto-assign role*
  was not used to grant access through a separate Moodle role.
- **A course in my CSV was ignored.** The short name / ID number / id did not match an existing
  course, or the learner is not enrolled in it. Unknown courses are skipped by design.
- **The "Assisted access" link/page is missing.** That is expected: it appears only when **both**
  the assisted master switch and the experimental-risk acknowledgement are enabled.
- **Health summaries don't appear.** Health records must be enabled in settings, and visibility is
  filtered per relationship/scope — a non-legal or restricted relationship will not see them.

---

## Documentation

- `docs/product-requirements.md`
- `docs/roles-and-relationship-model.md`
- `docs/admin-rationalisation.md`
- `docs/health-and-care-records.md`
- `docs/erp-api.md`
- `docs/phase-completion-matrix.md`
- `docs/data-protection-impact-assessment-template.md`
- `docs/test-plan.md`

## License

GNU GPL v3 or later, consistent with Moodle plugin expectations.
