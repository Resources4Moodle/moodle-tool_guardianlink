# Changelog — tool_guardianlink (GuardianLink)

All notable changes to this plugin are documented here.

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
