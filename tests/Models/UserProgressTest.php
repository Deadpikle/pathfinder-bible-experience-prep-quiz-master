<?php

namespace Tests\Models;

use App\Models\User;
use App\Models\UserProgress;
use App\Models\UserType;
use PHPUnit\Framework\TestCase;

final class UserProgressTest extends TestCase
{
    public function testChapterRowsProducePerChapterAndWeightedOverallMastery(): void
    {
        $summary = UserProgress::summarizeChapterMasteryRows([
            [
                'BookID' => 1,
                'BookName' => 'John',
                'ChapterID' => 10,
                'ChapterNumber' => 1,
                'AccessibleCount' => 10,
                'MasteredCount' => 9,
            ],
            [
                'BookID' => 1,
                'BookName' => 'John',
                'ChapterID' => 11,
                'ChapterNumber' => 2,
                'AccessibleCount' => 2,
                'MasteredCount' => 1,
            ],
        ]);

        self::assertSame(12, $summary['accessibleQuestions']);
        self::assertSame(10, $summary['masteredQuestions']);
        self::assertSame(83, $summary['masteryPercent']);
        self::assertSame(90, $summary['chapters'][0]['masteryPercent']);
        self::assertSame(50, $summary['chapters'][1]['masteryPercent']);
        self::assertSame('John', $summary['chapters'][1]['bookName']);
        self::assertSame(2, $summary['chapters'][1]['chapterNumber']);
    }

    public function testNoAccessibleQuestionsProducesZeroPercentages(): void
    {
        $summary = UserProgress::summarizeChapterMasteryRows([]);

        self::assertSame(0, $summary['accessibleQuestions']);
        self::assertSame(0, $summary['masteredQuestions']);
        self::assertSame(0, $summary['masteryPercent']);
        self::assertSame([], $summary['chapters']);
    }

    public function testMalformedCountsCannotExceedAccessibleQuestionCount(): void
    {
        $summary = UserProgress::summarizeChapterMasteryRows([[
            'BookID' => 2,
            'BookName' => 'Acts',
            'ChapterID' => 20,
            'ChapterNumber' => 3,
            'AccessibleCount' => 3,
            'MasteredCount' => 7,
        ]]);

        self::assertSame(3, $summary['masteredQuestions']);
        self::assertSame(100, $summary['masteryPercent']);
        self::assertSame(100, $summary['chapters'][0]['masteryPercent']);
    }

    public function testCanonicalUserAnswerTakesPrecedenceOverAttemptMastery(): void
    {
        self::assertFalse(UserProgress::resolveMasteryState(false, true));
        self::assertTrue(UserProgress::resolveMasteryState(true, false));
        self::assertTrue(UserProgress::resolveMasteryState(null, true));
        self::assertFalse(UserProgress::resolveMasteryState(null, null));
    }

    public function testOnlyPathfindersAreReportableLearners(): void
    {
        $pathfinder = new User(1, 'Learner');
        $pathfinder->type = new UserType(2, 'Pathfinder');
        $clubAdmin = new User(2, 'Leader');
        $clubAdmin->type = new UserType(3, 'ClubAdmin');

        self::assertTrue(UserProgress::isReportablePathfinder($pathfinder));
        self::assertFalse(UserProgress::isReportablePathfinder($clubAdmin));
    }
}
