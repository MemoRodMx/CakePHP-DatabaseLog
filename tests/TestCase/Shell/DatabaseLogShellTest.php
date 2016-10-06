<?php

namespace DatabaseLog\Test\TestCase\Shell;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use DatabaseLog\Shell\DatabaseLogShell;
use Tools\TestSuite\ConsoleOutput;
use Tools\TestSuite\TestCase;

/**
 */
class DatabaseLogShellTest extends TestCase {

	/**
	 * @var array
	 */
	public $fixtures = [
		'plugin.database_log.database_logs'
	];

	/**
	 * @var \DatabaseLog\Shell\DatabaseLogShell|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $Shell;

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Configure::delete('DatabaseLog.limit');
		Configure::delete('DatabaseLog.maxLength');

		$this->out = new ConsoleOutput();
		$this->err = new ConsoleOutput();
		$io = new ConsoleIo($this->out, $this->err);

		$this->Shell = $this->getMockBuilder(DatabaseLogShell::class)
			->setMethods(['in', '_stop'])
			->setConstructorArgs([$io])
			->getMock();
	}

	/**
	 * tearDown
	 *
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Shell);
	}

	/**
	 * @return void
	 */
	public function testCleanup() {
		Log::write('info', 'one');
		Log::write('info', 'two');
		Log::write('info', 'three');

		$this->Logs = TableRegistry::get('DatabaseLog.DatabaseLogs');

		$this->Logs->updateAll(['created' => date('Y-m-d H:i:s', time() - DAY)], '1 = 1');

		Log::write('info', 'four');
		Log::write('info', 'five');
		Log::write('info', 'six');

		$count = $this->Logs->find()->count();
		$this->assertTrue($count > 4);

		Configure::write('DatabaseLog.limit', 2);
		Configure::write('DatabaseLog.maxLength', '-1 hour');

		$this->Shell->runCommand(['cleanup']);
		$output = (string)$this->out->output();
		$this->assertNotEmpty($output);

		$this->assertContains('6 duplicates removed', $output);
		$this->assertContains('4 outdated logs removed', $output);

		$count = $this->Logs->find()->count();
		$this->assertSame(2, $count);
	}

	/**
	 * @return void
	 */
	public function testReset() {
		$this->Logs = TableRegistry::get('DatabaseLog.DatabaseLogs');

		Log::write('info', 'six');

		$count = $this->Logs->find()->count();
		$this->assertTrue($count > 0);

		$this->Shell->runCommand(['reset', '-q']);

		$count = $this->Logs->find()->count();
		$this->assertSame(0, $count);
	}

	/**
	 * @return void
	 */
	public function testTestEntry() {
		$this->Logs = TableRegistry::get('DatabaseLog.DatabaseLogs');
		$count = $this->Logs->find()->count();

		$this->Shell->runCommand(['test_entry']);
		//$output = $this->out->output();

		$newCount = $this->Logs->find()->count();

		$this->assertSame($count + 2, $newCount); // Should only be 1...
	}

	/**
	 * @return void
	 */
	public function testTestEntryCustom() {
		$this->Logs = TableRegistry::get('DatabaseLog.DatabaseLogs');

		$this->Shell->runCommand(['test_entry', 'warning', 'My warning']);

		$log = $this->Logs->find()->order(['id' => 'DESC'])->first();

		$this->assertSame('warning', $log->type);
		$this->assertSame('My warning', $log->message);
	}

}