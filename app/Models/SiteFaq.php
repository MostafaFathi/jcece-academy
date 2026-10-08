<?php

namespace App\Models;

use Database\Factories\SiteFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question_ar', 'answer_ar', 'question_en', 'answer_en', 'sort_order', 'is_active'])]
class SiteFaq extends Model
{
    /** @use HasFactory<SiteFaqFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
