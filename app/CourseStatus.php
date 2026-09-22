<?php

namespace App;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
    case ComingSoon = 'coming_soon';
    case Archived = 'archived';
}
