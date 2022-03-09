<?php

declare(strict_types=1);

namespace PowderBlue\GitUtils;

use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;

use function array_keys;
use function array_splice;
use function file;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
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
     * @throws RuntimeException If it failed to append patterns.
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

    /**
     * @param array|string $oneOrMorePatterns
     * @param integer $lineNo
     * @throws OutOfBoundsException If the line number does not exist.
     * @throws RuntimeException If it failed to insert the pattern(s).
     */
    public function insertPatternsAtLineNo($oneOrMorePatterns, int $lineNo): self
    {
        $lines = $this->getLines();

        if (!in_array($lineNo, array_keys($lines))) {
            throw new OutOfBoundsException('The line number does not exist.');
        }

        $patternsStr = implode(PHP_EOL, (array) $oneOrMorePatterns) . PHP_EOL;
        array_splice($lines, $lineNo, 0, [$patternsStr]);

        $bytesWritten = file_put_contents($this->getFilename(), $lines);

        if (!$bytesWritten) {
            throw new RuntimeException("Failed to insert the pattern(s) into `{$this->getFilename()}`.");
        }

        return $this;
    }

    /**
     * @throws InvalidArgumentException If the gitignore file does not exist.
     */
    private function setFilename(string $filename): self
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException("The gitignore file (`{$filename}`) does not exist.");
        }

        $this->filename = $filename;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
}
