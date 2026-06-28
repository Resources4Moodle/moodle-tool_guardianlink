# ERP/SIS API design

GuardianLink v1.0.0-rc1 includes Moodle external-service declarations in `db/services.php` and classes under `classes/external/`. The goal is to let a school's central ERP/SIS become the authoritative source for adult details, learner links, care organisations, and health/care summaries.

## Security model

- Use a dedicated service user.
- Assign only the GuardianLink sync capability and any Moodle web-service permissions required by your site policy.
- Keep the service disabled until token, IP, transport, logging, and data-sharing agreements are reviewed.
- Do not use a teacher account or a parent account as the ERP service user.
- Keep evidence documents in the ERP/records system and sync only references or status codes.

## Declared service

`GuardianLink ERP sync service` with shortname `guardianlink_erp`.

## Functions

### `tool_guardianlink_upsert_relationships`

Creates or updates adult-to-learner relationships. Important fields include:

- `sourcecode`
- adult locators: `adultid`, `adultidnumber`, `guardianid`, `guardianidnumber`
- learner locators: `learnerid`, `learneridnumber`, `childid`, `childidnumber`
- `externalid`, `sourcerevision`, `tenantkey`
- `reltype`, `relcategory`
- `legal`
- `authoritybasis`, `authoritystatus`, `confidentiality`
- `householdkey`, `contactgroupkey`
- `status`, `consentstatus`
- `starttime`, `endtime`, `reviewtime`
- `restrictionsjson`, `rightsjson`
- `scopes[]`

Scopes can set course/category permissions such as grades, completion, teacher contact, messaging, assisted view, health summary, tutor management, and policy consent.

### `tool_guardianlink_revoke_relationships`

Revokes relationships by `sourcecode` and `externalid`. This is preferred to deletion because revocation preserves audit history.

### `tool_guardianlink_get_relationships`

Reads relationship records for reconciliation. Filters include source, external id, adult id, learner id, status, and limit.

### `tool_guardianlink_upsert_organisations`

Creates or updates hostels, boarding houses, children's homes, orphanages, welfare agencies, tutoring providers, and similar organisations.

### `tool_guardianlink_upsert_health_records`

Creates or updates minimal health/care summaries. This endpoint should be enabled only after health/care governance is agreed.

### `tool_guardianlink_get_audit_events`

Exports recent audit events for governance or SIEM-style monitoring.

### `tool_guardianlink_get_my_learners`

Mobile/dashboard helper for the logged-in authorised adult; attached to the official mobile service in the declaration.

## Example relationship payload

```json
{
  "sourcecode": "ERP",
  "relationships": [
    {
      "adultidnumber": "P10023",
      "learneridnumber": "S8821",
      "externalid": "ERP-REL-441",
      "reltype": "hostel_warden",
      "relcategory": "residential",
      "legal": false,
      "authoritybasis": "hostel_record",
      "authoritystatus": "verified",
      "confidentiality": "standard",
      "status": "active",
      "starttime": 1782864000,
      "endtime": 1790812800,
      "scopes": [
        {
          "scopekind": "course",
          "courseid": 42,
          "allowoverview": true,
          "allowcompletion": true,
          "allowattendance": true,
          "allowteachercontact": true,
          "allowgrades": false,
          "allowhealthsummary": false
        }
      ]
    }
  ]
}
```

## Import philosophy

- ERP may be authoritative for identity and legal/care status.
- Moodle remains authoritative for Moodle course activity data.
- GuardianLink stores relationship grants and audit events, not complete family dossiers.
- Revocation and expiry are first-class sync operations.
- Use idnumber or external id consistently to avoid duplicate relationships.
