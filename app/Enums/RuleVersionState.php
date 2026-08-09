<?php

namespace App\Enums;

enum RuleVersionState: string
{
    case Draft = 'draft';
    case ActivatedEditable = 'activated_editable';
    case Frozen = 'frozen';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
