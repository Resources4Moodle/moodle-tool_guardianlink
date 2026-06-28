# GuardianLink roadmap from v1.0.0-rc1 onward

## Completed in v1.0.0-rc1 package

- Moodle 5.2+ baseline only.
- Rationalised admin category.
- Configurable non-binary relationship type taxonomy.
- Parent/adult child admin area.
- Tutor request and admin approval surface.
- Health/care summary model and admin surface.
- Residential/care organisation registry.
- ERP/SIS external service declarations and API classes.
- Extended schema for relationships, scopes, health/care, organisations, sync audit, external id mapping, and digest preferences.
- Scheduled expiry/retention task.
- Privacy and DPIA documentation.

## Next engineering hardening

1. Install and upgrade test on a clean Moodle 5.2.1+ site.
2. PHPUnit tests for relationship service, external services, health visibility, expiry, and audit.
3. Behat tests for admin relationship mapping, adult dashboard, tutor request, health page, and teacher proxy messaging.
4. CSV import wizard with dry-run validation.
5. Harden digest templates with site-specific analytics and translation review.
6. Proxy-message reply handling and institution-level monitoring policy.
7. Report-builder sources.
8. Mobile API expansion.
9. Accessibility, language-string, and RTL review.
10. Security review of web-service tokens and capabilities.

## Upstream Moodle pathway

GuardianLink can become a proposal for Moodle core once it proves the following concepts in real institutions:

- Core delegated-relationship subsystem.
- Scoped support-network access independent of role assignments.
- Privacy-preserving family/contact recipient resolver.
- Audited delegated views instead of silent learner impersonation.
- Health/care summary interface boundaries.
- Mobile-friendly learner support-network APIs.

A core proposal should be narrow: relationship API, delegated-access audit model, and proxy recipient resolver. Health/care and institutional workflows may remain plugin-level extensions.
