<?php namespace Illuminate\Queue\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SubscribeCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'queue:subscribe';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Subscribe a URL to an Iron.io or SQS push queue';

	/**
	 * Execute the console command.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException
	 */
	public function fire()
	{
		$queue = $this->laravel['queue']->connection();

		$advanced = json_decode(stripslashes($this->option('advanced')), true);

		$queue->subscribe($this->argument('queue'), $this->argument('url'), array_only($this->option(), array('retries', 'errqueue')), $advanced);

		$this->line('<info>Queue subscriber added:</info> <comment>'.$this->argument('url').'</comment>');
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('queue', InputArgument::REQUIRED, 'The name of Iron.io queue or SNS topic.'),

			array('url', InputArgument::REQUIRED, 'The URL to be subscribed.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('retries', null, InputOption::VALUE_OPTIONAL, 'Number of retries.', 3),

			array('errqueue', null, InputOption::VALUE_OPTIONAL, 'The error queue where all failed push messages will be routed after N retries.'),
			
			array('advanced', null, InputOption::VALUE_OPTIONAL, 'The JSON string containing advanced options specific to the 3rd party queue service.'),
		);
	}

}
