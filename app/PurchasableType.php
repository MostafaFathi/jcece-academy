<?php

namespace App;

use App\Models\Course;
use App\Models\Package;

enum PurchasableType: string
{
    case Course = 'course';
    case Package = 'package';

    /** @return class-string<Course|Package> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Course => Course::class,
            self::Package => Package::class,
        };
    }
}
