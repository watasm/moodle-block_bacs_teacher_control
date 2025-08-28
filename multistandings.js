class StandingsStudent {
    constructor (student, tasks) {
        this.student = student;
        this.tasks = tasks;
        this.rank_position = 42;
        
        this.submits = [];
    }

    get firstname () {
        return this.student.firstname;
    }

    get lastname () {
        return this.student.lastname;
    }

    get fullname () {
        return this.student.firstname + ' ' + this.student.lastname;
    }

    get user_id() {
        return this.student.id;
    }    

    get total_failed() {
        return this.total_tried - this.total_solved;
    }

    get course_info() {
        if (this.student.course_id == -1) {
            return "<i style='color: gray'>" + this.student.course_fullname + "</i>";
        } else {
            return "<a href='/course/view.php?id=" + this.student.course_id + "'>" + this.student.course_fullname + "</a>";
        }
    }

    add_submit (submit) {
        this.submits.push(submit);
    }

    build_results (time_cut) {
        this.active = false;

        this.results = [];
        for (var task of this.tasks) {
            this.results.push({
                task: task,

                accepted: false,
                judged: true,
                attempts: 0,
                points: 0,
                course_module_id: 0,
            });
        }

        for (var submit of this.submits) {
            if (!submit.task) continue;
            if (time_cut <= submit.submit_time) continue;

            this.active = true;
            var idx = submit.task.task_order-1;

            this.results[idx].attempts += 1;
            this.results[idx].points = Math.max(this.results[idx].points, submit.points);
            this.results[idx].course_module_id = submit.course_module_id;
            if (submit.accepted) this.results[idx].accepted = true;
            if (!submit.judged) this.results[idx].judged = false;
        }

        this.total_solved = 0;
        this.total_tried = 0;
        this.total_points = 0;

        for (var result of this.results) {
            if (result.accepted) this.total_solved += 1;
            if (result.attempts > 0) this.total_tried += 1;
            this.total_points += result.points;
        }
    }

    build_html (hide_inactive, course_module_id, admin_access) {
        this.html = '';

        if (hide_inactive && !this.active) {
            return this.html;
        }

        this.html += '<tr>';
        this.html += '<td class="cell">' + this.rank_position + '</td>';
        this.html += '<td class="cell">';
        this.html += this.fullname;
        //if (admin_access) this.html += '<sub><a href="mod/bacs/results.php?id='+course_module_id+'&user_id='+this.user_id+'">[посылки]</a></sub>';  
        this.html += '</td>';

        for (var result of this.results) {
            this.html += '<td style="text-align: center;" class="cell">';

            if (result.attempts == 0) {
                this.html += '-';
            } else {
                var font_color = result.accepted ? "green" : "red";
                var question_mark = (result.accepted || result.judged) ? '' : '<sup>?</sup>';

                this.html += 
                    '<font color="' + font_color + '" title="' + result.task.letter + '. ' + result.task.name + '">' +
                        //'<span style="border: 1px solid green; margin: 2px; padding: 2px;">' + 
                            result.points + 
                        //'</span>' + 
                        question_mark;
                
                this.html += '<sub>';
                if (admin_access) this.html += 
                        '<a href="mod/bacs/results.php?id='+result.course_module_id+'&user_id='+this.user_id+'&task_id='+result.task.task_id+'">';
                this.html += '[' + result.attempts + ']';
                if (admin_access) this.html += '</a>';
                this.html += '</sub>';
                this.html += '</font>';
            }

            this.html += '</td>';
        }

        this.html += '<td style="text-align: center;" class="cell">';
        this.html += '<font color="green">' + this.total_solved + '</font>';
        if (this.total_failed > 0) this.html += '/' + '<font color="red">' + this.total_failed + '</font>';
        this.html += '</td>';
        
        this.html += '<td class="cell">' + this.total_points + '</td>';

        // duplicate left columns
        this.html += '<td class="cell">' + this.rank_position + '</td>';
        this.html += '<td class="cell">';
        this.html += this.fullname;
        //if (admin_access) this.html += '<sub><a href="mod/bacs/results.php?id='+course_module_id+'&user_id='+this.user_id+'">[посылки]</a></sub>';  
        this.html += '</td>';
        this.html += '<td class="cell" style="white-space: nowrap;">' + this.course_info + '</td>';
        
        this.html += '</tr>';

        return this.html;
    }
}

class Standings {
    constructor (students, tasks, submits, contest_endtime, hide_upsolving, hide_inactive, admin_access) {
        students = students.map(function(st) { return new StandingsStudent(st, tasks); });

        console.log(submits);

        this.students = students;
        this.tasks = tasks;
        this.submits = submits;
        this.admin_access = admin_access;

        // prepare indexes
        this.student_by_id = {};
        for (var student of students) {
            this.student_by_id[student.user_id] = student;
        }
        
        this.task_by_id = {};
        for (var task of tasks) {
            this.task_by_id[task.cross_task_id] = task;
        }
        
        // additional info
        for (var submit of this.submits) {
            submit.accepted = (submit.result_id == 13 /* Accepted verdict */);
            submit.judged = (submit.result_id != 1 /* Pending verdict */ && 
                             submit.result_id != 2 /* Running verdict */);
            submit.task = this.task_by_id[submit.cross_task_id];
        }

        for (var task of tasks) {
            //task.letter = String.fromCharCode('A'.charCodeAt(0)+Number(task.task_order)-1);
        }

        // fill submits
        for (var submit of submits) {
            if (!this.student_by_id.hasOwnProperty(submit.user_id)) continue;
            this.student_by_id[submit.user_id].add_submit(submit);
        }

        // build
        this.contest_endtime = contest_endtime;
        this.hide_inactive = hide_inactive;
        this.hide_upsolving = hide_upsolving;
        this.build();
    }

    toggle_upsolving() {
        this.hide_upsolving = !this.hide_upsolving;

        this.build();

        return this.hide_upsolving;
    }

    toggle_inactive() {
        this.hide_inactive = !this.hide_inactive;

        localStorage.setItem('standings_hide_inactive', this.hide_inactive);

        this.build();

        return this.hide_inactive;
    }

    build () {
        // build each line
        for (var student of this.students) {
            student.build_results(this.hide_upsolving ? this.contest_endtime : Infinity);
        }

        // sort students
        this.students.sort(function (a, b) {
            if (a.total_points > b.total_points) return -1;
            if (a.total_points < b.total_points) return 1;

            if (a.total_solved > b.total_solved) return -1;
            if (a.total_solved < b.total_solved) return 1;

            return a.fullname.localeCompare(b.fullname);
        });

        // assign positions
        var prevpoints = NaN;
        var prevpos = NaN;
        for (var i = 0; i < this.students.length; i++) {
            if (prevpoints != this.students[i].total_points) {
                prevpoints = this.students[i].total_points;
                prevpos = i;
            }

            this.students[i].rank_position = prevpos + 1;
        }

        // prepare html
        for (var student of this.students) {
            student.build_html(this.hide_inactive, this.course_module_id, this.admin_access);
        }

        // prepare stats
        this.task_stats = [];
        for (var task of this.tasks) {
            this.task_stats.push({
                task: task,

                solved: 0,
                tried: 0,
            });
        }

        for (var i = 0; i < this.tasks.length; i++) {
            for (var student of this.students) {
                if (student.results[i].accepted) this.task_stats[i].solved += 1;
                if (student.results[i].attempts > 0) this.task_stats[i].tried += 1;
            }
        }
        
        // build
        this.html = '';

        // header
        this.html += 
            '<thead><tr>' +
                '<th class="header" scope="col">N</th>' +
                '<th class="header" scope="col">Имя участника</th>';
        
        for (var task of this.tasks) {
            this.html += 
                '<th class="header" style="text-align: center;" scope="col" title="' + task.letter + '. ' + task.name + '">' + 
                    task.letter
                '</th>';
        }

        this.html += 
                '<th class="header" style="text-align: center;" scope="col">+</th>' +
                '<th class="header" scope="col">Баллы</th>' +
                // duplicate left columns
                '<th class="header" scope="col">N</th>' +
                '<th class="header" scope="col">Имя участника</th>' +
                '<th class="header" scope="col">Курс</th>' +
            '</tr></thead><tbody>';

        // rows
        for (var student of this.students) {
            this.html += student.html;
        }

        this.html += '</tbody>';

        // stats
        this.html += '<tfoot>';
        
        this.html += 
            '<tr>' + 
                '<td></td>' + 
                '<td>' + 
                    '<font color="green">Количество решивших</font>' + 
                    '</br>' + 
                    '<font color="grey">Количество попытавшихся</font>' + 
                '</td>';

        for (var task_stat of this.task_stats) {
            this.html += '<td style="text-align: center;" class="cell">';

            this.html += 
                '<font color="green" title="' + task_stat.task.letter + '. ' + task_stat.task.name + '">' +
                    task_stat.solved +
                '</font>' +
                '<br>' +
                '<font color="grey" title="' + task_stat.task.letter + '. ' + task_stat.task.name + '">' +
                    task_stat.tried +
                '</font>';

            this.html += '</td>';
        }

        this.html += 
                '<td></td>' +
                '<td></td>' +
                '</tr>' +
            '</tfoot>';

        // show
        document.getElementById('standings_table').innerHTML = this.html;
    }
}
