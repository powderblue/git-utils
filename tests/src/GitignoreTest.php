<?php

declare(strict_types=1);

namespace PowderBlue\GitUtils\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PowderBlue\GitUtils\Gitignore;
use RuntimeException;

use function copy;
use function file_get_contents;
use function implode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const false;
use const PHP_EOL;
use const true;

class GitignoreTest extends TestCase
{
    public function testConstructor()
    {
        $filename = $this->createFixtureFilename('empty');

        $gitignore = new Gitignore($filename);

        $this->assertSame($filename, $gitignore->getFilename());
    }

    public function testConstructorThrowsAnExceptionIfTheFileDoesNotExist()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $filename = $this->createFixtureFilename('Supercalifragilisticexpialidocious');

        new Gitignore($filename);
    }

    public function providesForTestfindpattern(): array
    {
        return [
            [
                false,  // Line no.
                false,  // Contains pattern?
                $this->createFixtureFilename('empty'),
                'anything',
            ],
            [
                false,
                false,
                $this->createFixtureFilename('files_and_dirs'),
                'baz',
            ],
            [
                false,
                false,
                $this->createFixtureFilename('files_and_dirs'),
                '/baz',
            ],
            [
                false,
                false,
                $this->createFixtureFilename('files_and_dirs'),
                '/baz/',
            ],
            [
                2,
                true,
                $this->createFixtureFilename('files_and_dirs'),
                'baz/',
            ],
        ];
    }

    /**
     * @dataProvider providesForTestfindpattern
     */
    public function testFindpattern(
        $lineNumOfPattern,
        $fileContainsPattern,
        $filename,
        $pattern
    ) {
        $gitignore = new Gitignore($filename);

        $this->assertSame($lineNumOfPattern, $gitignore->findPattern($pattern));
    }

    /**
     * @dataProvider providesForTestfindpattern
     */
    public function testContainspattern(
        $lineNumOfPattern,
        $fileContainsPattern,
        $filename,
        $pattern
    ) {
        $gitignore = new Gitignore($filename);

        $this->assertSame($fileContainsPattern, $gitignore->containsPattern($pattern));
    }

    public function providesForTestappendpatterns(): array
    {
        return [
            [
                implode(PHP_EOL, [
                    'qux',
                    'quux',
                ]),
                $this->createFixtureFilename('empty'),
                [
                    'qux',
                    'quux',
                ],
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    'bar',
                    'baz',
                    'qux',
                    'quux',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                [
                    'qux',
                    'quux',
                ],
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    'bar',
                    'baz',
                    'qux',
                    'quux',
                ]),
                $this->createFixtureFilename('without_trailing_newline'),
                [
                    'qux',
                    'quux',
                ],
            ],
        ];
    }

    /**
     * @dataProvider providesForTestappendpatterns
     */
    public function testAppendpatterns(
        $expectedFileContent,
        $templateFilename,
        $patterns
    ) {
        $tempFilePath = $this->createTempFileFromTemplate($templateFilename);

        $gitignore = new Gitignore($tempFilePath);
        $something = $gitignore->appendPatterns($patterns);

        $tempFileContent = file_get_contents($tempFilePath);
        unlink($tempFilePath);

        $this->assertSame($gitignore, $something);
        $this->assertSame($expectedFileContent, $tempFileContent);
    }

    public function providesForTestinsertpatternatlineno(): array
    {
        return [
            [
                implode(PHP_EOL, [
                    'foo',
                ]),
                $this->createFixtureFilename('empty'),
                'foo',
                0,
            ],
            [
                implode(PHP_EOL, [
                    'qux',
                    'foo',
                    'bar',
                    'baz',
                    '',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                'qux',
                0,
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    'qux',
                    'bar',
                    'baz',
                    '',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                'qux',
                1,
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    'bar',
                    'baz',
                    'qux',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                'qux',
                3,
            ],
        ];
    }

    /**
     * @dataProvider providesForTestinsertpatternatlineno
     */
    public function testInsertpatternatlineno(
        $expectedFileContent,
        $templateFilename,
        $pattern,
        $lineNo
    ) {
        $tempFilePath = $this->createTempFileFromTemplate($templateFilename);

        $gitignore = new Gitignore($tempFilePath);
        $something = $gitignore->insertPatternAtLineNo($pattern, $lineNo);

        $tempFileContent = file_get_contents($tempFilePath);
        unlink($tempFilePath);

        $this->assertSame($gitignore, $something);
        $this->assertSame($expectedFileContent, $tempFileContent);
    }

    private function createFixtureFilename(string $basename): string
    {
        return __DIR__ . "/GitignoreTest/{$basename}";
    }

    private function createTempFileFromTemplate(string $templateFilename): string
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), '');
        $copied = copy($templateFilename, $tempFilePath);

        if (!$copied) {
            throw new RuntimeException("Failed to copy template file `{$templateFilename}`.");
        }

        return $tempFilePath;
    }
}
