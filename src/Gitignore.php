<?php

declare(strict_types=1);

namespace PowderBlue\GitUtils;

use InvalidArgumentException;
use RuntimeException;

use function array_splice;
use function count;
use function file;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function preg_match;
use function rtrim;

use const false;
use const FILE_APPEND;
use const PHP_EOL;

class Gitignore
{
    private string $filename;

    public function __construct(string $filename)
    {
        $this->setFilename($filename);
    }

    private function getLines(): array
    {
        return file($this->getFilename()) ?: [];
    }

    /** @return int|false */
    public function findPattern(string $pattern)
    {
        foreach ($this->getLines() as $i => $line) {
            if ($pattern === rtrim($line)) {
                return $i;
            }
        }

        return false;
    }

    public function containsPattern(string $pattern): bool
    {
        return false !== $this->findPattern($pattern);
    }

    /**
     * Appends the patterns, starting on a new line.
     *
     * @param string[] $patterns
     */
    public function appendPatterns(array $patterns): self
    {
        $fileContent = file_get_contents($this->getFilename());

        // If the file doesn't end with a newline then we'll need to add one -- so that all the patterns occupy their
        // own, separate line.
        $prefix = !$fileContent || preg_match('~[\r\n]$~', $fileContent)
            ? ''
            : PHP_EOL
        ;

        $bytesWritten = file_put_contents(
            $this->getFilename(),
            $prefix . implode(PHP_EOL, $patterns),
            FILE_APPEND
        );

        if (!$bytesWritten) {
            throw new RuntimeException("Failed to append patterns to `{$this->getFilename()}`.");
        }

        return $this;
    }

    public function insertPatternAtLineNo(string $pattern, int $lineNo): self
    {
        $lines = $this->getLines();

        // We'll need to append an EOL if we're inserting between lines (before the last line).
        $eol = $lineNo < count($lines)
            ? PHP_EOL
            : ''
        ;

        array_splice($lines, $lineNo, 0, ["{$pattern}{$eol}"]);
        $bytesWritten = file_put_contents($this->getFilename(), $lines);

        if (!$bytesWritten) {
            throw new RuntimeException("Failed to insert pattern into `{$this->getFilename()}`.");
        }

        return $this;
    }

    private function setFilename(string $filename): self
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException("The `.gitignore` (`{$filename}`) does not exist.");
        }

        $this->filename = $filename;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
}
