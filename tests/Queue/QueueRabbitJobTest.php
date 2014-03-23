<?php

use Mockery as m;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Http\Response;

class QueueRabbitJobTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}

	public function setUp() {

		$this->mockedContainer = m::mock('Illuminate\Container\Container');
		$this->mockedConnection = m::mock('PhpAmqpLib\Connection\AMQPConnection');
		$this->mockedChannel = m::mock('PhpAmqpLib\Channel\AMQPChannel');
		$this->mockedContainer = m::mock('Illuminate\Container\Container');
		$this->mockedRabbitQueue = m::mock('Illuminate\Queue\RabbitQueue');
		$this->mockedQueueManager = m::mock('Illuminate\Queue\QueueManager');
		$this->mockedDatabaseFailedJobProvider = m::mock('Illuminate\Queue\Failed\DatabaseFailedJobProvider');

		$this->job = 'foo';
		$this->data = array('data');
		$this->queue = 'default';
		$this->delay = 0;
		$this->maxRetries = 3;

		$this->payload = json_encode(array('job' => $this->job, 'data' => $this->data, 'attempts' => 1, 'queue' => $this->queue));
		$this->recreatedPayload = json_encode(array('job' => $this->job, 'data' => $this->data, 'attempts' => 2, 'queue' => $this->queue));
		$this->maxRetriesPayload = json_encode(array('job' => $this->job, 'data' => $this->data, 'attempts' => $this->maxRetries, 'queue' => $this->queue));
		$this->expiredPayload = json_encode(array('job' => $this->job, 'data' => $this->data, 'attempts' => $this->maxRetries + 1, 'queue' => $this->queue));

		$this->message = $this->getMessage($this->payload, $this->queue);
	}

	public function testFireProperlyCallsTheJobHandler()
	{
		$this->mockedRabbitQueue->shouldReceive('getQueue')->andReturn($this->queue);
		$job = $this->getJob();
		$job->getContainer()->shouldReceive('make')->once()->with('foo')->andReturn($handler = m::mock('StdClass'));
		$handler->shouldReceive('fire')->once()->with($job, array('data'));
		$job->fire();
	}

	public function testDeleteSendsAckToRemoveJob()
	{
		$this->mockedRabbitQueue->shouldReceive('getQueue')->andReturn($this->queue);
		$job = $this->getJob();
		$job->getRabbit()->shouldReceive('getChannel')->twice()->andReturn(m::mock('PhpAmqpLib\Channel\AMQPChannel'));
		$job->getRabbit()->getChannel()->shouldReceive('basic_ack')->once()->with($this->message->delivery_info['delivery_tag'])->andReturn(null);
		$job->delete();
        }

	public function testReleaseProperlyReleasesJobOntoRabbit()
	{
		$this->mockedRabbitQueue->shouldReceive('getQueue')->andReturn($this->queue);
		$job = $this->getJob();
		$job->getRabbit()->shouldReceive('getChannel')->twice()->andReturn(m::mock('PhpAmqpLib\Channel\AMQPChannel'));
		$job->getRabbit()->getChannel()->shouldReceive('basic_ack')->once()->with($this->message->delivery_info['delivery_tag'])->andReturn(null);
		$job->getRabbit()->shouldReceive('recreate')->once()->with($this->recreatedPayload, 'default', 2);
		$job->release(2);
	}

	public function testAttemptsReturnsCurrentCountOfAttempts()
	{
		$this->mockedRabbitQueue->shouldReceive('getQueue')->andReturn($this->queue);
		$job = $this->getJob();
		$one = $job->attempts();
		$this->assertEquals($one, 1);
	}

	public function testFailedJobWouldGetLoggedAfterMaxTriesHasBeenExceeded()
	{
		$worker = $this->getWorker();
		$worker->getManager()->shouldReceive('connection')->once()->with('rabbit')->andReturn($queue = $this->mockedRabbitQueue);
		$worker->getManager()->shouldReceive('getName')->andReturn('rabbit');
		$this->mockedDatabaseFailedJobProvider->shouldReceive('log')->with('rabbit', $this->queue, $this->expiredPayload);
		$this->mockedRabbitQueue->shouldReceive('getQueue')->once()->andReturn($this->queue);
		$job = $this->getJob($this->getMessage($this->expiredPayload, $this->queue));
		$this->mockedRabbitQueue->shouldReceive('getChannel')->once()->andReturn($this->mockedChannel);
		$this->mockedChannel->shouldReceive('basic_ack')->once()->with($this->message->delivery_info['delivery_tag'])->andReturn(null);
		$worker->getManager()->connection('rabbit');
		$worker->process('rabbit', $job, $this->maxRetries, $this->delay);
	}

	protected function getJob($message = null)
	{
		return new Illuminate\Queue\Jobs\RabbitJob(
			$this->mockedContainer,
			$this->mockedRabbitQueue,
			$message ?: $this->message
		);
	}

	protected function getWorker()
	{
		return new Illuminate\Queue\Worker(
			$this->mockedQueueManager,
			$this->mockedDatabaseFailedJobProvider
		);
	}

	protected function getMessage($payload, $queue)
	{
		$message = new AMQPMessage($payload, array('delivery_mode' => 2));
		$message->delivery_info = array(
			"channel" => 'foo',
			"consumer_tag" => 'bar',
			"delivery_tag" => 1,
			"redelivered" => false,
			"exchange" => "",
			"routing_key" => $queue
		);

		return $message;
	}

}
