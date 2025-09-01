<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/../../mod/bacs/utils.php');
require_once(dirname(__FILE__).'/../../mod/bacs/submit_verdicts.php');

function is_admin() {
    global $DB, $USER;

    $systemcontext = context_system::instance();
    return has_capability('moodle/course:delete', $systemcontext);
}

function is_teacher($context) {
    global $USER, $DB;

    /*
    $teacher_role = $DB->get_record('role', array('shortname' => 'editingteacher'));
    $users = get_role_users($teacher_role->id, $context);

    foreach ($users as $cur_user) {
        if ($cur_user->id == $USER->id) return true;
    }
    return false;
    //*/

    return has_capability('mod/bacs:addinstance', $context);
}

function is_student($author, $course) {
    global $DB;

    $context_instance = context_course::instance($course->id);
    $student_role = $DB->get_record('role', array('shortname' => 'student'));
    $students = get_role_users($student_role->id, $context_instance);

    foreach ($students as $cur_user) {
        if ($cur_user->id == $author->id) return true;
    }
    return false;
}

function my_courses() {
    global $USER, $DB;

    $all_courses = $DB->get_records('course');
    $my_courses = array();
    foreach ($all_courses as $cur_course) {
        $context_instance = context_course::instance($cur_course->id);
        //print $cur_course->fullname . ' - ' . (is_teacher($context_instance)?'TRUE':'FALSE') . '<br>';
        if (is_teacher($context_instance) && is_enrolled($context_instance, $USER, "", true)) $my_courses[] = $cur_course->id;
    }

    return $my_courses;
}

function all_courses() {
    global $USER, $DB;

    $all_courses = $DB->get_records('course');
    $my_courses = array();
    foreach ($all_courses as $cur_course) {
        $my_courses[] = $cur_course->id;
    }

    return $my_courses;
}


function print_nav_menu() {
    print '<script type="text/javascript">
        function teacher_monitor_open_tab(tab_name){
            "use strict";

            var activity_content = document.getElementById("tm_tab_activity_content");
            var activity_header = document.getElementById("tm_tab_activity_header");
            var enrols_content = document.getElementById("tm_tab_enrols_content");
            var enrols_header = document.getElementById("tm_tab_enrols_header");
            var attendance_content = document.getElementById("tm_tab_attendance_content");
            var attendance_header = document.getElementById("tm_tab_attendance_header");

            
            if (tab_name == "activity") {
                activity_header.classList.add("active");
                activity_content.style.display = "block";
            } else {
                activity_header.classList.remove("active");
                activity_content.style.display = "none";
            }

            if (tab_name == "enrols") {
                enrols_header.classList.add("active");
                enrols_content.style.display = "block";
            } else {
                enrols_header.classList.remove("active");
                enrols_content.style.display = "none";
            }

            if (tab_name == "attendance") {
                attendance_header.classList.add("active");
                attendance_content.style.display = "block";
            } else {
                attendance_header.classList.remove("active");
                attendance_content.style.display = "none";
            }
        }
        </script>';

    print '<ul class="nav nav-tabs">';

    $activity_string = "'activity'";
    $enrols_string = "'enrols'";
    $attendance_string = "'attendance'";

    print '<li class="nav-item" style="cursor: pointer;"
        onclick="teacher_monitor_open_tab('.$activity_string.')">
        <a id="tm_tab_activity_header" class="nav-link"> 
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
            </svg>
            Активность студентов
        </a>
    </li>';

    print '<li class="nav-item" style="cursor: pointer;"
        onclick="teacher_monitor_open_tab('.$enrols_string.')">
        <a id="tm_tab_enrols_header" class="nav-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
            </svg> 
            Заявки на курсы'.
            '<span id="tm_tab_enrols_header_ainfo" style="color: red;"></span>'.
        '</a>
    </li>';

    $pluginmanager = \core_plugin_manager::instance();
    $block_plugins = $pluginmanager->get_present_plugins('block');
    $is_bacs_attendance_list_presented = isset($block_plugins['bacs_attendance_list']);

    if($is_bacs_attendance_list_presented) {
        print '<li class="nav-item" style="cursor: pointer;">
            <a class="nav-link" href="/blocks/bacs_attendance_list/index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-check" viewBox="0 0 16 16">
                    <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                </svg>
                Журнал посещаемости' .
            '</a>
        </li>';

    } else {
        print '<li class="nav-item" style="cursor: pointer;"
        onclick="teacher_monitor_open_tab('.$attendance_string.')">
        <a id="tm_tab_attendance_header" class="nav-link"> 
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-check" viewBox="0 0 16 16">
                <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
            </svg>
            Журнал посещаемости
        </a>
        </li>';
    }

    print '<li class="nav-item" style="cursor: pointer;">
        <a class="nav-link" href="/blocks/teacher_control/multistandings.php?tab=settings">
            <svg class="bi bi-flag-fill" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" d="M3.5 1a.5.5 0 0 1 .5.5v13a.5.5 0 0 1-1 0v-13a.5.5 0 0 1 .5-.5z"/>
                <path fill-rule="evenodd" d="M3.762 2.558C4.735 1.909 5.348 1.5 6.5 1.5c.653 0 1.139.325 1.495.562l.032.022c.391.26.646.416.973.416.168 0 .356-.042.587-.126a8.89 8.89 0 0 0 .593-.25c.058-.027.117-.053.18-.08.57-.255 1.278-.544 2.14-.544a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-.5.5c-.638 0-1.18.21-1.734.457l-.159.07c-.22.1-.453.205-.678.287A2.719 2.719 0 0 1 9 9.5c-.653 0-1.139-.325-1.495-.562l-.032-.022c-.391-.26-.646-.416-.973-.416-.833 0-1.218.246-2.223.916A.5.5 0 0 1 3.5 9V3a.5.5 0 0 1 .223-.416l.04-.026z"/>
            </svg> 
            Составной монитор' .
        '</a>
    </li>';
    print '</ul>';
}

function print_students_activity() {
    global $USER, $DB, $now;

    // filter form
    $date_from = optional_param('from', '', PARAM_TEXT);
    $date_to = optional_param('to', '', PARAM_TEXT);

    if (($timestamp_from = strtotime($date_from)) === false) $timestamp_from = strtotime(date("Y-m-d"));
    if (($timestamp_to = strtotime($date_to)) === false) $timestamp_to = $timestamp_from;

    $string_from = date("Y-m-d", $timestamp_from);
    $string_to = date("Y-m-d", $timestamp_to);

    print "<form action='/blocks/teacher_control/teacher_monitor.php' method='get' style='text-align: center; margin-bottom: 15px;'>
        <b>От</b>
        <input type='date' name='from' value='$string_from'>
        <b>До</b>
        <input type='date' name='to' value='$string_to'>
        <input type='submit' value='Отфильтровать'>
    </form>";

    if (is_admin()) {
        $my_courses = all_courses();
    } else {
        $my_courses = my_courses();
    }

    $my_courses[] = -1; // empty mysql set case
    $my_courses_string = '(' . implode(',', $my_courses) . ')';

    $my_contests = array_map(
        function($x){return $x->id;},
        $DB->get_records_select('bacs', "(course IN $my_courses_string)")
    );

    $my_contests[] = -1; // empty mysql set case
    $my_contests_string = '(' . implode(',', $my_contests) . ')';

    $submit_verdict_pending = VERDICT_PENDING;
    $submit_verdict_running = VERDICT_RUNNING;

    $my_submits = $DB->get_records_select(
        'bacs_submits',
        "(contest_id IN $my_contests_string)" .
        //" AND (result_id != $submit_verdict_pending)" .
        //" AND (result_id != $submit_verdict_running)" .
        " AND (submit_time >= $timestamp_from)" .
        " AND (submit_time < ($timestamp_to + 86400))",
        array($params = null),
        'submit_time DESC',
        '*',
        0,
        1000
    );

    print '<style>
        .cwidetable {
            width: 100%;
            margin-bottom: 20px;
        }
        .cwidetable td,
        .cwidetable th {
            padding: 8px;
            line-height: 20px;
            text-align: left;
            vertical-align: top;
            border-top: 1px solid #ededed;
        }
        .cwidetable pre {
            padding: 3px;
            word-break: normal;
        }
        .verdict-accepted {
            background-color: #efe;
        }
        .verdict-failed {
            background-color: #fee;
        }
        .verdict-none {
            background-color: #e8e8ff;
        }
    </style>';

    print '
    <table class="cwidetable">
        <thead><tr style="font-weight: bold">
            <td>N</td>
            <td>Курс</td>
            <td>Контест</td>
            <td>Задача</td>
            <td>Автор</td>
            <td>Язык</td>
            <td>Вердикт</td>
            <td>Баллы</td>
            <td>Дата/время</td>
        </tr></thead>
        <tbody>';

    $row_number = 0;
    foreach ($my_submits as $submit) {
        $cur_contest = $DB->get_record('bacs', array('id' => $submit->contest_id));
        $cur_course = $DB->get_record('course', array('id' => $cur_contest->course));
        $cur_task = $DB->get_record('bacs_tasks', array('task_id' => $submit->task_id));
        $cur_lang = $DB->get_record('bacs_langs', array('lang_id' => $submit->lang_id));
        $cur_author = $DB->get_record('user', array('id' => $submit->user_id));

        if (!is_student($cur_author, $cur_course)) continue;

        $verdict_class = 'failed';
        if ($submit->result_id == VERDICT_ACCEPTED) $verdict_class = 'accepted';
        else if ($submit->result_id == VERDICT_PENDING) $verdict_class = 'none';
        else if ($submit->result_id == VERDICT_RUNNING) $verdict_class = 'none';

        $cur_verdict_string = get_string("submit_verdict_$submit->result_id", 'mod_bacs');
        $cur_datetime = userdate($submit->submit_time,"%d %B %Y (%A) %H:%M:%S");

        $submission_link = "/mod/bacs/results.php?b=$cur_contest->id&user_id=$cur_author->id&submission_id=$submit->id";

        // print
        $row_number++;
        print "<tr class='verdict-$verdict_class'>
            <td>$row_number</td>
            <td><a target='_blank' href='/course/view.php?id=$cur_course->id'>$cur_course->shortname</a></td>
            <td><a target='_blank' href='/mod/bacs/view.php?b=$cur_contest->id'>$cur_contest->name</a></td>
            <td><a target='_blank' href='$cur_task->statement_url'>$cur_task->name</a></td>
            <td><a target='_blank' href='/user/profile.php?id=$cur_author->id'>$cur_author->firstname $cur_author->lastname</a></td>
            <td>$cur_lang->name</td>
            <td><a target='_blank' href='$submission_link'>$cur_verdict_string</a></td>
            <td><a target='_blank' href='$submission_link'>$submit->points</a></td>
            <td>$cur_datetime</td>
        </tr>";
    }

    print '</tbody></table>';

}

function print_pending_enrols() {
    global $USER, $DB, $pending_enrols_number;

    try {
        $enrolData = $DB->get_records('enrol_apply_applicationinfo');
    } catch(dml_exception $e) {
        $enrolData = [];
    }


    $userenrolmentids = array_map(
        function($x){return $x->userenrolmentid;},
        $enrolData
    );
    $userenrolmentids[] = -1; // empty mysql set case
    $userenrolmentids_string = '('. implode(',', $userenrolmentids) . ')';

    $enrolments = $DB->get_records_select('user_enrolments', "(id IN $userenrolmentids_string) AND (status != 0)");

    if (is_admin()) {
        $my_courses = all_courses();
    } else {
        $my_courses = my_courses();
    }

    $my_courses[] = -1; // empty mysql set case
    $my_courses_string = '(' . implode(',', $my_courses) . ')';
    $my_enrols = $DB->get_records_select('enrol', "(enrol = 'apply') AND (courseid IN $my_courses_string)");
    $applications_per_enrol = array();
    foreach ($my_enrols as $cur_enrol) {
        $applications_per_enrol[$cur_enrol->id] = 0;
    }

    $pending_enrols_number = 0;
    foreach ($enrolments as $enrolment) {
        $cur_enrol = $DB->get_record('enrol', array('id' => $enrolment->enrolid));
	$cur_course = $DB->get_record('course', array('id' => $cur_enrol->courseid));

        if (!in_array($cur_course->id, $my_courses)) continue;

        $pending_enrols_number++;
        $applications_per_enrol[$cur_enrol->id]++;
    }

    $enrols_with_applications = array();
    $enrols_without_applications = array();
    foreach ($my_enrols as $cur_enrol){
        if ($applications_per_enrol[$cur_enrol->id] > 0) {
            $enrols_with_applications[] = $cur_enrol->id;
        } else {
            $enrols_without_applications[] = $cur_enrol->id;
        }
    }
    print '
    <table class="cwidetable">
        <thead><tr style="font-weight: bold">
            <td>Курс</td>
            <td>Заявки</td>
            <td>Действия</td>
        </tr></thead>
        <tbody>';
    foreach (array_merge($enrols_with_applications, $enrols_without_applications) as $cur_enrol_id) {
        $cur_enrol = $DB->get_record('enrol', array('id' => $cur_enrol_id));
        $cur_course = $DB->get_record('course', array('id' => $cur_enrol->courseid));

        if (!in_array($cur_course->id, $my_courses)) continue;

        $cur_css = ($applications_per_enrol[$cur_enrol->id] > 0 ? "background-color: #eef;" : "");
        print "<tr style='$cur_css'>
            <td><a href='/course/view.php?id=$cur_course->id'>$cur_course->shortname</a></td>
            <td>".$applications_per_enrol[$cur_enrol->id]."</td>
            <td><a href='/enrol/apply/manage.php?id=$cur_enrol->id'>
                <button class='btn btn-info'>Рассмотреть</button>
            </a></td>
        </tr>";
    }

    print '</tbody></table>';
}

function print_plugin($plugin_name, $plugin_fn) {
    $pluginmanager = \core_plugin_manager::instance();
    $parsed_plugin_name_str = explode("_", $plugin_name);
    $name = implode("_", array_slice($parsed_plugin_name_str, 1));
    $plugins_by_type = $pluginmanager->get_present_plugins($parsed_plugin_name_str[0]);
    $is_available = isset($plugins_by_type[$name]);

    if($is_available) {
        $plugin_fn();
    } else {
        print "<div style=\"padding-left:30px;padding-top: 30px;margin-bottom: 30px\">";
        print "<h1>" . $plugin_name . " " . get_string("no_plugin_installed", "block_teacher_control") . "</h1>";
        print "<p>" . get_string("install_plugin_description", "block_teacher_control") . "</p>";
        print "</div>";
    }
}



/// Print the page header
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/teacher_control/teacher_monitor.php', array());

require_login();
$PAGE->set_title('Монитор учителя');
$PAGE->set_heading('Монитор учителя');

$PAGE->requires->css('/mod/bacs/main.css', true);

// Output starts here
echo $OUTPUT->header();

$PAGE->navbar->ignore_active();
$PAGE->navbar->add('preview', new moodle_url('/a/link/if/you/want/one.php'));
$PAGE->navbar->add('name of thing', new moodle_url('/a/link/if/you/want/one.php'));

$now = time();

$menu_tab = optional_param('tab', 'activity', PARAM_TEXT);
if ($menu_tab != 'enrols') {
    $menu_tab = 'activity';
}

print_nav_menu();

print '<div id="tm_tab_activity_content">';
print_plugin('mod_bacs', function() {print_students_activity();});
print '</div>';

print '<div id="tm_tab_enrols_content">';
$pending_enrols_number = 0;
print_plugin('enrol_apply', function() {print_pending_enrols();});

print '</div>';

print '<div id="tm_tab_attendance_content">';
print_plugin('block_bacs_attendance_list', function() {});

print '</div>';

print '<script type="text/javascript">
    teacher_monitor_open_tab("'.$menu_tab.'");
</script>';

if (is_int($pending_enrols_number)){
    print '<script type="text/javascript">
        (function(){
            "use strict";

            var enrols_info = document.getElementById("tm_tab_enrols_header_ainfo");
            var pending_enrols_number = '.$pending_enrols_number.';
            if (pending_enrols_number > 0) {
                enrols_info.innerHTML = " (" + pending_enrols_number + ")";
            } else {
                enrols_info.innerHTML = "";
            }
        })();
    </script>';
}

echo $OUTPUT->footer();
