<?php

namespace Facade\Ignition\SolutionProviders;

use Throwable;
use ParseError;
use Illuminate\Support\Str;
use Facade\IgnitionContracts\BaseSolution;
use Facade\IgnitionContracts\HasSolutionsForThrowable;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class MergeConflictSolutionProvider implements HasSolutionsForThrowable
{
    public function canSolve(Throwable $throwable): bool
    {
        if (! ($throwable instanceof FatalThrowableError || $throwable instanceof ParseError)) {
            return false;
        }

        if (! Str::startsWith($throwable->getMessage(), 'syntax error, unexpected \'<<\'')) {
            return false;
        }

        $file = file_get_contents($throwable->getFile());

        if (strpos($file, '=======') === false) {
            return false;
        }

        if (strpos($file, '>>>>>>>') === false) {
            return false;
        }

        return true;
    }

    public function getSolutions(Throwable $throwable): array
    {
        $file = file_get_contents($throwable->getFile());
        preg_match('/\>\>\>\>\>\>\> (.*?)\n/', $file, $matches);
        $source = $matches[1];

        $target = $this->getCurrentBranch(basename($throwable->getFile()));

        return [
            BaseSolution::create("Merge conflict from branch '$source' into $target")
                ->setSolutionDescription('You have a Git merge conflict. To undo your merge do `git reset --hard HEAD`'),
        ];
    }

    private function getCurrentBranch($folder)
    {
        $branch = "'".trim(`cd $folder; git branch | grep \* | cut -d ' ' -f2`)."'";

        if (! isset($branch) || $branch === "''") {
            $branch = 'current branch';
        }

        return $branch;
    }
}