# GuardianLink product requirements for Moodle 5.2+

## Product intent

GuardianLink increases healthy adult involvement in a learner's education without turning the adult into the learner. It supports parents, guardians, carers, foster/kinship carers, hostel wardens, residential-care workers, orphanage or children's-home staff, welfare officers, private tutors, mentors, sponsors, and other authorised adults.

The plugin must work for modern families and care settings without assuming two parents, a single home, a binary mother/father structure, or a single legal authority pattern.

## Non-negotiable principle

Do not implement silent parent login as the child. Silent impersonation weakens accountability, can expose peer data, can permit submissions and posts under the learner's identity, and can create safeguarding disputes. GuardianLink uses delegated, scoped, time-limited, auditable access.

## Moodle version baseline

This version is 5.2+ only. The plugin metadata sets `requires = 2026042000`. All new API surfaces are written for the namespaced external API model used in current Moodle developer documentation.

## Core actors

- Site administrator: owns configuration, role type catalogue, ERP/SIS sync, and high-risk governance.
- Relationship administrator: maps adult-learner relationships and verifies authority basis.
- Safeguarding/privacy officer: controls restricted family situations, disclosure rules, retention, and health/care visibility.
- Health/care administrator: maintains minimal learner support summaries where the school has a lawful basis.
- Teacher: contacts authorised adults through proxy routing without direct access to adult contact details by default.
- Authorised adult: sees linked learners and scoped progress, communicates with permitted teachers, and may request tutor access where enabled.
- Tutor/helper: receives narrower, course-scoped, time-limited access, normally after school approval.
- Residential or hostel staff: receive scoped access where the learner lives away from home or receives institutional care.
- Learner: remains the account owner; local policy decides whether and how the learner is notified about adult access.

## Main product areas

1. Relationship registry: neutral adult-to-learner records with relationship type, authority basis, authority status, confidentiality level, consent/policy status, household/contact grouping, start/end/review times, external identifiers, and JSON restrictions/rights metadata.
2. Course/category scopes: per-relationship visibility for overview, grades, completion, activities, attendance, calendar, teacher contact, messaging, assisted view, health summary, tutor management, and policy consent.
3. Parent/adult child admin: adult-facing dashboard to see linked learners, relationship status, contact routes, tutor requests, and permitted care/health summaries.
4. Tutor workflow: adult proposes tutor/helper; institution approves, narrows, rejects, or expires grants.
5. Teacher communication: proxy messaging and future bulk-recipient resolver so teachers need not see parent email addresses or family-status details.
6. Health and care summaries: minimal support-level data for allergies, medication alerts, care plans, access needs, wellbeing notes, emergency protocols, and staff-only safeguarding summaries. It is not a clinical record system.
7. Residential/care organisations: separate organisation registry for hostels, boarding houses, children's homes, orphanages, welfare agencies, and tutoring organisations, including organisations outside the school.
8. ERP/SIS API: web-service functions for upserting relationships, revoking relationships, reading relationships, upserting organisations, upserting health/care summaries, reading audit events, and mobile/adult dashboards.
9. Audit and retention: append-style logs for relationship changes, access events, proxy messages, API sync jobs, and expiry tasks.
10. Privacy API: metadata/export/delete scaffolding, with explicit warnings that institutional policies and data-protection review remain required.

## Inclusion requirements

The data model must support:

- Two or more active adults with equal rights.
- Separated parents who must not see each other's details.
- One adult with legal responsibility and another with educational support rights only.
- Restricted or disputed contact.
- Foster care and kinship care.
- Adoptive parents and step-parents.
- Same-sex parents and non-binary adults.
- Guardians appointed by courts, state agencies, or schools.
- Boarding-school guardians and hostel wardens.
- Children in orphanages, children's homes, or troubled-home support programmes.
- Refugee, displaced, or international learners with temporary carers.
- Private tutors and mentors with narrow time-limited access.
- Adult learners with sponsors, employers, or case workers.

## Acceptance criteria for v1.0.0-rc1

- Plugin is Moodle 5.2+ only.
- Admin area is split by responsibility, not stuffed into one settings page.
- Default role taxonomy is seedable and configurable.
- Relationship schema is non-binary and supports authority/confidentiality status.
- Adult-facing child admin area exists.
- Tutor request workflow exists.
- Health/care summary table, form, admin page, and API endpoint exist.
- External organisation registry exists.
- ERP/SIS web-service declarations and classes exist.
- Expiry and audit mechanisms exist.
- Privacy/DPIA documentation is present.
- Code is syntax checked and install XML parses.

## Still required before production

- Full installation test on a Moodle 5.2 site.
- Automated PHPUnit and Behat tests.
- Security review of all capabilities and web-service token use.
- Accessibility review of all admin and adult-facing screens.
- Local legal review for GDPR, FERPA, COPPA, safeguarding, data-retention, and health-data obligations.
- Hardening of proxy messaging and report-builder integration.
