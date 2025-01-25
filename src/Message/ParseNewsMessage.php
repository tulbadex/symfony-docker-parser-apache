<?php
namespace App\Message;

class ParseNewsMessage
{
    private $source;

    public function __construct(string $source = 'https://highload.today/category/novosti/')
    {
        $this->source = $source;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}