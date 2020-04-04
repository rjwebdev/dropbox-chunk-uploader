<?php

namespace App\Model;

class Commit
{
    public const MODE_ADD = 'add';
    public const MODE_OVERWRITE = 'overwrite';

    private string $path;
    private string $mode;
    private bool $autorename;
    private bool $mute;

    /**
     * @param string $path
     * @param string $mode
     * @param bool   $autorename
     */
    public function __construct(string $path, string $mode, bool $autorename, bool $mute)
    {
        $this->path = $path;
        $this->mode = $mode;
        $this->autorename = $autorename;
        $this->mute = $mute;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isAutorename(): bool
    {
        return $this->autorename;
    }

    public function isMute(): bool
    {
        return $this->mute;
    }
}

//"commit": {
//    "path": "/Homework/math/Matrices.txt",
//        "mode": "add",
//        "autorename": true,
//        "mute": false,
//        "strict_conflict": false
//    }