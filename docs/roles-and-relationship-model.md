# Roles and relationship model

GuardianLink separates three concepts that are often confused:

1. Moodle roles and capabilities: what a logged-in user can do in Moodle contexts.
2. GuardianLink relationship types: why an adult is linked to a learner.
3. GuardianLink scopes: exactly which courses, categories, permissions, and dates apply.

A person may have a Moodle account but no GuardianLink relationship. A teacher may message an authorised adult through GuardianLink without being allowed to browse that adult's profile. A parent may be legally responsible but not permitted to see a specific restricted course. A tutor may see only one course and never see family metadata.

## Default relationship types

| Code | Meaning | Typical profile | Notes |
|---|---|---|---|
| `legal_parent` | Parent with recognised legal or parental responsibility | `family_full` | May receive broad educational summaries where policy permits. |
| `parent_no_contact` | Parent whose access/contact is restricted or disputed | `health_sensitive` | Must not be treated as a normal active parent. Use authority status and confidentiality controls. |
| `guardian` | Court/school/state-recognised guardian | `family_full` | May or may not be biologically related. |
| `carer` | Day-to-day care provider | `family_basic` | Educational support role, not automatically legal authority. |
| `foster_carer` | Foster/kinship placement adult | `residential_care` | May need operational information and teacher contact. |
| `adoptive_parent` | Adoptive parent | `family_full` | Treat according to institutional/legal record, not assumptions. |
| `step_parent` | Step-parent or partner in the home | `family_basic` | Usually requires explicit institutional verification. |
| `hostel_warden` | Hostel or boarding residence staff | `hostel_guardian` | Can be outside the school; still requires Moodle account and scoped grant. |
| `residential_keyworker` | Children's home or residential-care key worker | `residential_care` | Supports children in formal care settings. |
| `orphanage_staff` | Orphanage/home staff | `residential_care` | Included so the system language does not marginalise these learners. |
| `case_worker` | Welfare, social, or safeguarding case worker | `health_sensitive` | Access should be minimal and staff-controlled. |
| `tutor` | Private tutor or helper | `tutor_limited` | Normally course-scoped and time-limited. |
| `mentor` | Mentor or community support adult | `tutor_limited` | Educational support without family visibility. |
| `sponsor` | Financial/educational sponsor | `family_basic` | Usually limited to progress/digest views. |
| `other` | Institution-defined authorised adult | `family_basic` | Avoid using this when a clearer local type exists. |

## Authority basis

The relationship type is not enough. Each active relationship should also state why the institution recognises it:

- `school_record`: recorded by admissions or student office.
- `court_order`: order or legally binding restriction exists; store reference, not the document.
- `care_order`: social-care or child-protection authority basis.
- `hostel_record`: residential/hostel authority basis.
- `consent`: consent from an authorised adult or learner where lawful.
- `contract`: tutor, sponsor, apprenticeship, or service contract.
- `emergency`: temporary urgent arrangement requiring review.

## Authority status

- `unverified`: entered but not yet trusted.
- `verified`: institution has accepted the relationship.
- `restricted`: recognised but limited by policy/order/safeguarding.
- `disputed`: conflict or uncertainty exists; escalation required.
- `revoked`: no current authority.

## Confidentiality levels

- `standard`: normal school-family communication.
- `restricted`: limit staff visibility and never reveal to other adults.
- `sensitive`: only named staff/admin roles should see metadata.
- `safeguarding`: safeguarding workflow; avoid narrative details in GuardianLink.

## Access profiles

`family_basic` gives overview, completion, activities, calendar, and teacher contact but not grades or health summaries by default.

`family_full` adds grades, attendance, tutor-management, and policy-consent where local policy permits.

`tutor_limited` allows only learning-support visibility, usually without grades, teacher messaging, health, or policy consent.

`hostel_guardian` allows operational education support such as attendance, calendar, and teacher contact.

`residential_care` adds health/care summary visibility only where the scope explicitly allows it.

`health_sensitive` minimises access for restricted or high-risk situations.

## Household and contact grouping

GuardianLink has `householdkey` and `contactgroupkey`, but these are governance fields, not something displayed to adults. They help the school route messages correctly while avoiding disclosure between separated parents, carers, or households.

## What teachers should see by default

Teachers should see a button such as “message authorised adults” or “message family/support network”. They should not automatically see:

- Adult email addresses.
- Family structure.
- Custody conflict notes.
- Legal basis details.
- Health or safeguarding notes.
- Whether another parent is blocked or restricted.

Where a school chooses to disclose more, that must be capability controlled and audited.
