<?php

namespace App;

enum CourseTrainingType: string
{
    case Recorded = 'recorded';
    case Live = 'live';
    case Hybrid = 'hybrid';
}
