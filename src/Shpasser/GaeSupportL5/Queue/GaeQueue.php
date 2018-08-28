<?php

namespace Shpasser\GaeSupportL5\Queue;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use google\appengine\api\taskqueue\PushTask;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GaeQueue extends Queue implements QueueContract
{
	const PAYLOAD_REQ_PARAM_NAME = 'data';

	/**
	 * The current request instance.
	 *
	 * @var \Illuminate\Http\Request
	 */
	protected $request;

	/**
	 * The name of the default tube.
	 *
	 * @var string
	 */
	protected $default;

	/**
	 * URL for push.
	 * @var string
	 */
	protected $url;

	/**
	 * Indicates if the messages should be encrypted.
	 *
	 * @var bool
	 */
	protected $shouldEncrypt;

	/**
	 * Create a new Gae queue instance.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  string $default
	 * @param  string $url
	 * @param  bool $shouldEncrypt
	 */
	public function __construct(Request $request, $default, $url, $shouldEncrypt = false){
		$this->request = $request;
		$this->url = $url;
		$this->default = $default;
		$this->shouldEncrypt = $shouldEncrypt;
	}

	/**
	 * Get the size of the queue.
	 *
	 * @param  string $queue
	 *
	 * @return int
	 */
	public function size($queue = null){
		return 1;
	}

	/**
	 * Push a new job onto the queue.
	 *
	 * @param  string $job
	 * @param  mixed $data
	 * @param  string $queue
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function push($job, $data = '', $queue = null){
		return $this->pushRaw($this->createPayload($job, $data), $queue);
	}

	/**
	 * Push a raw payload onto the queue.
	 *
	 * @param  string $payload
	 * @param  string $queue
	 * @param  array $options
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function pushRaw($payload, $queue = null, array $options = []){
		if($this->shouldEncrypt){
			$payload = Crypt::encrypt($payload);
		}

		$task = new PushTask($this->url, [self::PAYLOAD_REQ_PARAM_NAME => $payload], $options);

		return $task->add($this->getQueue($queue));
	}

	/**
	 * Push a raw payload onto the queue after encrypting the payload.
	 *
	 * @param  string $payload
	 * @param  string $queue
	 * @param  int $delay
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function recreate($payload, $queue = null, $delay){
		$options = ['delay_seconds' => $this->getSeconds($delay)];

		return $this->pushRaw($payload, $queue, $options);
	}

	/**
	 * Push a new job onto the queue after a delay.
	 *
	 * @param  \DateTime|int $delay
	 * @param  string $job
	 * @param  mixed $data
	 * @param  string $queue
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function later($delay, $job, $data = '', $queue = null){
		$delay_seconds = $this->getSeconds($delay);

		$payload = $this->createPayload($job, $data);

		return $this->pushRaw($payload, $queue, compact('delay_seconds'));
	}

	/**
	 * Pop the next job off of the queue.
	 *
	 * @param  string $queue
	 *
//	 * @return \Illuminate\Queue\Jobs\Job|null
	 */
	public function pop($queue = null){
		throw new \RuntimeException("Pop is not supported by GaeQueue.");
	}

	/**
	 * Delete a message from the Gae queue.
	 *
	 * @param  string $queue
	 * @param  string $id
	 *
	 * @return void
	 */
	public function deleteMessage($queue, $id){
		throw new \RuntimeException("Delete is not supported by GaeQueue.");
	}

	/**
	 * Marshal a push queue request and fire the job.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function marshal(){
		try{
			$job = $this->marshalPushedJob();
		}catch(\Exception $e){
			// Ignore for security reasons!
			// So if we are being hacked
			// the hacker would think it went OK.
			Log::warning('Marshalling Queue Request: Invalid job.');

			return new Response('OK');
		}

		if(isset($job->id)){
			$this->createPushedGaeJob($job)->fire();
		}else{
			Log::warning('Marshalling Queue Request: No GAE header supplied.');
		}

		return new Response('OK');
	}

	/**
	 * Marshal out the pushed job and payload.
	 *
	 * @return object
	 */
	protected function marshalPushedJob(){
		$r = $this->request;

		$body = $this->parseJobBody($r->input(self::PAYLOAD_REQ_PARAM_NAME));

		return (object)[
			'id' => $r->header('X-AppEngine-TaskName'),
			'body' => $body,
			'pushed' => true,
		];
	}

	/**
	 * Create a new GaeJob for a pushed job.
	 *
	 * @param  object $job
	 *
	 * @return \Shpasser\GaeSupportL5\Queue\GaeJob
	 */
	protected function createPushedGaeJob($job){
		return new GaeJob($this->container, $this, $job, true);
	}

	/**
	 * Parse the job body for firing.
	 *
	 * @param  string $body
	 *
	 * @return string
	 */
	protected function parseJobBody($body){
		return $this->shouldEncrypt ? Crypt::decrypt($body) : $body;
	}

	/**
	 * Get the queue or return the default.
	 *
	 * @param  string|null $queue
	 *
	 * @return string
	 */
	public function getQueue($queue){
		return $queue ?: $this->default;
	}

	/**
	 * Get the request instance.
	 *
	 * @return \Illuminate\Http\Request
	 */
	public function getRequest(){
		return $this->request;
	}

	/**
	 * Set the request instance.
	 *
	 * @param \Illuminate\Http\Request $request
	 */
	public function setRequest(Request $request){
		$this->request = $request;
	}

	/**
	 * Calculate the number of seconds with the given delay.
	 *
	 * @param  \DateTime|int $delay
	 *
	 * @return int
	 */
	protected function getSeconds($delay){
		if($delay instanceof \DateTime){
			return max(0, $delay->getTimestamp() - $this->getTime());
		}

		return (int)$delay;
	}

	/**
	 * Get the current system time.
	 *
	 * @return int
	 */
	protected function getTime(){
		return time();
	}
}
