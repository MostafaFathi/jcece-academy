<?php

namespace App\Models;

use Database\Factories\PolicyPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'draft_ar', 'draft_en', 'body_ar', 'body_en', 'version', 'published_at'])]
class PolicyPage extends Model
{
    public const SLUGS = ['privacy', 'terms', 'refund'];

    /** @use HasFactory<PolicyPageFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
