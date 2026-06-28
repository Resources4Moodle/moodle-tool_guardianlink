# Admin rationalisation

The first prototype placed too much responsibility into a generic admin/settings area. GuardianLink v1.0.0-rc1 reorganises administration around operational responsibility and risk.

## Admin category

GuardianLink now appears as one local-plugin admin category containing:

1. Overview: counts, entry points, governance reminders.
2. Relationships: authorised adult-to-learner mapping and course/category scope entry.
3. Role types: seed and review relationship taxonomy.
4. Organisations: hostels, boarding houses, orphanages, children's homes, welfare agencies, and tutoring providers.
5. Health/care: minimal health and care summaries with strong warnings.
6. Tutor requests: approve/narrow/reject adult-proposed tutors.
7. Messaging: proxy-message and digest governance.
8. Integrations: ERP/SIS service functions and sync logs.
9. Audit: access/sync audit review.
10. Settings: only cross-cutting defaults and risk switches.

## Why this layout

Relationship mapping, health records, external integrations, tutor approval, and audit review are different jobs. They should not all be hidden in one cluttered settings page. The split also allows institutions to delegate capabilities more safely. For example, a safeguarding officer can view audit and health/care summaries without being able to change ERP API settings.

## Capabilities

Important system capabilities include:

- `tool/guardianlink:manage`
- `tool/guardianlink:maprelationships`
- `tool/guardianlink:configureroles`
- `tool/guardianlink:approvetutors`
- `tool/guardianlink:viewallrelationships`
- `tool/guardianlink:manageorganisations`
- `tool/guardianlink:managehealth`
- `tool/guardianlink:viewhealth`
- `tool/guardianlink:sync`
- `tool/guardianlink:viewaudit`
- `tool/guardianlink:viewreports`
- `tool/guardianlink:managedigests`

Course capability:

- `tool/guardianlink:sendproxymessages`

The explicit default is that teachers can message through GuardianLink but cannot browse family metadata.

## Adult-facing child admin

Authorised adults get their own area at `/admin/tool/guardianlink/my/admin.php`. This is not Moodle site administration. It is a personal control centre for linked learners, relationships, permitted tutor requests, and later digest preferences.

This distinction is important: a parent should not need site-admin-like screens. They need a family/support dashboard designed around their linked children.
