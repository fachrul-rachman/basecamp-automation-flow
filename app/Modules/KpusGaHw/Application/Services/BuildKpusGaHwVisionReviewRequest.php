<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Basecamp\Contracts\AttachmentDownloader;
use App\Core\Shared\OpenAI\Data\VisionReviewRequest;
use Carbon\CarbonImmutable;

class BuildKpusGaHwVisionReviewRequest
{
    public function __construct(private readonly AttachmentDownloader $attachmentDownloader) {}

    /**
     * @param  array<string, mixed>  $area
     */
    public function handle(array $area, CarbonImmutable $reportDate): VisionReviewRequest
    {
        return new VisionReviewRequest(
            developerPrompt: $this->developerPrompt(),
            userPrompt: $this->userPrompt((string) $area['area_name'], $reportDate),
            imageUrls: $this->firstTwoImageUrls($area),
            schema: $this->schema(),
            schemaName: 'kpus_ga_hw_vision_review',
        );
    }

    private function developerPrompt(): string
    {
        return <<<'PROMPT'
You review KPUS GA HW daily area evidence cautiously.
Return only strict JSON matching the provided schema.
Allowed result values are ok, anomaly, and uncertain.
Never return the final business status.
Never claim certainty about cleanliness.
Use short cautious Indonesian reasons.
PROMPT;
    }

    private function userPrompt(string $areaName, CarbonImmutable $reportDate): string
    {
        return sprintf(
            'Review the first two evidence images for area "%s" on report date %s. Check whether one image appears to show area condition, one appears to show the paper checklist, printed timestamp/date seems inconsistent, printed location does not appear to contain Hayam Wuruk, image is blurry/unreadable, image is irrelevant, images appear duplicated, checklist is missing/unreadable, or visible cleanliness may need human review.',
            $areaName,
            $reportDate->toDateString(),
        );
    }

    /**
     * @param  array<string, mixed>  $area
     * @return list<string>
     */
    private function firstTwoImageUrls(array $area): array
    {
        $images = array_slice($area['images'] ?? [], 0, 2);
        $urls = [];

        foreach ($images as $image) {
            if (is_array($image) && is_string($image['download_url'] ?? null) && $image['download_url'] !== '') {
                $urls[] = $this->attachmentDownloader->toImageInput($image['download_url']);
            }
        }

        return $urls;
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['result', 'reasons', 'confidence'],
            'properties' => [
                'result' => [
                    'type' => 'string',
                    'enum' => ['ok', 'anomaly', 'uncertain'],
                ],
                'reasons' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'minItems' => 1,
                    'maxItems' => 5,
                ],
                'confidence' => [
                    'type' => 'string',
                    'enum' => ['high', 'medium', 'low'],
                ],
            ],
        ];
    }
}
