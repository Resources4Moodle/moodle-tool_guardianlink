# Phase 1-4 completion matrix for v1.0.0-rc1

This document maps the earlier roadmap into the current package. “Implemented” means the package contains schema/classes/pages/declarations for the feature. It does not mean the feature has passed live Moodle installation QA.

## Phase 1: Safe MVP

| Item | Status in package |
|---|---|
| Admin relationship mapping | Implemented through `admin/relationships.php`, relationship form, and relationship service. |
| Per-course/course-category scopes | Implemented in `tool_guardianlink_scope` and service methods. |
| Family/adult dashboard | Implemented through `index.php`, `child.php`, and `/my/admin.php`. |
| Teacher proxy contact | Implemented in messaging service, message metadata, recipient resolver, and teacher page; reply UX still needs production hardening. |
| Expiry task | Implemented in scheduled task and `expire_due_grants()`. |
| Audit export/review | Implemented with access log table, audit admin page, API endpoint. |
| CSV import | Implemented as a CLI importer plus the ERP API; a full browser wizard can be added later for non-ERP schools. |

## Phase 2: Engagement

| Item | Status in package |
|---|---|
| Tutor request/approval workflow | Implemented through adult page, tutor form, admin approval, service method. |
| Bulk/digest model | Implemented with adult preferences, scheduled digest task, Moodle message provider, and privacy-preserving digest rendering. |
| Teacher recipient resolver | Proxy-recipient service exists; full bulk-mail plugin integration remains a hardening task. |
| Learner notification rules | Settings and audit hooks exist; event-specific notifications should be added after Moodle event QA to avoid noisy or unsafe alerts. |
| Report builder source | Admin audit/report pages and API export are implemented; a native custom-report datasource is documented as the remaining upstream-polish item. |
| Moodle mobile support | `get_my_learners` external function declared for mobile; richer mobile actions remain future work. |

## Phase 3: Policy and integrations

| Item | Status in package |
|---|---|
| SIS/ERP imports | Implemented through Moodle external service declarations and classes. |
| External identifier mapping | Implemented in `tool_guardianlink_extmap`. |
| Synchronisation audit | Implemented in `tool_guardianlink_erpsync` and integrations admin page. |
| Policy acknowledgements | Scope fields and relationship consent status exist; deep Moodle policy-tool integration remains future work. |
| Safeguarding/restricted contact model | Implemented with authority status, confidentiality, restrictions JSON, restricted access profiles, and admin docs. |
| Multiple households and restrictions | Implemented with household/contact group keys and confidentiality controls. |

## Phase 4: Upstream Moodle proposal

| Item | Status in package |
|---|---|
| Core relationship subsystem proposal | Documented in `docs/roadmap.md`; plugin now provides a concrete reference implementation. |
| Proxy messaging recipient resolver | Implemented at service level; candidate for future API extraction. |
| Delegated-access event model | Audit model implemented; formal core proposal remains a governance step. |
| Family dashboard APIs | Initial API implemented through `get_my_learners`; fuller API can follow after QA. |
| Compatibility with long-tail institutions | Deliberately dropped for 4.5-5.1 in this version, per project decision. |

## Remaining gaps before stable 1.0.0

- Live Moodle 5.2 install/upgrade testing.
- PHPUnit/Behat tests.
- Native Moodle custom-report datasource for site-specific report builder use.
- CSV import wizard for schools that cannot use ERP APIs.
- Complete proxy-message reply UX.
- Data-retention approval workflow for high-risk deletions.
- Accessibility and translation review.
