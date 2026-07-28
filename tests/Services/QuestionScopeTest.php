<?php
declare(strict_types=1);

use App\Services\QuestionScope;
use PHPUnit\Framework\TestCase;

final class QuestionScopeTest extends TestCase
{
    public function testItNormalizesBankIDsAndChecksAccess(): void
    {
        $scope = QuestionScope::fromBankIDs([2, '1', 2, 0, -1], [2], 2);

        $this->assertSame([2, 1], $scope->readableBankIDs());
        $this->assertSame([2], $scope->writableBankIDs());
        $this->assertTrue($scope->canReadBank(1));
        $this->assertTrue($scope->canWriteBank(2));
        $this->assertFalse($scope->canWriteBank(1));
        $this->assertSame(2, $scope->defaultWriteBankID());
    }

    public function testParameterizedPredicatesKeepIDsOutOfSql(): void
    {
        $scope = QuestionScope::fromBankIDs([1, 9]);

        $this->assertSame(
            ['sql' => 'question.QuestionBankID IN (?, ?)', 'params' => [1, 9]],
            $scope->readPredicate('question')
        );
    }

    public function testAnEmptyScopeAlwaysRejectsRows(): void
    {
        $scope = QuestionScope::fromBankIDs([]);

        $this->assertSame(
            ['sql' => '1 = 0', 'params' => []],
            $scope->readPredicate()
        );
        $this->assertSame('1 = 0', $scope->readPredicateLiteral());
    }

    public function testInvalidAliasesCannotBeInjectedIntoPredicates(): void
    {
        $scope = QuestionScope::fromBankIDs([1]);

        $this->assertSame(
            'q.QuestionBankID IN (?)',
            $scope->readPredicate('q; DROP TABLE Questions')['sql']
        );
    }

    public function testDefaultWriteBankFallsBackToFirstWritableBank(): void
    {
        $scope = QuestionScope::fromBankIDs([1, 2], [2], 99);

        $this->assertSame(2, $scope->defaultWriteBankID());
    }
}
