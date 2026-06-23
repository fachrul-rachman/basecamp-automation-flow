<?php

namespace App\Core\Shared\OpenAI\Contracts;

use App\Core\Shared\OpenAI\Data\VisionReviewRequest;
use App\Core\Shared\OpenAI\Data\VisionReviewResponse;

interface VisionReviewClient
{
    public function review(VisionReviewRequest $request): VisionReviewResponse;
}
