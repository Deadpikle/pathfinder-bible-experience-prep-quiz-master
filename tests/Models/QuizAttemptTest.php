<?php

namespace Tests\Models;

use App\Models\QuizAttempt;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuizAttemptTest extends TestCase
{
    public function testNormaliseAnswersClampsPointsAndUsesAttemptSnapshot(): void
    {
        $items = [[
            'QuizAttemptItemID' => 12,
            'QuestionID' => 44,
            'PointsPossible' => 3,
        ]];

        $answers = QuizAttempt::normaliseAnswers([[
            'attemptItemID' => 12,
            'questionID' => 999,
            'pointsEarned' => 20,
            'correct' => 'true',
            'userAnswer' => '  response  ',
        ]], $items);

        self::assertSame(44, $answers[0]['questionID']);
        self::assertSame(3, $answers[0]['pointsEarned']);
        self::assertTrue($answers[0]['correct']);
        self::assertSame('response', $answers[0]['userAnswer']);
    }

    public function testEveryAttemptItemMustBeSubmittedExactlyOnce(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(422);
        QuizAttempt::normaliseAnswers([], [[
            'QuizAttemptItemID' => 1,
            'QuestionID' => 2,
            'PointsPossible' => 1,
        ]]);
    }

    public function testMalformedAnswerItemsReturnAValidationError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(422);
        QuizAttempt::normaliseAnswers(['not-an-answer-object'], [[
            'QuizAttemptItemID' => 1,
            'QuestionID' => 2,
            'PointsPossible' => 1,
        ]]);
    }

    public function testUserAnswerSnapshotIsBounded(): void
    {
        $answers = QuizAttempt::normaliseAnswers([[
            'attemptItemID' => 1,
            'userAnswer' => str_repeat('x', 6000),
        ]], [[
            'QuizAttemptItemID' => 1,
            'QuestionID' => 2,
            'PointsPossible' => 1,
        ]]);

        self::assertSame(5000, mb_strlen($answers[0]['userAnswer']));
    }

    public function testCompletionHashIsIndependentOfSubmissionOrder(): void
    {
        $a = [
            ['attemptItemID' => 2, 'pointsEarned' => 0, 'correct' => false, 'userAnswer' => 'b'],
            ['attemptItemID' => 1, 'pointsEarned' => 1, 'correct' => true, 'userAnswer' => 'a'],
        ];
        $b = array_reverse($a);

        self::assertSame(QuizAttempt::completionHash($a), QuizAttempt::completionHash($b));
    }
}
