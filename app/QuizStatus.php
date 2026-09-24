<?php

namespace App;

enum QuizStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
