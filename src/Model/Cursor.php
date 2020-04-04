<?php

namespace App\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class Cursor
{
    /**
     * @var string
     *
     * @SerializedName("session_id")
     */
    private string $sessionId;
    private int $offset;

    /**
     * @param string $sessionId
     * @param int    $offset
     */
    public function __construct(string $sessionId, int $offset)
    {
        $this->sessionId = $sessionId;
        $this->offset = $offset;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}