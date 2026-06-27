# Changelog — tool_guardianlink (GuardianLink)

All notable changes to this plugin are documented here.

## v1.0.0 (2026) — 2026063015

First stable release for Moodle 5.2+.

- Delegated, scoped, audited authorised-adult (parent/guardian/carer/tutor/hostel/observer)
  access to a learner's progress, without learner impersonation and without exposing contact details.
- Relationship registry (site + course-scoped for permitted faculty), CSV bulk import with a
  downloadable sample, access profiles and relationship-type management.
- Privacy-preserving proxy messaging and digests (no-reply addressing), email/message templates
  with placeholders including per-activity grade tokens, and course-scoped teacher templates.
- In-course faculty dashboard, consolidated oversight report with charts/CSV export, audit log.
- Governed assisted access (parent co-login) and independent (unsupervised) access acknowledgements.
- Higher-education "observer parent" profile (grades and teacher contact only).
- Public API (`\tool_guardianlink\api`) for other plugins; ERP/SIS web services.
- Full privacy provider, capability set, scheduled tasks, events, and a help manual.

Tested on PostgreSQL and MySQL. PHPUnit and Behat suites included.
