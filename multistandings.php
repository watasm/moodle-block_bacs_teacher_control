<?php

/**
 *
 * @package    mod
 * @subpackage bacs
 */

function prepare_params() {
    global $DB, $pattern, $ids, $ids_list, $ids_set, $courseids, $courseids_list, $courseids_set, $use_compression;

    $ids_list = optional_param('ids', '', PARAM_TEXT);
    $ids = explode('_', $ids_list);
    if ($ids_list == '') $ids = array();

    $courseids_list = optional_param('courseids', '', PARAM_TEXT);
    $courseids = explode('_', $courseids_list);
    if ($courseids_list == '') $courseids = array();

    $use_compression = optional_param('compress', true, PARAM_BOOL);

    $pattern = optional_param('pattern', '', PARAM_TEXT);

    $ids_set = array();
    foreach ($ids as $cur_id) {
        $ids_set[$cur_id] = true;
    }

    $courseids_set = array();
    foreach ($courseids as $cur_id) {
        $courseids_set[$cur_id] = true;
    }

    // unpack whole courses
    foreach ($courseids as $cur_courseid) {
        $cur_course_contests = $DB->get_records('bacs', array('course' => $cur_courseid), '', 'id');
    
        foreach ($cur_course_contests as $cur_contest) {
            $cur_cm = get_coursemodule_from_instance('bacs', $cur_contest->id, $cur_courseid, false, MUST_EXIST);
    
            if ($ids_set[$cur_cm->id]) continue;

            $ids[] = $cur_cm->id;
            $ids_set[$cur_cm->id] = true;
        }
    }

}

function print_nav_menu() {
    print '<script type="text/javascript">
        function multistandings_open_tab(tab_name){
            "use strict";

            var view_content = document.getElementById("ms_tab_view_content");
            var view_header  = document.getElementById("ms_tab_view_header");
            var settings_content = document.getElementById("ms_tab_settings_content");
            var settings_header  = document.getElementById("ms_tab_settings_header");

            if (tab_name == "view") {
                view_header.classList.add("active");
                view_content.style.display = "block";
            } else {
                view_header.classList.remove("active");
                view_content.style.display = "none";
            }

            if (tab_name == "settings") {
                settings_header.classList.add("active");
                settings_content.style.display = "block";
            } else {
                settings_header.classList.remove("active");
                settings_content.style.display = "none";
            }
        }
        </script>';

    print '<ul class="nav nav-tabs">';

    $view_string     = "'view'";
    $settings_string = "'settings'";

    print '<li id="ms_tab_view_header" style="cursor: pointer;" class="nav-item"
        onclick="multistandings_open_tab('.$view_string.')">
        <a class="nav-link"><i class="icon-flag"></i> Монитор</a>
    </li>';

    print '<li id="ms_tab_settings_header" style="cursor: pointer;" class="nav-item"
        onclick="multistandings_open_tab('.$settings_string.')">
        <a class="nav-link"><i class="icon-cog"></i> Настройки содержимого</a>
    </li>';

    print '</ul>';
}

function print_view() {
    global $DB, $ids, $ids_list, $courseids, $courseids_list, $use_compression, $student;
    
    $submits = '[].concat(';
    $contest_number = 0;
    foreach ($ids as $cur_id) {
        $contest_number++;
    
        $CM         = get_coursemodule_from_id('bacs', $cur_id, 0, false, MUST_EXIST);
        $COURSE     = $DB->get_record('course', array('id' => $CM->course), '*', MUST_EXIST);
        $BACS       = $DB->get_record('bacs', array('id' => $CM->instance), '*', MUST_EXIST);
    
        $cur_submits = $BACS->standings;
        
        if (is_null($cur_submits) || $cur_submits == '') {
            $cur_submits = '[]';
        }
    
        if ($use_compression) {
            $modifier = "function(x){
                x.cross_task_id = x.task_id;
                x.course_module_id = $cur_id;
                return x;
            }";
        } else {
            $modifier = "function(x){
                x.cross_task_id = x.task_id + '_' + $contest_number;
                x.course_module_id = $cur_id;
                return x;
            }";
        }   
    
        $submits .= "$cur_submits.map($modifier),";
    }
    $submits .= '[])';
    
    $role = $DB->get_record('role', array('shortname' => 'student'));
    
    $students_id_to_course_id = array();
    foreach ($ids as $cur_id) {
        $CM         = get_coursemodule_from_id('bacs', $cur_id, 0, false, MUST_EXIST);
        $COURSE     = $DB->get_record('course', array('id' => $CM->course), '*', MUST_EXIST);
        $BACS       = $DB->get_record('bacs', array('id' => $CM->instance), '*', MUST_EXIST);
    
        $context_instance = context_course::instance($COURSE->id);
        
        $coursestudents = get_role_users($role->id, $context_instance);
        foreach ($coursestudents as $cur_student) {
            if (is_null($students_id_to_course_id[$cur_student->id])) {
                $students_id_to_course_id[$cur_student->id] = $COURSE->id;
            } else if ($students_id_to_course_id[$cur_student->id] != $COURSE->id) {
                $students_id_to_course_id[$cur_student->id] = -1 /* multiple contests*/;
            }
        }
    }
    
    $students = array();
    foreach ($students_id_to_course_id as $cur_student_id => $cur_course_id) {
        $cur_student = $DB->get_record('user', array('id' => $cur_student_id), 'id, firstname, lastname', MUST_EXIST);

        if ($cur_course_id == -1) {
            $cur_student->course_fullname = "Несколько курсов";
            $cur_student->course_id = -1;
        } else {
            $cur_course = $DB->get_record('course', array('id' => $cur_course_id), 'fullname', MUST_EXIST);
            $cur_student->course_fullname = $cur_course->fullname;
            $cur_student->course_id = $cur_course_id;
        }

        $students[] = $cur_student;
    }
    $students = json_encode($students);
    
    $tasks_id_set = array();
    $tasks = array();
    $contest_number = 0;
    $task_number = 0;
    foreach ($ids as $cur_id) {
        $contest_number++;
    
        $CM         = get_coursemodule_from_id('bacs', $cur_id, 0, false, MUST_EXIST);
        $COURSE     = $DB->get_record('course', array('id' => $CM->course), '*', MUST_EXIST);
        $BACS       = $DB->get_record('bacs', array('id' => $CM->instance), '*', MUST_EXIST);
    
        $tasks_for_contest = $DB->get_records('bacs_tasks_to_contests', array('contest_id' => $BACS->id), 'task_order ASC');
        foreach ($tasks_for_contest as $task) {
            $taskr = $DB->get_record('bacs_tasks', array('task_id' => $task->task_id), 'task_id, name');
    
            if ($use_compression && $tasks_id_set[$task->task_id]) continue;
            $tasks_id_set[$task->task_id] = true;
    
            $task_number++;
    
            if ($use_compression) {
                $cross_task_id = $taskr->task_id;
            } else {
                $cross_task_id = $taskr->task_id . '_' . $contest_number;
            }
    
            $taskinfo = new stdClass();
            $taskinfo->task_id = $taskr->task_id;
            $taskinfo->cross_task_id = $cross_task_id;
            $taskinfo->name = $taskr->name;
            $taskinfo->task_order = $task_number;
            $taskinfo->course_module_id = $cur_id;
            $taskinfo->letter = chr(ord('A') + $task->task_order - 1) . $contest_number;
    
            $tasks[] = $taskinfo;
        }
    }
    $tasks = json_encode($tasks);
    
    // upsolving button
    /*
    if (($contest->endtime <= $now)) {
        print "<script type='text/javascript'>
                function toggle_upsolving() {
                    var upsolving_button = document.getElementById('upsolving_button');
                    if (standings.toggle_upsolving()) {
                        upsolving_button.innerHTML = 'Показать дорешивание';
                    } else {
                        upsolving_button.innerHTML = 'Скрыть дорешивание';
                    }
                }
            </script>";
    
        print "<div style='float: right;'>
            <button id='upsolving_button' class='btn btn-info' onclick='toggle_upsolving();'>Скрыть дорешивание</button>
        </div>";
    }
    //*/
    
    // use compression
    if ($use_compression) {
        $not_use_compression = 0;
        $use_compression_checked = 'checked=true';
    } else {
        $not_use_compression = 1;
        $use_compression_checked = '';
    }
    
    print "<script type='text/javascript'>
                function toggle_compression() {
                    window.location.href = '/blocks/teacher_control/multistandings.php?courseids=$courseids_list&ids=$ids_list&compress=$not_use_compression';
                }
            </script>";
    
    // hide inactive
    print "<script type='text/javascript'>
                function toggle_inactive() {
                    var hide_inactive_checkbox = document.getElementById('hide_inactive_checkbox');
                    hide_inactive_checkbox.checked = standings.toggle_inactive();
                }
            </script>";
    
    print "<div>
            <p>
                <input type='checkbox' id='use_compression_checkbox' onclick='toggle_compression();' $use_compression_checked>
                <label for='use_compression_checkbox'>Объединить одинаковые задачи</label>
            </p>
            <p>
                <input type='checkbox' id='hide_inactive_checkbox' onclick='toggle_inactive();'>
                <label for='hide_inactive_checkbox'>Скрыть неактивных</label>
            </p>
        </div>";
    
    print "";
    
    if ($student) {
        $admin_access_str = 'false';
    } else {
        $admin_access_str = 'true';
    }
    
    if (count($ids) == 0) {
        print 'Нечего показывать, добавьте курсы или контесты во вкладке "Настройки содержимого".';
    } else {
        print "<table class='generaltable' id='standings_table'></table>";
    
        print "
            <script type='text/javascript' src='multistandings.js'></script>
    
            <script type='text/javascript'>
                var saved_value_hide_inactive = JSON.parse(localStorage.getItem('standings_hide_inactive'));
                document.getElementById('hide_inactive_checkbox').checked = saved_value_hide_inactive;
                var standings = new Standings($students, $tasks, $submits, Infinity, false, saved_value_hide_inactive, $admin_access_str);
            </script>";
    }
    
}

function print_settings() {
    global $DB, $pattern, $ids, $ids_list, $ids_set, $courseids, $courseids_list, $courseids_set, $use_compression;

    $count_ids = count($ids);

    print "<p><b>Контестов в мониторе: $count_ids </b></p>";

    if ($count_ids > 0) {
        print "<a href='/blocks/teacher_control/multistandings.php.php"
            . "?ids="
            . "&courseids="
            . "&tab=settings"
            . "&pattern=$pattern'><button class='btn btn-warning'>
                Очистить монитор
            </button></a>";

        print "<script type='text/javascript'>
                function toggle_taken() {
                    var taken_button = document.getElementById('taken_button');
                    var taken_table = document.getElementById('taken_table');
                    if (taken_button.innerHTML == 'Скрыть выбранные') {
                        taken_button.innerHTML = 'Показать выбранные';
                        taken_table.style.display = 'none';
                    } else {
                        taken_button.innerHTML = 'Скрыть выбранные';
                        taken_table.style.display = 'block';
                    }
                }
            </script>";

        print " <button id='taken_button' class='btn btn-info' onclick='toggle_taken();'>Показать выбранные</button>";

        print "<table id='taken_table' style='display: none;' class='generaltable'><thead>
                <th>N</th>
                <th>Курс</th>
                <th>Контест</th>
            </thead><tbody>";

        $row_number = 0;
        foreach ($ids as $cur_id) {
            $row_number++;

            $cur_cm = get_coursemodule_from_id('bacs', $cur_id, 0, false, MUST_EXIST);
            $cur_course = $DB->get_record('course', array('id' => $cur_cm->course), '*', MUST_EXIST);
            $cur_contest = $DB->get_record('bacs', array('id' => $cur_cm->instance), '*', MUST_EXIST);

            print "<tr>
                    <td>$row_number</td>
                    <td><a href='/course/view.php?id=$cur_course->id'>$cur_course->fullname</a></td>
                    <td><a href='/mod/bacs/view.php?id=$cur_cm->id'>$cur_contest->name</a></td>
                </tr>";
        }

        print "</tbody></table>";
    }

    if ($ids_list       == '') $ids_list_append       = ''; else $ids_list_append       = $ids_list . '_';
    if ($courseids_list == '') $courseids_list_append = ''; else $courseids_list_append = $courseids_list . '_';

    print "<form action='/blocks/teacher_control/multistandings.php' method='get' style='text-align: center; margin-bottom: 15px;'>
        <b>Поиск курса: </b>
        <input type='text' size=30 name='pattern' value='$pattern'>
        <input type='hidden' name='ids' value='$ids_list'>
        <input type='hidden' name='courseids' value='$courseids_list'>
        <input type='hidden' name='tab' value='settings'>
        <input type='submit' value='Найти'>
    </form>";

    $search_limit = 10;

    $fullnameParam = $DB->sql_like('fullname', ':fullname');

    $course_sql = "
        SELECT id, fullname
        FROM {course} 
        WHERE {$fullnameParam}
        ORDER BY id DESC 
        LIMIT $search_limit ";

    $matched_courses = $DB->get_records_sql($course_sql, ['fullname'  => '%' . $DB->sql_like_escape($pattern) . '%',]);

    print "<table class='generaltable'><thead>
            <th class='header' style='' scope='col'>N</th>
            <th class='header' style='' scope='col'>Курс</th>
            <th class='header' style='' scope='col'>Действия</th>
            <th class='header' style='' scope='col'>Контест</th>
            <th class='header' style='' scope='col'>Действия</th>
        </thead><tbody>";

    $row_number = 0;
    foreach ($matched_courses as $cur_course) {
        $cur_course_contests = $DB->get_records('bacs', array('course' => $cur_course->id));

        foreach ($cur_course_contests as $cur_contest) {
            $row_number++;

            $cur_cm = get_coursemodule_from_instance('bacs', $cur_contest->id, $cur_course->id, false, MUST_EXIST);

            if (isset($courseids_set[$cur_course->id])) {
                $course_action_html = "<i style='color: gray;'>Уже добавлен</i>";
            } else {
                $course_action_html = "<a href='/blocks/teacher_control/multistandings.php"
                    . "?ids=$ids_list"
                    . "&courseids=$courseids_list_append$cur_course->id"
                    . "&tab=settings"
                    . "&pattern=$pattern'><button class='btn btn-info'>
                        Добавить весь курс
                    </button></a>";
            }

            if (isset($ids_set[$cur_cm->id])) {
                $contest_action_html = "<i style='color: gray;'>Уже добавлен</i>";
            } else {
                $contest_action_html = "<a href='/blocks/teacher_control/multistandings.php"
                    . "?ids=$ids_list_append$cur_cm->id"
                    . "&courseids=$courseids_list"
                    . "&tab=settings"
                    . "&pattern=$pattern'><button class='btn btn-info'>
                        Добавить контест
                    </button></a>";
            }

            print "<tr>
                    <td>$row_number</td>
                    <td><a href='/course/view.php?id=$cur_course->id'>$cur_course->fullname</a></td>
                    <td>$course_action_html</td>
                    <td><a href='/mod/bacs/view.php?id=$cur_cm->id'>$cur_contest->name</a></td>
                    <td>$contest_action_html</td>
                </tr>";
        }
    }

    print "</tbody></table>";
}

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/../../mod/bacs/lib.php');
require_once(dirname(__FILE__).'/../../mod/bacs/utils.php');
require_once(dirname(__FILE__).'/../../mod/bacs/submit_verdicts.php');

prepare_params();

require_login();
$CONTEXT = context_coursecat::instance(1 /* programming category */);
$CONTEXT = context_system::instance();
$PAGE->set_context($CONTEXT);

//add_to_log($COURSE->id, 'bacs', 'view', "monitor.php?id={$CM->id}", $BACS->name, $CM->id);

/// Print the page header

$PAGE->set_url('/blocks/teacher_control/multistandings.php', array());
$PAGE->set_title('Составной монитор');
$PAGE->set_heading('Составной монитор');
//$PAGE->set_context($CONTEXT);

//$PAGE->requires->css('/mod/bacs/bootstrap/css/docs.min.css');
//$PAGE->requires->css('/mod/bacs/bootstrap/css/common.css');
//$PAGE->requires->css('/mod/bacs/bootstrap/css/bootstrap.min.css');

//$PAGE->requires->js('/mod/bacs/WWW_TEMP/bootstrap/js/jquery-2.2.2.js', true);
//$PAGE->requires->js('/mod/bacs/WWW_TEMP/bootstrap/js/production.js', true);
//$PAGE->requires->js('/mod/bacs/WWW_TEMP/bootstrap/js/font.js', true);
//$PAGE->requires->js('/mod/bacs/standings.js', true);

$stringman = get_string_manager();
$strings = $stringman->load_component_strings('bacs', 'ru');
$PAGE->requires->strings_for_js(array_keys($strings), 'bacs');

//if (has_capability('mod/bacs:addinstance', $CONTEXT))
//    $student = false;
//else
//    $student = true;

$student = false;

if ($student) {
    print("You have no permission for this operation!");
    die();
}

// Output starts here
echo $OUTPUT->header();

//print_contest_title();

$PAGE->navbar->ignore_active();
$PAGE->navbar->add('preview', new moodle_url('/a/link/if/you/want/one.php'));
$PAGE->navbar->add('name of thing', new moodle_url('/a/link/if/you/want/one.php'));

// HEADER END BOOTSTRAP

print_nav_menu();

$menu_tab = optional_param('tab', 'view', PARAM_TEXT);
if ($menu_tab != 'settings') {
    $menu_tab = 'view';
}

print '<div id="ms_tab_view_content" style="display: none;">';
print_view();
print '</div>';

print '<div id="ms_tab_settings_content" style="display: none;">';
print_settings();
print '</div>';

print '<script type="text/javascript">
    multistandings_open_tab("'.$menu_tab.'");
</script>';

echo $OUTPUT->footer();
