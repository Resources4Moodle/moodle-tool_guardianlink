# Health and care records

GuardianLink includes a health and care summary model because learning support often depends on information beyond grades and deadlines. The model is deliberately conservative.

## What this is

A minimal support summary system for Moodle-context needs such as:

- Allergy alert relevant to school activities.
- Medication support note.
- Care plan summary.
- Wellbeing support summary.
- Accessibility or reasonable-adjustment support.
- Emergency protocol summary.
- Staff-only care note.

## What this is not

GuardianLink is not a hospital, clinic, counselling, or full safeguarding case-management system. Do not store full medical files, therapy notes, court files, abuse disclosures, or unrestricted narrative case histories in Moodle.

Where high-risk documents exist, store a reference key and keep the source document in the institution's governed record system.

## Visibility levels

- `legal_guardian`: visible only where relationship and scope allow health summary.
- `residential_care`: visible to authorised residential-care/hostel roles where explicitly scoped.
- `staff_only`: visible to school staff with health/care capability.
- `safeguarding`: visibility should be restricted to safeguarding workflows; the plugin stores only minimal references/summaries.

## Governance controls

- Health records are disabled by default.
- Health records can require approval before becoming active.
- Every create/update action is audited.
- Adult visibility additionally requires relationship, active status, date validity, and a scope with `allowhealthsummary`.
- Records include start, end, and review timestamps.
- Retention is governed by institution policy.

## Inclusion of residential and care settings

Hostel wardens, boarding guardians, children's-home staff, orphanage staff, foster carers, and welfare workers may need learning-support visibility. The plugin therefore separates family role, residential role, care role, legal responsibility, and health-summary permission.

A child in a hostel or home outside the school can be supported without pretending the hostel is part of the school and without forcing the staff member into a fake parent category.
