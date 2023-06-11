<?php

namespace App;

class Database
{
    private string $path;
    private array $data;

    public function __construct()
    {
        $this->path = __DIR__ . '/../database.json';
        $this->data = json_decode(file_get_contents($this->path), true);
    }

    public function get($key)
    {
        return $this->data[$key] ?? [];
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;

        $this->write();
    }

    private function write()
    {
        file_put_contents($this->path, json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
