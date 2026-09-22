<?php

namespace App;

enum PermissionName: string
{
    case CoursesView = 'courses.view';
    case CoursesCreate = 'courses.create';
    case CoursesUpdate = 'courses.update';
    case CoursesDelete = 'courses.delete';
    case CoursesPublish = 'courses.publish';
    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';
    case InstructorsView = 'instructors.view';
    case InstructorsUpdate = 'instructors.update';
    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';
    case CurriculumView = 'curriculum.view';
    case CurriculumCreate = 'curriculum.create';
    case CurriculumUpdate = 'curriculum.update';
    case CurriculumDelete = 'curriculum.delete';
}
