<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'scope_type', 'scope_key', 'student_coach_max', 'faculty_coach_max', 'source_reference'])]
class CoachCapacityRule extends Model {}
