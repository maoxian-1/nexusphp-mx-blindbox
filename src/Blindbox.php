<?php
namespace NexusPlugin\Blindbox;

class Blindbox
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'blindbox';
    }
}
