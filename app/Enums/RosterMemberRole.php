<?php

namespace App\Enums;

enum RosterMemberRole: string
{
    case StudentAthlete = 'student_athlete';
    case Reserve = 'reserve';
    case StudentCoach = 'student_coach';
    case FacultyCoach = 'faculty_coach';
}
