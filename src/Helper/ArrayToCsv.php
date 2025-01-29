<?php

namespace Wtsergo\Misc\Helper;

class ArrayToCsv
{
    private $handle;
    public function __construct(
        private string $delimiter = ',',
        private string $enclosure = '"',
        private string $escape = "\\"
    )
    {
        $this->handle = fopen('php://memory', 'r+');
    }

    public function __destruct()
    {
        fclose($this->handle);
    }

    public function convert(array $data, bool $multiline = false): string
    {
        ftruncate($this->handle, 0);
        if ($multiline) {
            foreach ($data as $row) {
                fputcsv($this->handle, $row, $this->delimiter, $this->enclosure, $this->escape);
            }
        } else {
            fputcsv($this->handle, $data, $this->delimiter, $this->enclosure, $this->escape);
        }
        rewind($this->handle);
        return stream_get_contents($this->handle);
    }
}
