<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Basecamp\Contracts\BasecampClient;
use App\Core\Shared\Basecamp\Data\AttachmentData;
use App\Core\Shared\Basecamp\Data\CommentData;
use App\Core\Shared\Basecamp\Data\TodoData;
use App\Core\Shared\Basecamp\Data\TodosetTodolistData;
use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Modules\KpusGaHw\Application\Exceptions\DatedTodolistNotFoundException;
use App\Modules\KpusGaHw\Application\Exceptions\DuplicateDatedTodolistException;
use Carbon\CarbonImmutable;

class BuildReadOnlyAuditInput
{
    public function __construct(
        private readonly BasecampClient $basecamp,
        private readonly DateTitleParser $dateTitleParser,
    ) {}

    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $reportDate): array
    {
        $accountId = (string) config('basecamp.account_id');
        $projectId = (string) config('basecamp.project_id');

        $project = $this->basecamp->getProject($accountId, $projectId);
        $todosetDock = $project->enabledTodosetDockItem();
        $todoset = $this->basecamp->getTodoset($todosetDock->url);
        $datedListSummary = $this->findDatedList($todoset->todolists, $reportDate);
        $datedList = $this->basecamp->getTodolist($datedListSummary->url);
        $todos = $this->basecamp->listTodos($datedList->todosUrl);

        $this->registerProject($accountId, $projectId, $project->name);

        return [
            'project' => [
                'account_id' => $accountId,
                'project_id' => $project->id,
                'name' => $project->name,
                'todoset_id' => $todoset->id,
            ],
            'report_date' => $reportDate->toDateString(),
            'dated_todolist' => [
                'id' => $datedList->id,
                'title' => $datedList->title,
                'url' => $datedList->url,
                'app_url' => $datedList->appUrl,
            ],
            'areas' => array_map(fn (TodoData $todo): array => $this->normalizeArea($todo), $todos),
        ];
    }

    /** @param list<TodosetTodolistData> $todolists */
    private function findDatedList(array $todolists, CarbonImmutable $reportDate): TodosetTodolistData
    {
        $matches = [];

        foreach ($todolists as $todolist) {
            $parsedDate = $this->dateTitleParser->parse($todolist->title);

            if ($parsedDate?->toDateString() === $reportDate->toDateString()) {
                $matches[] = $todolist;
            }
        }

        if ($matches === []) {
            throw DatedTodolistNotFoundException::forDate($reportDate->toDateString());
        }

        if (count($matches) > 1) {
            throw DuplicateDatedTodolistException::forDate(
                $reportDate->toDateString(),
                array_map(fn (TodosetTodolistData $todolist): string => $todolist->title, $matches),
            );
        }

        return $matches[0];
    }

    /** @return array<string, mixed> */
    private function normalizeArea(TodoData $todo): array
    {
        $comments = $this->basecamp->listComments($todo->commentsUrl);
        $images = $this->normalizeImages($comments);

        return [
            'area_external_id' => $todo->id,
            'area_name' => $todo->areaName(),
            'todo_url' => $todo->appUrl,
            'comments_url' => $todo->commentsUrl,
            'comments_count' => $todo->commentsCount,
            'image_count' => count($images),
            'first_upload_at' => $images[0]['uploaded_at'] ?? null,
            'images' => $images,
        ];
    }

    /**
     * @param  list<CommentData>  $comments
     * @return list<array<string, mixed>>
     */
    private function normalizeImages(array $comments): array
    {
        usort(
            $comments,
            fn (CommentData $left, CommentData $right): int => $left->createdAt <=> $right->createdAt,
        );

        $images = [];

        foreach ($comments as $comment) {
            foreach ($comment->imageAttachments() as $attachment) {
                $images[] = $this->normalizeImage($comment, $attachment);
            }
        }

        return $images;
    }

    /** @return array<string, mixed> */
    private function normalizeImage(CommentData $comment, AttachmentData $attachment): array
    {
        return [
            'attachment_id' => $attachment->id,
            'comment_id' => $comment->id,
            'uploaded_at' => $comment->createdAt->toIso8601String(),
            'content_type' => $attachment->contentType,
            'filename' => $attachment->filename,
            'byte_size' => $attachment->byteSize,
            'width' => $attachment->width,
            'height' => $attachment->height,
            'download_url' => $attachment->downloadUrl,
            'preview_url' => $attachment->previewUrl,
            'thumbnail_url' => $attachment->thumbnailUrl,
        ];
    }

    private function registerProject(string $accountId, string $projectId, string $name): void
    {
        BasecampProject::query()->updateOrCreate(
            [
                'basecamp_account_id' => $accountId,
                'basecamp_project_id' => $projectId,
            ],
            [
                'name' => $name,
                'workflow_type' => 'kpus_ga_hw',
                'notion_database_id' => config('services.notion.data_source_id') ?? config('services.notion.database_id'),
                'active' => true,
            ],
        );
    }
}
