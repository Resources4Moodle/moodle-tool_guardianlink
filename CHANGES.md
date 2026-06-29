# Changelog — tool_guardianlink (GuardianLink)

All notable changes to this plugin are documented here.

## v1.0.0-rc3 (2026) — 2026063019

Third release candidate. Closes the gate-enforcement, classroom-connection, help-system and CI
findings of a follow-up audit.

- **Course policy enforced everywhere (GATE-01):** `course_allows_teacher_proxy()` is enforced in
  the teacher/send pages AND in the message services (`send_proxy_message`, `send_proxy_template`,
  `send_template_to_adults`, `send_one_off`), so a course that disables teacher proxy messaging
  cannot be bypassed by any caller.
- **Grade preview gated (GATE-02):** grade item options and grade tokens are only offered to a
  sender who holds `moodle/grade:viewall`; the service template sends take an explicit
  sender-can-view-grades flag that defaults to false (fail closed).
- **`send_one_off()` fails closed (GATE-03):** it now requires a live messaging scope for the
  learner/course and respects the course proxy policy.
- **Bulk audience intersection (GATE-04):** category sends require the adult's scope to intersect the
  target category; cohort sends require a learner/site scope (a cohort is not a teaching context).
- **Metadata visibility (GATE-05/06):** the course dashboard shows ordinary teachers only their own
  message threads, and hides authorised-adult identities in acknowledgement tables unless the viewer
  holds family-metadata/audit/manage capability.
- **Classroom connection (GATE-07):** `visible_courses_for_adult()` expands category, learner and
  site scopes into concrete course cards on the adult dashboard, with a plain-language reason for
  access on every row.
- **Proposal policy (GATE-09):** tutor-request course scopes honour `allowparentpropose`.
- **Help system + inclusive language:** the in-product help now serves authorised adults and higher
  education explicitly (new "Words we use", "For authorised adults" and "Higher education" sections),
  and user-facing labels move to inclusive wording (authorised adult, learner, student-authorised
  supporter, share results with authorised adults).
- **CI hardening:** the workflow defines an explicit PHP extension list (was an undefined matrix
  variable), runs quality steps fail-fast instead of `if: always()`, and uploads failure artifacts.

Verified before release: PHPUnit 52/52 and Behat 4/4 green on the Moodle 5.2 test instances.

## v1.0.0-rc2 (2026) — 2026063018

Second release candidate. Resolves the findings of an in-depth security review (two Critical and
ten High issues) plus a contained performance improvement.

- **Adult-facing access invariant (Critical):** only a verified, active, in-date relationship may
  expose learner data. Active-but-unverified relationships no longer pass overview/calendar checks
  or appear in adult-facing lists.
- **Learner dashboard (Critical):** child.php now renders only from active, in-date scopes and
  re-checks every feature (grades/attendance/teacher-contact/assisted) per course via
  can_access_child(), so expired/revoked/future scopes cannot expose data.
- **Recipient eligibility:** proxy and bulk messaging share one scope-eligibility helper that
  honours scope time windows and category scopes; bulk messaging always requires a verified
  relationship (restricted/unverified can no longer slip through).
- **Health visibility:** a course-specific health scope no longer satisfies a learner-level health
  check (learner/site scope required).
- **Grades:** grade items are course-bound (a tampered grade-item id cannot leak another course's
  grade); the teacher dashboard gates grade display on moodle/grade:viewall.
- **Enrolment/course validation:** teacher send pages, tutor-request scopes, independent-access
  acknowledgements and course-specific health records all validate learner enrolment / course
  offering / requester scope server-side.
- **Immediate de-provisioning:** revoke, restriction, dispute and expiry strip standing role grants
  (auto-assigned role and assisted course-views) synchronously; cleanup tasks are only a backstop.
- **Atomic upserts:** relationship, scope, external-map and audit writes commit in one delegated
  transaction; scope kinds are validated against a whitelist before any write.
- **Privacy export:** subject-access export now covers the requester in every role (learner, adult,
  teacher) rather than only when exporting their own user context.
- **Performance:** the course-level class average is memoised per request (computed once per send,
  not once per recipient). Larger scaling items (queued bulk sends, overdue snapshots, dashboard
  pagination, batched ERP upserts) are documented as deliberate follow-ups.

## v1.0.0-rc1 (2026) — 2026063017

First release candidate for Moodle 5.2+. Feature-complete and covered by automated tests;
a formal security, accessibility, and data-protection review is still required before a
stable 1.0.0 release.

- Delegated, scoped, audited authorised-adult (parent/guardian/carer/tutor/hostel/observer)
  access to a learner's progress, without learner impersonation and without exposing contact details.
- Relationship registry (site + course-scoped for permitted faculty), CSV bulk import with a
  downloadable sample, access profiles and relationship-type management. Course references in the
  registry and CSV accept a course short name, ID number, or numeric id.
- Hardened access control: per-record health-summary visibility, category-scope expiry,
  restricted/disputed relationships hidden from adult-facing lists, replace-safe scope
  synchronisation, message-thread lockout when a relationship is revoked/restricted, and digests
  gated on verified authority status.
- Unique external-identity and scope keys, with a data-preserving upgrade migration that
  de-duplicates pre-existing rows.
- Privacy provider now performs real erasure (deletes preferences/messages, anonymises threads,
  retains legally-required audit and safeguarding records) in addition to describe and export.
- Privacy-preserving proxy messaging and digests (no-reply addressing), email/message templates
  with placeholders including per-activity grade tokens, and course-scoped teacher templates.
- In-course faculty dashboard, consolidated oversight report with charts/CSV export, audit log.
- Independent (unsupervised) access acknowledgements (three-key admin → teacher → parent workflow).
- Higher-education "observer parent" profile (grades and teacher contact only).
- Public API (`\tool_guardianlink\api`) for other plugins; ERP/SIS web services.
- Full privacy provider, capability set, scheduled tasks, events, and a help manual.

**Experimental, off by default (out of MVP scope):**

- *Governed assisted access* (a parent co-logs in alongside a young learner — banner-flagged,
  time-capped, assessed activities blocked). It drives Moodle's "log in as" and stays inert unless
  BOTH an organisation master switch and a separate experimental-risk acknowledgement are enabled,
  so a single accidental toggle can never turn it on. Do not enable in production until reviewed.
- *Auto-assign authorised-adult role* (grants access through core Moodle capabilities outside
  GuardianLink's own scope checks).

Tested on Moodle 5.2 with PostgreSQL and MySQL/MariaDB. PHPUnit and Behat suites included.
