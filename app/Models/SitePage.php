<?php

namespace App\Models;

use Database\Factories\SitePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'draft_ar', 'draft_en', 'body_ar', 'body_en', 'published_at'])]
class SitePage extends Model
{
    public const SLUGS = ['about', 'faq', 'contact'];

    /** @use HasFactory<SitePageFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
