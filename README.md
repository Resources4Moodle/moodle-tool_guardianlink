# GuardianLink for Moodle 5.2+

GuardianLink is a Moodle admin-tool plugin for delegated parent, guardian, carer, residential-care, hostel, tutor, mentor, and other authorised-adult involvement in a learner's educational life.

This version targets **Moodle 5.2 and later only**. It is built around separate adult identity, scoped permission grants, expiry, audit, teacher-contact masking, relationship roles that are not binary, and ERP/SIS integration.

## Main design rule

GuardianLink does not grant parents or tutors Moodle's broad `moodle/user:loginas` capability. The adult acts as themselves, with a controlled delegated view of the learner's progress. This preserves auditability, child protection, teacher trust, and peer privacy.

## What is included in v0.2.1-alpha

- Moodle 5.2+ plugin metadata and schema.
- Rationalised GuardianLink admin category with separate pages for overview, relationships, role types, organisations, health/care, tutor requests, messaging, integrations, audit, and settings.
- Configurable relationship role taxonomy covering legal parents, restricted parents, guardians, foster/kinship carers, hostel wardens, residential key workers, orphanage/children's-home staff, case workers, tutors, mentors, sponsors, and other authorised adults.
- Adult-facing child administration area under `/local/guardianlink/my/admin.php`, tutor requests under `/local/guardianlink/my/tutors.php`, and digest preferences under `/local/guardianlink/my/digest.php`.
- Health and care summary model that is deliberately not a clinical medical record system.
- External organisation registry for hostels, boarding houses, children's homes, orphanages, welfare agencies, troubled-home support organisations, and approved tutoring providers.
- ERP/SIS web-service declarations and Moodle 5.2-style external API classes.
- Expanded database schema for relationships, scopes, organisations, health/care summaries, access logs, ERP sync logs, external identifier mappings, and digest preferences.
- Scheduled expiry, digest delivery, and retention tasks.
- Privacy provider scaffold and DPIA documentation.
- SVG logo and icon.

## Installation

1. Copy `local/guardianlink` into your Moodle root.
2. Log in as a site administrator.
3. Visit **Site administration > Notifications**.
4. Review **Site administration > Plugins > Local plugins > GuardianLink**.
5. Seed default relationship role types from **GuardianLink > Role types**.
6. Configure health/care records only after your institution has agreed the lawful basis, retention policy, visibility rules, and safeguarding governance process.
7. Create a dedicated web-service user for ERP/SIS sync and assign only the required GuardianLink integration capability.

## Production status

This is an alpha implementation package: syntax checked, schema parsed, and structured for Moodle 5.2+ development. It still needs installation testing inside a live Moodle 5.2 site, PHPUnit/Behat coverage, security review, accessibility review, and data-protection review before production use.

## Documentation

Start with these files:

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
