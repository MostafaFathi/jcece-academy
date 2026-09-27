<?php

namespace App;

enum AssignmentGradingAction: string
{
    case Graded = 'graded';
    case RevisionRequested = 'revision_requested';
    case Corrected = 'corrected';
}
