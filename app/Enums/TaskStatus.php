<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'zinc',
            self::InProgress => 'blue',
            self::Done => 'green',
        };
    }
}
