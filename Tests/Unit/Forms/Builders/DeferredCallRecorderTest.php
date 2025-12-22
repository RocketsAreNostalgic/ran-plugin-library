<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\DeferredCallRecorder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ran\PluginLib\Forms\Builders\DeferredCallRecorder
 */
final class DeferredCallRecorderTest extends TestCase {
	public function test_replay_applies_calls_in_order(): void {
		$recorder = new DeferredCallRecorder();
		$target   = new DeferredCallRecorderTestTarget();

		$recorder->record('first', array('a'), 0);
		$recorder->record('second', array('b'), 0);

		$returned = $recorder->replay($target);

		self::assertSame($target, $returned);
		self::assertSame(
			array('first:a', 'second:b'),
			$target->calls
		);
	}

	public function test_replay_switches_target_when_call_returns_object(): void {
		$recorder = new DeferredCallRecorder();
		$b        = new DeferredCallRecorderTestTargetSwitchB();
		$a        = new DeferredCallRecorderTestTargetSwitchA($b);

		$recorder->record('switch', array(), 0);
		$recorder->record('after', array('x'), 0);

		$returned = $recorder->replay($a);

		self::assertSame($b, $returned);
		self::assertSame(array('switch'), $a->calls);
		self::assertSame(array('after:x'), $b->calls);
	}

	public function test_replay_failure_throws_runtime_exception_with_call_chain_and_previous_exception(): void {
		$recorder = new DeferredCallRecorder();
		$target   = new DeferredCallRecorderTestTargetThrowing();

		$recorder->record('ok', array(), 0);
		$recorder->record('boom', array(), 0);

		try {
			$recorder->replay($target);
			self::fail('Expected replay() to throw when a deferred call throws.');
		} catch (\RuntimeException $e) {
			self::assertStringContainsString('Error replaying boom()', $e->getMessage());
			self::assertMatchesRegularExpression('/defined at .*\.php:\d+/', $e->getMessage());
			self::assertStringContainsString('Call chain:', $e->getMessage());
			self::assertStringContainsString('ok() at ', $e->getMessage());
			self::assertStringContainsString('boom() at ', $e->getMessage());
			self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
			self::assertSame('kaboom', $e->getPrevious()?->getMessage());
		}
	}
}

final class DeferredCallRecorderTestTarget {
	/**
	 * @var list<string>
	 */
	public array $calls = array();

	public function first(string $value): void {
		$this->calls[] = 'first:' . $value;
	}

	public function second(string $value): void {
		$this->calls[] = 'second:' . $value;
	}
}

final class DeferredCallRecorderTestTargetSwitchA {
	/**
	 * @var list<string>
	 */
	public array $calls = array();

	public function __construct(private DeferredCallRecorderTestTargetSwitchB $b) {
	}

	public function switch(): DeferredCallRecorderTestTargetSwitchB {
		$this->calls[] = 'switch';
		return $this->b;
	}
}

final class DeferredCallRecorderTestTargetSwitchB {
	/**
	 * @var list<string>
	 */
	public array $calls = array();

	public function after(string $value): void {
		$this->calls[] = 'after:' . $value;
	}
}

final class DeferredCallRecorderTestTargetThrowing {
	public function ok(): void {
	}

	public function boom(): void {
		throw new \InvalidArgumentException('kaboom');
	}
}
