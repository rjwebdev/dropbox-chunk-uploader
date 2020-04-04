<?php

namespace App\Model;

final class SessionAppend
{
    private Cursor $cursor;
    private bool $close;

    /**
     * @param Cursor $cursor
     * @param bool   $close
     */
    public function __construct(Cursor $cursor, bool $close = false)
    {
        $this->cursor = $cursor;
        $this->close = $close;
    }

    public function getCursor(): Cursor
    {
        return $this->cursor;
    }

    public function isClose(): bool
    {
        return $this->close;
    }
}