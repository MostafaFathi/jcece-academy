<?php

namespace App;

enum PermissionName: string
{
    case CoursesView = 'courses.view';
    case CoursesCreate = 'courses.create';
    case CoursesUpdate = 'courses.update';
    case CoursesDelete = 'courses.delete';
    case CoursesPublish = 'courses.publish';
    case PackagesView = 'packages.view';
    case PackagesCreate = 'packages.create';
    case PackagesUpdate = 'packages.update';
    case PackagesDelete = 'packages.delete';
    case PackagesPublish = 'packages.publish';
    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';
    case InstructorsView = 'instructors.view';
    case InstructorsUpdate = 'instructors.update';
    case InstructorsManage = 'instructors.manage';
    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';
    case UsersManage = 'users.manage';
    case CurriculumView = 'curriculum.view';
    case CurriculumCreate = 'curriculum.create';
    case CurriculumUpdate = 'curriculum.update';
    case CurriculumDelete = 'curriculum.delete';
    case EnrollmentsView = 'enrollments.view';
    case EnrollmentsManage = 'enrollments.manage';
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case PaymentsView = 'payments.view';
    case PaymentsManage = 'payments.manage';
    case AssessmentsView = 'assessments.view';
    case AssessmentsCreate = 'assessments.create';
    case AssessmentsUpdate = 'assessments.update';
    case AssessmentsDelete = 'assessments.delete';
    case AssessmentsPublish = 'assessments.publish';
    case AssessmentResultsView = 'assessments.results.view';
    case AssignmentsView = 'assignments.view';
    case AssignmentsCreate = 'assignments.create';
    case AssignmentsUpdate = 'assignments.update';
    case AssignmentsDelete = 'assignments.delete';
    case AssignmentsPublish = 'assignments.publish';
    case AssignmentSubmissionsView = 'assignment_submissions.view';
    case AssignmentSubmissionsGrade = 'assignment_submissions.grade';
    case CertificatesView = 'certificates.view';
    case CertificatesIssue = 'certificates.issue';
    case CertificatesRevoke = 'certificates.revoke';
    case ReviewsView = 'reviews.view';
    case ReviewsModerate = 'reviews.moderate';
    case SupportTicketsView = 'support_tickets.view';
    case SupportTicketsManage = 'support_tickets.manage';
    case SupportTicketsReply = 'support_tickets.reply';
}
