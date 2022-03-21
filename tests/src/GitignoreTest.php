<?php

declare(strict_types=1);

namespace PowderBlue\GitUtils\Tests;

use InvalidArgumentException;
use OutOfBoundsException;
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
                $this->createFixtureFilename('with_trailing_newline'),
                'baz',
            ],
            [
                false,
                false,
                $this->createFixtureFilename('with_trailing_newline'),
                '/baz',
            ],
            [
                false,
                false,
                $this->createFixtureFilename('with_trailing_newline'),
                '/baz/',
            ],
            [
                2,
                true,
                $this->createFixtureFilename('with_trailing_newline'),
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
                    '/bar',
                    'baz/',
                    '/qux/',
                    'quux',
                    'quuz',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                [
                    'quux',
                    'quuz',
                ],
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    '/bar',
                    'baz/',
                    '/qux/',
                    'quux',
                    'quuz',
                ]),
                $this->createFixtureFilename('without_trailing_newline'),
                [
                    'quux',
                    'quuz',
                ],
            ],
            [
                implode(PHP_EOL, [
                    '',
                    'foo',
                ]),
                $this->createFixtureFilename('only_newline'),
                [
                    'foo',
                ],
            ],
            [
                implode(PHP_EOL, [
                    '',
                    'foo',
                    '',
                ]),
                $this->createFixtureFilename('only_newline'),
                [
                    'foo',
                    '',
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

    public function providesForTestinsertpatternsatlineno(): array
    {
        return [
            [
                implode(PHP_EOL, [
                    'quux',
                    'foo',
                    '/bar',
                    'baz/',
                    '/qux/',
                    '',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                'quux',
                0,
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    'quux',
                    '/bar',
                    'baz/',
                    '/qux/',
                    '',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                'quux',
                1,
            ],
            [
                implode(PHP_EOL, [
                    'foo',
                    'quux',
                    'quuz',
                    '/bar',
                    'baz/',
                    '/qux/',
                    '',
                ]),
                $this->createFixtureFilename('with_trailing_newline'),
                [
                    'quux',
                    'quuz',
                ],
                1,
            ],
        ];
    }

    /**
     * @dataProvider providesForTestinsertpatternsatlineno
     */
    public function testInsertpatternsatlineno(
        $expectedFileContent,
        $templateFilename,
        $oneOrMorePatterns,
        $lineNo
    ) {
        $tempFilePath = $this->createTempFileFromTemplate($templateFilename);

        $gitignore = new Gitignore($tempFilePath);
        $something = $gitignore->insertPatternsAtLineNo($oneOrMorePatterns, $lineNo);

        $tempFileContent = file_get_contents($tempFilePath);
        unlink($tempFilePath);

        $this->assertSame($gitignore, $something);
        $this->assertSame($expectedFileContent, $tempFileContent);
    }

    public function providesImpossibleArgumentsForInsertpatternsatlineno(): array
    {
        return [
            [  // There are no lines in an empty file.
                $this->createFixtureFilename('empty'),
                'foo',
                1,
            ],
            [  // There are no lines -- at all -- in an empty file.
                $this->createFixtureFilename('empty'),
                'foo',
                0,
            ],
        ];
    }

    /**
     * @dataProvider providesImpossibleArgumentsForInsertpatternsatlineno
     */
    public function testInsertpatternsatlinenoThrowsAnExceptionIfTheLineNoIsOutOfBounds(
        $filename,
        $oneOrMorePatterns,
        $lineNo
    ) {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('The line number does not exist.');

        $gitignore = new Gitignore($filename);
        $gitignore->insertPatternsAtLineNo($oneOrMorePatterns, $lineNo);
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
