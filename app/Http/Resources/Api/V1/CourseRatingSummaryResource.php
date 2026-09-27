<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseRatingSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $count = (int) $this->published_reviews_count;

        return [
            'average_rating' => $count === 0 ? null : round((float) $this->published_reviews_average_rating, 2),
            'review_count' => $count,
            'distribution' => [
                'rating_1' => (int) $this->published_reviews_rating_1_count,
                'rating_2' => (int) $this->published_reviews_rating_2_count,
                'rating_3' => (int) $this->published_reviews_rating_3_count,
                'rating_4' => (int) $this->published_reviews_rating_4_count,
                'rating_5' => (int) $this->published_reviews_rating_5_count,
            ],
        ];
    }
}
