<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Print an overview of groupings & group membership
 *
 * @author  Valery Fremaux valery.fremaux@institut-iperia.fr
 * @version 0.0.1
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package groups
 */

require('../../config.php');
require_once($CFG->dirroot.'/local/groupcopy/groupoperationsform.php');
require_once($CFG->dirroot.'/group/lib.php');

$courseid = required_param('id', PARAM_INT);

$returnurl = new moodle_url('/group/index.php', array('id' => $courseid));
$thisurl = new moodle_url('/local/groupcopy/operations_perform.php', array('id' => $courseid));

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('coursemisconf');
}

// Make sure that the user has permissions to manage groups.
require_course_login($course, true);

$context = context_course::instance($courseid);
require_capability('moodle/course:managegroups', $context);

$strgroups           = get_string('groups');
$strparticipants     = get_string('participants');
$stroverview         = get_string('overview', 'group');
$strgrouping         = get_string('grouping', 'group');
$strgroup            = get_string('group', 'group');
$strnotingrouping    = get_string('notingrouping', 'group');
$strfiltergroups     = get_string('filtergroups', 'group');
$strnogroups         = get_string('nogroups', 'group');
$strdescription      = get_string('description');

$syscontext = context_system::instance();

// If we're not a course creator , we can only import from our own courses.
if (has_capability('moodle/course:create', $syscontext)) {
    $creator = true;
}

// Print the page and form.
$PAGE->set_url($thisurl);
$PAGE->set_context($context);
$PAGE->set_heading(get_string('pluginname', 'local_groupcopy'));
$PAGE->set_title(get_string('pluginname', 'local_groupcopy'));
$PAGE->navbar->add($strparticipants, new moodle_url('/user/index.php', array('id' => $courseid)));
$PAGE->navbar->add(get_string('pluginname', 'local_groupcopy'));
$PAGE->set_pagelayout('admin');

/*
 * get all course contexts the user has managegroups capability on
 * those courses are legitimate as source for group structure copying.
 * @see
 */

$params = array('options' => null, 'courseid' => $course->id, 'text' => get_string('coursestaught', 'local_groupcopy'));
$mform1 = new Group_Operations_Setup1_Form($thisurl, $params);

if ($mform1->is_cancelled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('copyallgroupstructure', 'local_groupcopy'));
    echo $OUTPUT->print_box(get_string('cancelledmsg', 'local_groupcopy'));
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $courseid)));
    echo $OUTPUT->footer();
    exit;
}

$fromcourseid = required_param('fromcourse', PARAM_INT);
$fromcourse = $DB->get_record('course', array('id' => $fromcourseid));

$groups = groups_get_all_groups($fromcourse->id);
$context = context_course::instance($fromcourse->id);
$systemcontext = context_system::instance();
$roles = get_roles_on_exact_context($context);
$fixedroles = role_fix_names($roles, $systemcontext, ROLENAME_ORIGINAL);

$params = array('roles' => $fixedroles,  'groups' => $groups, 'courseid' => $course->id, 'fromcourseid' => $fromcourse->id);
$mform2 = new Group_Operations_Setup2_Form($thisurl, $params);

if ($mform2->is_cancelled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('copyallgroupstructure', 'local_groupcopy'));
    echo $OUTPUT->print_box(get_string('cancelledmsg', 'local_groupcopy'));
    echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$courseid);
    echo $OUTPUT->footer();
    exit;
} else if ($data = $mform2->get_data()) {

    echo $OUTPUT->header();

    echo $OUTPUT->heading(get_string('copyallgroupstructure', 'local_groupcopy'));
    echo $OUTPUT->heading(get_string('groupsfromcourse', 'local_groupcopy', $fromcourse->fullname.' ('.$fromcourse->shortname.')'));

    echo $OUTPUT->box_start();
    echo '<pre>';

    // Get groups and groupings from sourcecourse in groupid list.
    $formkeys = array_keys($_REQUEST);
    $groupkeys = preg_grep('/^groupids.*/', $formkeys);
    $rolekeys = preg_grep('/^roleids.*/', $formkeys);

    foreach ($groupkeys as $akey) {
        $data->groups[] = str_replace('groupids', '', $akey);
    }

    foreach ($rolekeys as $akey) {
        $data->roles[] = str_replace('roleids', '', $akey);
    }

    // Do we need deleting all users assignation to course, unless editing teachers.
    $clearall = optional_param('deleteallusersbeforecopy', false, PARAM_INT);
    if ($clearall) {
        if ($groups = $DB->get_records('groups', array('courseid' => $courseid))) {
            foreach ($groups as $g) {
                groups_delete_group($g->id);
            }
        }

        // Getting context for course.
        $currentcontext = context_course::instance($courseid);
        if ($allassigns = $DB->get_records('role_assignments', array('contextid' => $currentcontext->id))) {
            foreach ($allassigns as $asg) {
                if (!has_capability('moodle/course:manageactivities', $currentcontext, $asg->userid)) {
                    role_unassign($asg->roleid, $asg->userid, $currentcontext->id);
                    echo "Removing enrolled user $asg->userid \n";
                }
            }
        }
    }

    // Create missing groupings in course.

    $backupids = new StdClass;

    // Copy groups that are not here.
    $backupids->allgroupsusers = array();
    $backupids->groupings = array();
    $backupids->groups = array();

    if ($data->groups) {
        foreach ($data->groups as $groupid) {
            $group = $DB->get_record('groups', array('id' => $groupid));
            if (!$groupintheway = $DB->get_record('groups', array('name' => $group->name, 'courseid' => $data->id))) {
                unset($group->id);
                $group->courseid = $data->id;
                $group->timecreated = time();
                $group->timemodified = time();
                $newid = $DB->insert_record('groups', $group);
                mtrace(get_string('copyinggroup', 'local_groupcopy', $group->name));
            } else {
                mtrace(get_string('foundgroup', 'local_groupcopy', $group->name));
                $newid = $groupintheway->id;
            }
            $backupids->groups[$groupid] = $newid;
            $groupusers = $DB->get_records('groups_members', array('groupid' => $groupid));
            $backupids->newgroupsusers[$newid] = $groupusers;
            foreach ($groupusers as $gu) {
                $backupids->allgroupsusers[] = $gu->userid;
            }

            // We copy inexistant groupings from source attached to that group even if group was not duplicated.
            if (!empty($data->copygroupings)) {
                if ($groupgroupings = $DB->get_records('groupings_groups', array('groupid' => $groupid))) {
                    foreach ($groupgroupings as $groupingbind) {
                        $grouping = $DB->get_record('groupings', array('id' => $groupingbind->groupingid));
                        $oldgroupingid = $grouping->id;
                        $params = array('name' => $grouping->name, 'courseid' => $data->id);
                        if (!$groupingintheway = $DB->get_record('groupings', $params)) {
                            unset($grouping->id);
                            $grouping->courseid = $data->id;
                            $grouping->timecreated = time();
                            $grouping->timemodified = time();
                            $newid = $DB->insert_record('groupings', $grouping);
                            mtrace(get_string('copyinggrouping', 'local_groupcopy', $grouping->name));
                        } else {
                            $newid = $groupingintheway->id;
                            mtrace(get_string('foundgrouping', 'local_groupcopy', $grouping->name));
                        }
                        $backupids->groupings[$oldgroupingid] = $newid;
                    }
                }
            }
        }

        /*
         * theoretically, all old group/groupings bindings should have been duplicated to
         * combination of newgroup/newgrouping/oldgroup/oldgrouping in target course.
         */

        if (!empty($backupids->groups)) {

            // Bind all possible groupings looking at source structure.
            if ($groupbinds = $DB->get_records_list('groupings_groups', 'groupid', array_keys($backupids->groups))) {
                foreach ($groupbinds as $bind) {
                    // Discard non relevant pairs... if any.
                    if (!array_key_exists($bind->groupingid, $backupids->groupings) ||
                            !array_key_exists($bind->groupid, $backupids->groups)) {
                        continue;
                    }
                    unset($bind->id);
                    $bind->groupingid = $backupids->groupings[$bind->groupingid];
                    $bind->groupid = $backupids->groups[$bind->groupid];
                    $bind->timeadded = time();
                    $newid = $DB->insert_record('groupings_groups', $bind);
                    mtrace(get_string('rebindgrouping', 'local_groupcopy', $bind->groupingid));
                }
            }
        }
    } else {
        mtrace(get_string('nogroupstructuretocopy', 'local_groupcopy'));
    }

    // If some roles to copy and we are not a metacourse.
    if (!empty($data->roles)) {
        /*
         * for each role, get original enrolments and copy them to target context
         * hidden status will be kept
         */
        $oldcontext = context_course::instance($fromcourseid);
        $nonmetacourserolesarr = get_config('enrol_meta', 'nosyncroleids');
        foreach ($data->roles as $rid) {
            // Foreach enrolment copy enrolment.
            $role = $DB->get_record('role', array('id' => $rid));

            // Avoid synced by metacourse roles.
            // @TODO : reexamine against moodle 2 metacourse enrolements.

            $params = array('enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED);
            $enrol = $DB->get_record('enrol', $params);
            $enrolplugin = enrol_get_plugin('manual');

            if ($incominguserassigns = get_users_from_role_on_context($role, $oldcontext)) {
                foreach ($incominguserassigns as $assign) {
                    // Only report users in groups that were copied.
                    if (in_array($assign->userid, $backupids->allgroupsusers)) {
                        // Check if user is really enrolled or simply has extra roles.
                        if (is_enrolled($oldcontext, $assign->userid)) {
                            $enrolplugin->enrol_user($enrol, $assign->userid, $role->id, time(), 0, ENROL_USER_ACTIVE);
                            mtrace(get_string('enroluser', 'local_groupcopy', $assign->userid));
                        } else {
                            role_assign($role->id, $assign->userid, $context->id, '', 0, time());
                            $a = new SdClass;
                            $a->role = $role->shortname;
                            $a->userid = $assign->userid;
                            mtrace(get_string('roleassignuser', 'local_groupcopy', $a));
                        }
                    }
                }
            }
        }
    } else {
        mtrace(get_string('noroleschoosedtosync', 'local_groupcopy'));
    }

    /*
     * finally get all group to user assignation from really copied
     * as users might have already old assignment in this course, we need check again
     * for each and not only the copied ones.
     */
    if (!empty($backupids->newgroupsusers)) {
        foreach ($backupids->newgroupsusers as $newgroup => $usertable) {
            foreach ($usertable as $ua) {
                $groupmember = new StdClass();
                $groupmember->userid = $ua->userid;
                $groupmember->groupid = $newgroup;
                $groupmember->timeadded = time();
                $groupmember->itemid = 0;

                $a = new StdClass();
                $a->newgroup = $newgroup;
                $a->username = $DB->get_field('user', 'username', array('id' => $ua->userid));
                if (!$oldassign = $DB->get_record('groups_members', array('userid' => $ua->userid, 'groupid' => $newgroup))) {
                    $DB->insert_record('groups_members', $groupmember);
                    mtrace(get_string('groupaddmember', 'local_groupcopy', $a));
                } else {
                    mtrace(get_string('memberalreadyingroup', 'local_groupcopy', $a));
                }
            }
        }
    } else {
        mtrace(get_string('nogroupuserbindingsfound', 'local_groupcopy'));
    }

    echo '</pre>';
    echo $OUTPUT->box_end();

    // Invalidate the course groups cache seeing as we've changed it.
    cache_helper::invalidate_by_definition('core', 'groupdata', array(), array($courseid));

    echo $OUTPUT->continue_button(new moodle_url('/group/index.php', array('id' => $courseid)));
    echo $OUTPUT->footer();
    die;
} else {
    echo $OUTPUT->header();

    echo $OUTPUT->heading(get_string('copyallgroupstructure', 'local_groupcopy'));
    echo $OUTPUT->heading(get_string('groupsfromcourse', 'local_groupcopy', $fromcourse->fullname.' ('.$fromcourse->shortname.')'));
}

$mform2->display();

echo $OUTPUT->footer();
