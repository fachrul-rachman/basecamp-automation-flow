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
            imageUrls: $this->imageUrls($area),
            schema: $this->schema(),
            schemaName: 'kpus_ga_hw_vision_review',
        );
    }

    private function developerPrompt(): string
    {
        return <<<'PROMPT'
You review KPUS GA HW daily area evidence cautiously.
Hayam Wuruk is the expected office/site location for all KPUS GA HW areas.
Lestari Memorial Park is the company/brand name and may appear on paper checklist forms; it is not a different site location by itself.
Area names such as Tomb, Finance, Pantry, or BD are rooms/areas inside the Hayam Wuruk site.
Do not mark evidence anomalous merely because the printed location contains Hayam Wuruk.
Do not mark evidence anomalous merely because a paper form contains Lestari Memorial Park.
Only flag location when the visible printed location clearly points to another site, clearly does not contain Hayam Wuruk, or cannot be read reliably.
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
            'Review up to the first %d evidence images for room/area "%s" inside the Hayam Wuruk office/site on report date %s. Check whether at least one image appears to show area condition, at least one appears to show the paper checklist, printed timestamp/date seems inconsistent, printed location clearly points to a site other than Hayam Wuruk or is unreadable, image is blurry/unreadable, image is irrelevant to the requested room/area, images appear duplicated, checklist is missing/unreadable, or visible cleanliness may need human review. If the printed location contains Hayam Wuruk, treat that as expected site evidence, not an anomaly by itself. If a paper form contains Lestari Memorial Park, treat it as the company/form brand, not as a conflicting location.',
            $this->maxImages(),
            $areaName,
            $reportDate->toDateString(),
        );
    }

    /**
     * @param  array<string, mixed>  $area
     * @return list<string>
     */
    private function imageUrls(array $area): array
    {
        $images = array_slice($area['images'] ?? [], 0, $this->maxImages());
        $urls = [];

        foreach ($images as $image) {
            if (is_array($image) && is_string($image['download_url'] ?? null) && $image['download_url'] !== '') {
                $urls[] = $this->attachmentDownloader->toImageInput($image['download_url']);
            }
        }

        return $urls;
    }

    private function maxImages(): int
    {
        return max(1, (int) config('kpus-ga-hw.ai_max_images', 4));
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
