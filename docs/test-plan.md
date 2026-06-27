# GuardianLink v0.2.1-alpha test plan

## Installation

- Install only on Moodle 5.2.0 or later.
- Confirm plugin metadata rejects older Moodle versions through `$plugin->requires = 2026042000`.
- Run Site administration > Notifications.
- Seed default relationship role types.
- Verify all admin pages load.

## Relationship tests

- Create a legal parent relationship with two scoped courses.
- Create a restricted parent relationship and confirm teacher metadata remains hidden.
- Create a hostel warden relationship with attendance and teacher-contact permissions but no grades.
- Create an orphanage/children's-home staff relationship with residential-care profile.
- Create a tutor relationship with one course and an expiry date.
- Confirm expired relationships are deactivated by scheduled task.

## Adult dashboard tests

- Adult with one learner sees only that learner.
- Adult with multiple learners sees all linked learners.
- Separated adults cannot see each other's contact details or relationship metadata.
- Tutor sees only scoped course data.
- Adult can submit a tutor request only when settings and scope allow it.

## Health/care tests

- Confirm health records are disabled by default.
- Enable health records and create a minimal support summary.
- Confirm visibility requires both active relationship and scope `allowhealthsummary`.
- Confirm staff-only/safeguarding records are not shown to ordinary adults.
- Confirm create/update actions are audited.

## ERP/API tests

- Enable a web-service token for a dedicated service user.
- Upsert a relationship by `idnumber`.
- Upsert the same relationship again and confirm update, not duplication.
- Revoke by `sourcecode` + `externalid`.
- Upsert hostel/residential organisation.
- Upsert health/care summary with health module enabled.
- Read audit events after sync.

## Communication tests

- Teacher can select proxy-contact workflow where capability allows.
- Teacher cannot browse adult emails by default.
- Proxy recipient resolver excludes revoked, expired, restricted, or out-of-scope adults.

## Privacy/security tests

- Confirm access logs include actor, learner, relationship/course where relevant, action, result, IP, source, and timestamp.
- Confirm privacy metadata covers all GuardianLink tables containing personal data.
- Confirm audit retention task honours configured retention months.
- Confirm high-risk notes are not used to store full court, safeguarding, or medical documents.

## Accessibility and usability

- Test all admin pages with keyboard navigation.
- Confirm neutral language: authorised adult, learner, relationship, support network.
- Confirm no UI assumes mother/father or a two-parent household.
