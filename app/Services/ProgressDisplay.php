<?php

namespace App\Services;

class ProgressDisplay
{
    private $totalIterations;
    private $startTime;
    private $dataName;
    private $spinnerChars;
    private $spinnerIndex;

    public function __construct($totalIterations, $dataName)
    {
        $this->totalIterations = $totalIterations;
        $this->dataName        = $dataName;
        $this->startTime       = microtime(true);
        $this->spinnerChars    = ['/', '-', '\\', '|'];
        $this->spinnerIndex    = 0;

        echo sprintf("Starting the generation of %s data...\n", $this->dataName);
    }

    public function displayProgress($currentIteration)
    {
        $progressPercentage = ($currentIteration + 1) / $this->totalIterations * 100;

        $elapsedTime            = microtime(true) - $this->startTime;
        $estimatedTotalTime     = ($currentIteration > 0) ? ($elapsedTime / $currentIteration) * $this->totalIterations : 0;
        $estimatedTimeRemaining = $estimatedTotalTime - $elapsedTime;

        $progressBar = '[';
        $progressBar .= str_repeat('█', floor($progressPercentage / 2));
        $progressBar .= str_repeat(' ', 50 - floor($progressPercentage / 2));
        $progressBar .= '] ';
        $progressBar .= $this->spinnerChars[$this->spinnerIndex];

        $formattedElapsedTime            = gmdate('H:i:s', (int) $elapsedTime);
        $formattedEstimatedTimeRemaining = gmdate('H:i:s', (int) $estimatedTimeRemaining);
        $memoryUsage                     = $this->getMemoryUsage();

        echo "\r".$progressBar.sprintf(
            ' %6.2f%% (%d/%d) - Elapsed Time: %s - Time Remaining: %s - Memory Usage: %s',
            $progressPercentage,
            $currentIteration + 1,
            $this->totalIterations,
            $formattedElapsedTime,
            $formattedEstimatedTimeRemaining,
            $memoryUsage
        );

        $this->spinnerIndex = ($this->spinnerIndex + 1) % count($this->spinnerChars);

        if ($currentIteration + 1 == $this->totalIterations) {
            echo sprintf("\n\033[32mThe generation of %s data is complete.\033[0m\n\n", $this->dataName);
        }
    }

    private function getMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage < 1024) {
            return $memoryUsage.' bytes';
        }
        if ($memoryUsage < 1048576) {
            return round($memoryUsage / 1024, 2).' KB';
        }

        return round($memoryUsage / 1048576, 2).' MB';
    }
}
