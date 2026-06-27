<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Rationalised admin settings and pages for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = new admin_category('tool_guardianlink_cat', get_string('guardianlinkadmin', 'tool_guardianlink'));
    // Classified under Site administration > Plugins > Admin tools (the correct home for a tool_
    // plugin), NOT under "Local plugins".
    $ADMIN->add('tools', $category);

    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_overview',
        get_string('admin_overview', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/index.php'),
        'tool/guardianlink:manage'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_relationships',
        get_string('admin_relationships', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/relationships.php'),
        'tool/guardianlink:maprelationships'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_uploadparents',
        get_string('uploadparents', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/upload_parents.php'),
        'tool/guardianlink:maprelationships'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_roletypes',
        get_string('admin_roletypes', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/roletypes.php'),
        'tool/guardianlink:configureroles'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_profiles',
        get_string('admin_profiles', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/profiles.php'),
        'tool/guardianlink:configureroles'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_organisations',
        get_string('admin_organisations', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/organisations.php'),
        'tool/guardianlink:manageorganisations'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_health',
        get_string('admin_health', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/health.php'),
        'tool/guardianlink:managehealth'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_tutor_requests',
        get_string('admin_tutor_requests', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/tutor_requests.php'),
        'tool/guardianlink:approvetutors'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_messaging',
        get_string('admin_messaging', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/messaging.php'),
        'tool/guardianlink:managedigests'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_templates',
        get_string('admin_templates', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/templates.php'),
        'tool/guardianlink:managedigests'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_bulkmail',
        get_string('admin_bulkmail', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/bulkmail.php'),
        'tool/guardianlink:sendbulkmessages'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_integrations',
        get_string('admin_integrations', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/integrations.php'),
        'tool/guardianlink:sync'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_audit',
        get_string('admin_audit', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/audit.php'),
        'tool/guardianlink:viewaudit'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_report',
        get_string('admin_report', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/report.php'),
        'tool/guardianlink:viewreports'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_revalidation',
        get_string('admin_revalidation', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/admin/revalidation.php'),
        'tool/guardianlink:maprelationships'
    ));
    $ADMIN->add('tool_guardianlink_cat', new admin_externalpage(
        'tool_guardianlink_manual',
        get_string('admin_manual', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/manual.php'),
        'tool/guardianlink:manage'
    ));

    $settings = new admin_settingpage(
        'tool_guardianlink_settings',
        get_string('admin_settings', 'tool_guardianlink'),
        'tool/guardianlink:manage'
    );
    $settings->add(new admin_setting_heading(
        'tool_guardianlink/generalheading',
        get_string('settings_general', 'tool_guardianlink'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/enableassistedmode',
        get_string('enableassistedmode', 'tool_guardianlink'),
        get_string('enableassistedmode_desc', 'tool_guardianlink'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/allowguardianproposetutor',
        get_string('allowguardianproposetutor', 'tool_guardianlink'),
        get_string('allowguardianproposetutor_desc', 'tool_guardianlink'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/requireadminapprovalfortutors',
        get_string('requireadminapprovalfortutors', 'tool_guardianlink'),
        get_string('requireadminapprovalfortutors_desc', 'tool_guardianlink'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/notifychildofaccess',
        get_string('notifychildofaccess', 'tool_guardianlink'),
        get_string('notifychildofaccess_desc', 'tool_guardianlink'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/hideparentcontactfromteachers',
        get_string('hideparentcontactfromteachers', 'tool_guardianlink'),
        get_string('hideparentcontactfromteachers_desc', 'tool_guardianlink'),
        1
    ));
    $settings->add(new admin_setting_configduration(
        'tool_guardianlink/maxdefaultdurationdays',
        get_string('maxdefaultdurationdays', 'tool_guardianlink'),
        get_string('maxdefaultdurationdays_desc', 'tool_guardianlink'),
        90 * DAYSECS,
        DAYSECS
    ));
    // Global default grant duration in days (0 = none). Course policy can tighten this; parents cannot exceed it.
    $settings->add(new admin_setting_configtext(
        'tool_guardianlink/defaultgrantdays',
        get_string('defaultgrantdays', 'tool_guardianlink'),
        get_string('defaultgrantdays_desc', 'tool_guardianlink'),
        90,
        PARAM_INT
    ));
    // Lead time (days) for the "grant due for review" reminder task.
    $settings->add(new admin_setting_configtext(
        'tool_guardianlink/reviewleaddays',
        get_string('reviewleaddays', 'tool_guardianlink'),
        get_string('reviewleaddays_desc', 'tool_guardianlink'),
        14,
        PARAM_INT
    ));
    // Re-validation cycle: relationships are scheduled for periodic re-validation.
    $settings->add(new admin_setting_configtext(
        'tool_guardianlink/revalidationperiod',
        get_string('revalidationperiod', 'tool_guardianlink'),
        get_string('revalidationperiod_desc', 'tool_guardianlink'),
        12,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configselect(
        'tool_guardianlink/revalidationunit',
        get_string('revalidationunit', 'tool_guardianlink'),
        get_string('revalidationunit_desc', 'tool_guardianlink'),
        'months',
        ['days' => get_string('days'), 'months' => get_string('months'), 'years' => get_string('years')]
    ));

    // Optional: auto-assign the dedicated authorised-adult role (login-as prohibited) when a grant activates.
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/autoassignrole',
        get_string('autoassignrole', 'tool_guardianlink'),
        get_string('autoassignrole_desc', 'tool_guardianlink'),
        0
    ));

    // Governed assisted access (parent/caregiver co-login as the learner).
    $settings->add(new admin_setting_heading(
        'tool_guardianlink/assistedheading',
        get_string('settings_assisted', 'tool_guardianlink'),
        get_string('settings_assisted_desc', 'tool_guardianlink')
    ));
    // Maximum assisted-session duration in minutes (0 = no cap).
    $settings->add(new admin_setting_configtext(
        'tool_guardianlink/assistedmaxminutes',
        get_string('assistedmaxminutes', 'tool_guardianlink'),
        get_string('assistedmaxminutes_desc', 'tool_guardianlink'),
        60,
        PARAM_INT
    ));
    // Anti-simultaneous-access: refuse assisted login while the learner is already online.
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/blockassistedwhenlearneronline',
        get_string('blockassistedwhenlearneronline', 'tool_guardianlink'),
        get_string('blockassistedwhenlearneronline_desc', 'tool_guardianlink'),
        1
    ));

    // Consent on behalf of a minor (GDPR Art. 8).
    $settings->add(new admin_setting_heading(
        'tool_guardianlink/consentheading',
        get_string('settings_consent', 'tool_guardianlink'),
        get_string('settings_consent_desc', 'tool_guardianlink')
    ));
    $settings->add(new admin_setting_configtextarea(
        'tool_guardianlink/consentpolicies',
        get_string('consentpolicies', 'tool_guardianlink'),
        get_string('consentpolicies_desc', 'tool_guardianlink'),
        '',
        PARAM_RAW
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/requireconsentforassist',
        get_string('requireconsentforassist', 'tool_guardianlink'),
        get_string('requireconsentforassist_desc', 'tool_guardianlink'),
        0
    ));

    // Independent (unsupervised) learner access — parent acknowledgement workflow.
    $settings->add(new admin_setting_heading(
        'tool_guardianlink/independentheading',
        get_string('settings_independent', 'tool_guardianlink'),
        get_string('settings_independent_desc', 'tool_guardianlink')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/allowindependentaccess',
        get_string('allowindependentaccess', 'tool_guardianlink'),
        get_string('allowindependentaccess_desc', 'tool_guardianlink'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/requiresupervision',
        get_string('requiresupervision', 'tool_guardianlink'),
        get_string('requiresupervision_desc', 'tool_guardianlink'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'tool_guardianlink/healthheading',
        get_string('settings_health', 'tool_guardianlink'),
        get_string('settings_health_desc', 'tool_guardianlink')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/enablehealthrecords',
        get_string('enablehealthrecords', 'tool_guardianlink'),
        get_string('enablehealthrecords_desc', 'tool_guardianlink'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_guardianlink/requirehealthapproval',
        get_string('requirehealthapproval', 'tool_guardianlink'),
        get_string('requirehealthapproval_desc', 'tool_guardianlink'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'tool_guardianlink/integrationheading',
        get_string('settings_integrations', 'tool_guardianlink'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'tool_guardianlink/trustedsources',
        get_string('trustedsources', 'tool_guardianlink'),
        get_string('trustedsources_desc', 'tool_guardianlink'),
        'SIS,ERP,HOSTEL,CARE',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'tool_guardianlink/auditretentionmonths',
        get_string('auditretentionmonths', 'tool_guardianlink'),
        get_string('auditretentionmonths_desc', 'tool_guardianlink'),
        36,
        PARAM_INT
    ));
    $ADMIN->add('tool_guardianlink_cat', $settings);
}
