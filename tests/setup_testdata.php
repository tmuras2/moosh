<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI script to populate a fresh Moodle installation with test data:
//   - 4 categories, each with 3 courses (12 courses total)
//   - 1 file resource per course
//   - 50 student accounts
//   - 10 teacher accounts
//   - All students and teachers enrolled into all courses
//
// Usage: php setup_testdata.php
//        sudo -u www-data php setup_testdata.php

define('CLI_SCRIPT', true);
$moodledir = $argv[1];

require($moodledir . '/public/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');
require_once($CFG->libdir . '/testing/generator/component_generator_base.php');
require_once($CFG->libdir . '/testing/generator/module_generator.php');
require_once($CFG->dirroot . '/mod/resource/tests/generator/lib.php');

// We need an admin user set as current user for file operations.
$admin = get_admin();
\core\session\manager::set_user($admin);

$generator = new testing_data_generator();

cli_heading('Moodle test data generator');

// ---------- Categories & Courses ----------

$categorynames = [
    'Mathematics',
    'Sciences',
    'Humanities',
    'Computer Science',
];

$coursenames = [
    'Mathematics' => [
        'Algebra Fundamentals',
        'Calculus I',
        'Statistics and Probability',
    ],
    'Sciences' => [
        'Introduction to Physics',
        'General Chemistry',
        'Biology Essentials',
    ],
    'Humanities' => [
        'World History',
        'Introduction to Philosophy',
        'English Literature',
    ],
    'Computer Science' => [
        'Programming Basics',
        'Data Structures',
        'Web Development',
    ],
];

$courses = [];
$resourcegen = $generator->get_plugin_generator('mod_resource');

foreach ($categorynames as $catname) {
    $category = $generator->create_category([
        'name' => $catname,
        'description' => "Test category: $catname",
    ]);
    cli_writeln("Created category: $catname (id={$category->id})");

    foreach ($coursenames[$catname] as $cname) {
        $shortname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cname));
        $course = $generator->create_course([
            'fullname'  => $cname,
            'shortname' => substr($shortname, 0, 20) . '_' . $category->id,
            'category'  => $category->id,
            'summary'   => "This is the course: $cname in the $catname category.",
            'numsections' => 3,
        ]);
        cli_writeln("  Created course: $cname (id={$course->id})");

        // Add a file resource to section 1.
        $resource = $resourcegen->create_instance([
            'course' => $course->id,
            'name'   => "Course material - $cname",
            'intro'  => "Sample file resource for $cname.",
            'introformat' => FORMAT_HTML,
            'defaultfilename' => 'coursefile.txt',
        ], ['section' => 1]);
        cli_writeln("    Added file resource (cmid={$resource->cmid})");

        $courses[] = $course;
    }
}

$cname = "Empty course";
$shortname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cname));
$course = $generator->create_course([
        'fullname'  => $cname,
        'shortname' => substr($shortname, 0, 20) . '_' . $category->id,
        'category'  => $category->id,
        'summary'   => "Empty course: $cname in the $catname category.",
        'numsections' => 3,
]);
cli_writeln("  Created empty course: $cname (id={$course->id})");

// ---------- Students ----------

cli_writeln('');
cli_heading('Creating student accounts');

$students = [];
for ($i = 1; $i <= 50; $i++) {
    $num = str_pad($i, 2, '0', STR_PAD_LEFT);
    $user = $generator->create_user([
        'username'  => "student{$num}",
        'password'  => 'Student1!',
        'firstname' => "Student",
        'lastname'  => "User{$num}",
        'email'     => "student{$num}@example.invalid",
    ]);
    $students[] = $user;
}
cli_writeln("Created 50 student accounts (student01 .. student50, password: Student1!)");

// ---------- Teachers ----------

cli_heading('Creating teacher accounts');

$teachers = [];
for ($i = 1; $i <= 10; $i++) {
    $num = str_pad($i, 2, '0', STR_PAD_LEFT);
    $user = $generator->create_user([
        'username'  => "teacher{$num}",
        'password'  => 'Teacher1!',
        'firstname' => "Teacher",
        'lastname'  => "User{$num}",
        'email'     => "teacher{$num}@example.invalid",
    ]);
    $teachers[] = $user;
}
cli_writeln("Created 10 teacher accounts (teacher01 .. teacher10, password: Teacher1!)");

// ---------- Enrolments ----------

cli_heading('Enrolling users into courses');

$studentrole  = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole  = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);

// Fill up first 10 courses with students and teachers

foreach (array_slice($courses, 0, 10) as $course) {
    // Enrol all students.
    foreach ($students as $student) {
        $generator->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
    }
    // Enrol all teachers.clear
    foreach ($teachers as $teacher) {
        $generator->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');
    }
    cli_writeln("Enrolled 50 students + 10 teachers into: {$course->fullname}");
}

// ---------- Summary ----------

cli_writeln('');
cli_heading('Done!');
cli_writeln("Categories created: " . count($categorynames));
cli_writeln("Courses created:    " . count($courses));
cli_writeln("Students created:   " . count($students) . "  (student01..student50 / Student1!)");
cli_writeln("Teachers created:   " . count($teachers) . "  (teacher01..teacher10 / Teacher1!)");
cli_writeln("Enrolments:         All users enrolled in first 10 courses");
