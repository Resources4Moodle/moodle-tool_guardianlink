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
 * Upgrade steps for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * GuardianLink upgrade handler.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_tool_guardianlink_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 1.0.0 stable: no schema change from the 0.2.x alphas (the bulk-mail layer
    // reuses existing tables). Re-seed the default relationship types so any
    // alpha install that pre-dates a seeded type picks it up, and set the
    // savepoint so the stable version is recorded.
    if ($oldversion < 2026062700) {
        if ($dbman->table_exists('tool_guardianlink_reltype')) {
            \tool_guardianlink\local\relationship_service::ensure_default_relationship_types();
        }
        upgrade_plugin_savepoint(true, 2026062700, 'tool', 'guardianlink');
    }

    // 1.0.0 build 2: per-course duration/policy table + the dedicated authorised-adult
    // role (login-as prohibited). Durations now cascade Admin -> course -> parent.
    if ($oldversion < 2026062800) {
        $table = new xmldb_table('tool_guardianlink_courseconfig');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('maxgrantdays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('defaultgrantdays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('allowparentpropose', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('allowteacherproxy', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);
            $dbman->create_table($table);
        }
        \tool_guardianlink\local\setup::ensure_guardian_role();
        upgrade_plugin_savepoint(true, 2026062800, 'tool', 'guardianlink');
    }

    // 1.0.0 build 3: governed assisted access (parent/caregiver co-login as child).
    // Adds the per-course assisted toggle and re-provisions the role with course:view
    // (so core's login-as course check passes) + course-context assignability.
    if ($oldversion < 2026062900) {
        $table = new xmldb_table('tool_guardianlink_courseconfig');
        $field = new xmldb_field(
            'allowassistedaccess',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'allowteacherproxy'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        \tool_guardianlink\local\setup::ensure_guardian_role();
        upgrade_plugin_savepoint(true, 2026062900, 'tool', 'guardianlink');
    }

    // 1.0.0 build 7: email/message templates with placeholders.
    if ($oldversion < 2026063007) {
        $table = new xmldb_table('tool_guardianlink_template');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('triggerkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, 'manual');
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('bodyformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
            $table->add_index('triggerenabled', XMLDB_INDEX_NOTUNIQUE, ['triggerkey', 'enabled']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026063007, 'tool', 'guardianlink');
    }

    // 1.0.0 build 8: admin-editable access profiles.
    if ($oldversion < 2026063008) {
        $table = new xmldb_table('tool_guardianlink_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('shortname', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            foreach (
                ['allowoverview' => '1', 'allowgrades' => '0', 'allowcompletion' => '1',
                    'allowactivities' => '1', 'allowattendance' => '0', 'allowcalendar' => '1',
                    'allowteachercontact' => '1', 'allowmessaging' => '1', 'allowassisted' => '0',
                    'allowhealthsummary' => '0', 'allowtutormanagement' => '0',
                    'allowpolicyconsent' => '0'] as $f => $def
            ) {
                $table->add_field($f, XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, $def);
            }
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('systemmanaged', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
            $dbman->create_table($table);
        }
        \tool_guardianlink\local\relationship_service::ensure_default_profiles();
        upgrade_plugin_savepoint(true, 2026063008, 'tool', 'guardianlink');
    }

    // 1.0.0 build 10: custody chain (HTML validation notes + proof file attachments).
    if ($oldversion < 2026063010) {
        $table = new xmldb_table('tool_guardianlink_proof');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('relationshipid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('noteformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('relationshipid', XMLDB_INDEX_NOTUNIQUE, ['relationshipid', 'timecreated']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026063010, 'tool', 'guardianlink');
    }

    // 1.0.0 build 12: course-scoped teacher templates (courseid + ownerid; unique key per course).
    if ($oldversion < 2026063012) {
        $table = new xmldb_table('tool_guardianlink_template');

        $courseid = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enabled');
        if (!$dbman->field_exists($table, $courseid)) {
            $dbman->add_field($table, $courseid);
        }
        $ownerid = new xmldb_field('ownerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if (!$dbman->field_exists($table, $ownerid)) {
            $dbman->add_field($table, $ownerid);
        }

        // Replace the global-unique shortname index with one unique per (courseid, shortname).
        $oldindex = new xmldb_index('shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('coursesn', XMLDB_INDEX_UNIQUE, ['courseid', 'shortname']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        $courseidx = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $courseidx)) {
            $dbman->add_index($table, $courseidx);
        }
        upgrade_plugin_savepoint(true, 2026063012, 'tool', 'guardianlink');
    }

    // 1.0.0 build 15: independent (unsupervised) access acknowledgements.
    if ($oldversion < 2026063015) {
        // Per-course teacher switch: this course is safe for independent learner access.
        $table = new xmldb_table('tool_guardianlink_courseconfig');
        $field = new xmldb_field(
            'allowindependentaccess',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'allowassistedaccess'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Parent/guardian acknowledgements allowing a learner to access a course unsupervised.
        $indack = new xmldb_table('tool_guardianlink_indack');
        if (!$dbman->table_exists($indack)) {
            $indack->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $indack->add_field('guardianid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $indack->add_field('childid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $indack->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $indack->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'allowed');
            $indack->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $indack->add_field('acknowledgedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $indack->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $indack->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $indack->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $indack->add_index('uniqack', XMLDB_INDEX_UNIQUE, ['guardianid', 'childid', 'courseid']);
            $indack->add_index('childcourse', XMLDB_INDEX_NOTUNIQUE, ['childid', 'courseid']);
            $dbman->create_table($indack);
        }
        // Seed the new higher-education observer profile and relationship type for existing installs.
        \tool_guardianlink\local\relationship_service::ensure_default_profiles();
        \tool_guardianlink\local\relationship_service::ensure_default_relationship_types();
        upgrade_plugin_savepoint(true, 2026063015, 'tool', 'guardianlink');
    }

    // 1.0.0 build 16: rename all tables to the tool_guardianlink_ component prefix (Moodle plugin
    // directory requirement). Existing installs carry the historical local_gl_* tables; rename them
    // in place so no data is lost. Fresh installs already use the new names from install.xml.
    if ($oldversion < 2026063016) {
        $renames = [
            'local_gl_rel' => 'tool_guardianlink_rel',
            'local_gl_scope' => 'tool_guardianlink_scope',
            'local_gl_accesslog' => 'tool_guardianlink_accesslog',
            'local_gl_tutorreq' => 'tool_guardianlink_tutorreq',
            'local_gl_msgthread' => 'tool_guardianlink_msgthread',
            'local_gl_org' => 'tool_guardianlink_org',
            'local_gl_orgmember' => 'tool_guardianlink_orgmember',
            'local_gl_health' => 'tool_guardianlink_health',
            'local_gl_erpsync' => 'tool_guardianlink_erpsync',
            'local_gl_extmap' => 'tool_guardianlink_extmap',
            'local_gl_digestpref' => 'tool_guardianlink_digestpref',
            'local_gl_policy' => 'tool_guardianlink_policy',
            'local_gl_courseconfig' => 'tool_guardianlink_courseconfig',
            'local_gl_template' => 'tool_guardianlink_template',
            'local_gl_profile' => 'tool_guardianlink_profile',
            'local_gl_proof' => 'tool_guardianlink_proof',
            'local_gl_indack' => 'tool_guardianlink_indack',
            'local_gl_reltype' => 'tool_guardianlink_reltype',
        ];
        foreach ($renames as $oldname => $newname) {
            if ($dbman->table_exists($oldname) && !$dbman->table_exists($newname)) {
                $dbman->rename_table(new xmldb_table($oldname), $newname);
            }
        }
        upgrade_plugin_savepoint(true, 2026063016, 'tool', 'guardianlink');
    }

    // 1.0.1: enforce uniqueness for external source identities and scope identities.
    if ($oldversion < 2026063017) {
        // Empty external refs become NULL so the unique index does not collide across manual rows.
        $DB->execute("UPDATE {tool_guardianlink_rel} SET sourcecode = NULL WHERE sourcecode = ?", ['']);
        $DB->execute("UPDATE {tool_guardianlink_rel} SET externalid = NULL WHERE externalid = ?", ['']);

        // Repair any pre-existing duplicate external identities (keep the lowest id; null the rest).
        $dupes = $DB->get_records_sql(
            "SELECT MIN(id) AS keepid, sourcecode, externalid
               FROM {tool_guardianlink_rel}
              WHERE sourcecode IS NOT NULL AND externalid IS NOT NULL
           GROUP BY sourcecode, externalid
             HAVING COUNT(*) > 1"
        );
        foreach ($dupes as $dupe) {
            $DB->execute(
                "UPDATE {tool_guardianlink_rel} SET sourcecode = NULL, externalid = NULL "
                    . "WHERE sourcecode = ? AND externalid = ? AND id <> ?",
                [$dupe->sourcecode, $dupe->externalid, $dupe->keepid]
            );
        }

        // Repair duplicate scope identities (keep the lowest id; delete the rest).
        $scopedupes = $DB->get_records_sql(
            "SELECT MIN(id) AS keepid, relationshipid, scopekind, courseid, categoryid
               FROM {tool_guardianlink_scope}
           GROUP BY relationshipid, scopekind, courseid, categoryid
             HAVING COUNT(*) > 1"
        );
        foreach ($scopedupes as $dupe) {
            $DB->execute(
                "DELETE FROM {tool_guardianlink_scope} "
                    . "WHERE relationshipid = ? AND scopekind = ? AND courseid = ? AND categoryid = ? AND id <> ?",
                [$dupe->relationshipid, $dupe->scopekind, $dupe->courseid, $dupe->categoryid, $dupe->keepid]
            );
        }

        $reltable = new xmldb_table('tool_guardianlink_rel');
        $oldexternal = new xmldb_index('external', XMLDB_INDEX_NOTUNIQUE, ['sourcecode', 'externalid']);
        if ($dbman->index_exists($reltable, $oldexternal)) {
            $dbman->drop_index($reltable, $oldexternal);
        }
        $newexternal = new xmldb_index('external', XMLDB_INDEX_UNIQUE, ['sourcecode', 'externalid']);
        if (!$dbman->index_exists($reltable, $newexternal)) {
            $dbman->add_index($reltable, $newexternal);
        }

        $scopetable = new xmldb_table('tool_guardianlink_scope');
        $oldrelcourse = new xmldb_index('relcourse', XMLDB_INDEX_NOTUNIQUE, ['relationshipid', 'courseid']);
        if ($dbman->index_exists($scopetable, $oldrelcourse)) {
            $dbman->drop_index($scopetable, $oldrelcourse);
        }
        $scopeidentity = new xmldb_index(
            'relscopeidentity',
            XMLDB_INDEX_UNIQUE,
            ['relationshipid', 'scopekind', 'courseid', 'categoryid']
        );
        if (!$dbman->index_exists($scopetable, $scopeidentity)) {
            $dbman->add_index($scopetable, $scopeidentity);
        }
        upgrade_plugin_savepoint(true, 2026063017, 'tool', 'guardianlink');
    }

    return true;
}
