<?php

namespace App;

enum AssignmentSubmissionType: string
{
    case Text = 'text';
    case File = 'file';
    case TextAndFile = 'text_and_file';

    public function requiresText(): bool
    {
        return $this !== self::File;
    }

    public function requiresFile(): bool
    {
        return $this !== self::Text;
    }
}
