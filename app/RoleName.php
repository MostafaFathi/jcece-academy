<?php

namespace App;

enum RoleName: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case ContentManager = 'content_manager';
    case SalesSupport = 'sales_support';
    case Admin = 'admin';
}
