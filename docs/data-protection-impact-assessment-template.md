# GuardianLink DPIA template

This is not legal advice. It is a working template for an institution's data protection officer, safeguarding lead, and Moodle administrator.

## Processing purpose

Support learner progress, school-family communication, tutor assistance, safeguarding, and lawful parental/guardian involvement.

## Data subjects

- Learners, including children and vulnerable learners.
- Parents, guardians, carers, tutors, and other authorised adults.
- Teachers and school staff participating in communications.

## Personal data processed

- Moodle user identifiers and display names.
- Relationship type, status, start/end time, and approval metadata.
- Course scopes and permission flags.
- Access logs and proxy messaging metadata.
- Administrative notes only when necessary and policy-approved.

## Special risk categories

- Child educational data.
- Family structure and custody information.
- Safeguarding-related access patterns.
- Potential exposure of peer data in forums, groups, assignments, and collaborative tools.
- Misuse of delegated access to pressure, surveil, or impersonate a learner.

## Key controls

- No silent login as learner.
- Separate adult identity at all times.
- Course-scoped and time-limited grants.
- Admin-controlled relationship creation.
- Guardian-proposed tutors require approval by default.
- Teacher contact masking enabled by default.
- Audit log for every access.
- Learner notice where appropriate and safe.
- Revocation and emergency suspension workflow.
- Data export and retention policy alignment.

## Questions for institutional policy

1. Which staff roles may approve relationships?
2. What evidence is required for legal parental responsibility or care status?
3. How are separated parents, court orders, and restricted-contact cases represented?
4. When should the learner be notified of access?
5. What information is too sensitive for family dashboard display?
6. Which courses should default to guardian-visible?
7. How long should access logs be retained?
8. Who handles access disputes and data subject requests?
9. Can parents nominate tutors, and what verification is required?
10. Which jurisdictions apply to the institution and the learner population?

## Residual risks

- Moodle activity modules may expose peer data if rendered naively.
- Parent access can increase pressure on learners if not designed sensitively.
- Family disputes can turn educational access into a conflict channel.
- Teacher messages may accidentally disclose sensitive information.
- Overly broad tutor access can become unauthorised third-party disclosure.

## Mitigation plan

- Use curated reports and dashboards instead of raw child sessions for MVP.
- Build module-by-module review before assisted view is allowed.
- Provide school policy templates and training.
- Make high-risk grants expire quickly.
- Require reason capture for overrides.
- Review logs periodically.
