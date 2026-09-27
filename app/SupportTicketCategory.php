<?php

namespace App;

enum SupportTicketCategory: string
{
    case General = 'general';
    case Technical = 'technical';
    case CourseContent = 'course_content';
    case Payment = 'payment';
    case Enrollment = 'enrollment';
    case Certificate = 'certificate';
    case Other = 'other';
}
