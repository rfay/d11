<?php

declare(strict_types=1);

namespace Drupal\Tests\package_manager\Unit;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests Drupal\package_manager\ValidationResult.
 *
 * @internal
 */
#[CoversClass(ValidationResult::class)]
#[Group('package_manager')]
class ValidationResultTest extends UnitTestCase {

  /**
   * Tests create warning result.
   *
   * @legacy-covers ::createWarning
   */
  #[DataProvider('providerValidConstructorArguments')]
  public function testCreateWarningResult(array $messages, ?string $summary): void {
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString, DrupalPractice.Objects.GlobalFunction
    $summary = $summary ? t($summary) : NULL;
    $result = ValidationResult::createWarning($messages, $summary);
    $this->assertResultValid($result, $messages, $summary, RequirementSeverity::Warning->value);
  }

  /**
   * Tests overall severity.
   *
   * @legacy-covers ::getOverallSeverity
   */
  public function testOverallSeverity(): void {
    // An error and a warning should be counted as an error.
    $results = [
      // phpcs:disable DrupalPractice.Objects.GlobalFunction
      ValidationResult::createError([t('Boo!')]),
      ValidationResult::createWarning([t('Moo!')]),
      // phpcs:enable DrupalPractice.Objects.GlobalFunction
    ];
    $this->assertSame(RequirementSeverity::Error->value, ValidationResult::getOverallSeverity($results));

    // If there are no results, but no errors, the results should be counted as
    // a warning.
    array_shift($results);
    $this->assertSame(RequirementSeverity::Warning->value, ValidationResult::getOverallSeverity($results));

    // If there are just plain no results, we should get
    // RequirementSeverity::OK.
    array_shift($results);
    $this->assertSame(RequirementSeverity::OK->value, ValidationResult::getOverallSeverity($results));
  }

  /**
   * Tests create error result.
   *
   * @legacy-covers ::createError
   */
  #[DataProvider('providerValidConstructorArguments')]
  public function testCreateErrorResult(array $messages, ?string $summary): void {
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString, DrupalPractice.Objects.GlobalFunction
    $summary = $summary ? t($summary) : NULL;
    $result = ValidationResult::createError($messages, $summary);
    $this->assertResultValid($result, $messages, $summary, RequirementSeverity::Error->value);
  }

  /**
   * Tests create warning result exception.
   *
   * @param string[] $messages
   *   The warning messages of the validation result.
   * @param string $expected_exception_message
   *   The expected exception message.
   *
   * @legacy-covers ::createWarning
   */
  #[DataProvider('providerCreateExceptions')]
  public function testCreateWarningResultException(array $messages, string $expected_exception_message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_exception_message);
    ValidationResult::createWarning($messages, NULL);
  }

  /**
   * Tests create error result exception.
   *
   * @param string[] $messages
   *   The error messages of the validation result.
   * @param string $expected_exception_message
   *   The expected exception message.
   *
   * @legacy-covers ::createError
   */
  #[DataProvider('providerCreateExceptions')]
  public function testCreateErrorResultException(array $messages, string $expected_exception_message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_exception_message);
    ValidationResult::createError($messages, NULL);
  }

  /**
   * Tests that the messages are asserted to be translatable.
   */
  #[TestWith(["createError"])]
  #[TestWith(["createWarning"])]
  public function testMessagesMustBeTranslatable(string $method): void {
    // When creating an error from a throwable, the message does not need to be
    // translatable.
    ValidationResult::createErrorFromThrowable(new \Exception('Burn it down.'));

    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessageMatches('/instanceof TranslatableMarkup/');
    ValidationResult::$method(['Not translatable!']);
  }

  /**
   * Data provider for test methods that test create exceptions.
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerCreateExceptions(): array {
    return [
      '2 messages, no summary' => [
        [t('Something is wrong'), t('Something else is also wrong')],
        'If more than one message is provided, a summary is required.',
      ],
      'no messages' => [
        [],
        'At least one message is required.',
      ],
    ];
  }

  /**
   * Data provider for testCreateWarningResult().
   *
   * @return mixed[]
   *   The test cases.
   */
  public static function providerValidConstructorArguments(): array {
    return [
      '1 message no summary' => [
        'messages' => [t('Something is wrong')],
        'summary' => NULL,
      ],
      '2 messages has summary' => [
        'messages' => [
          t('Something is wrong'),
          t('Something else is also wrong'),
        ],
        'summary' => 'This sums it up.',
      ],
    ];
  }

  /**
   * Asserts a check result is valid.
   *
   * @param \Drupal\package_manager\ValidationResult $result
   *   The validation result to check.
   * @param array $expected_messages
   *   The expected messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The expected summary or NULL if not summary is expected.
   * @param int $severity
   *   The severity.
   */
  protected function assertResultValid(ValidationResult $result, array $expected_messages, ?TranslatableMarkup $summary, int $severity): void {
    $this->assertSame($expected_messages, $result->messages);
    if ($summary === NULL) {
      $this->assertNull($result->summary);
    }
    else {
      $this->assertSame($summary->getUntranslatedString(), $result->summary
        ->getUntranslatedString());
    }
    $this->assertSame($severity, $result->severity);
  }

}
