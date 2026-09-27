<?php

namespace App;

enum AssignmentSubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case RevisionRequested = 'revision_requested';
    case Graded = 'graded';
}
