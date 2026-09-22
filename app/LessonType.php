<?php

namespace App;

enum LessonType: string
{
    case Video = 'video';
    case Text = 'text';
    case File = 'file';
    case Link = 'link';
}
