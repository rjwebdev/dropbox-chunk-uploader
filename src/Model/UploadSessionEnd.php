<?php

namespace App\Model;

final class UploadSessionEnd
{
    private Cursor $cursor;
    private Commit $commit;

    /**
     * @param Cursor $cursor
     * @param Commit $commit
     */
    public function __construct(Cursor $cursor, Commit $commit)
    {
        $this->cursor = $cursor;
        $this->commit = $commit;
    }

    public function getCursor(): Cursor
    {
        return $this->cursor;
    }

    public function getCommit(): Commit
    {
        return $this->commit;
    }
}