<?php

namespace App\Core\Shared\Basecamp\Contracts;

use App\Core\Shared\Basecamp\Data\BasecampProjectData;
use App\Core\Shared\Basecamp\Data\CommentData;
use App\Core\Shared\Basecamp\Data\TodoData;
use App\Core\Shared\Basecamp\Data\TodolistData;
use App\Core\Shared\Basecamp\Data\TodosetData;

interface BasecampClient
{
    public function getProject(string $accountId, string $projectId): BasecampProjectData;

    public function getTodoset(string $url): TodosetData;

    public function getTodolist(string $url): TodolistData;

    /** @return list<TodoData> */
    public function listTodos(string $url): array;

    /** @return list<CommentData> */
    public function listComments(string $url): array;
}
